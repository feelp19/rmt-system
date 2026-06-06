<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly XpService $xp,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Comprador adquire um anúncio: o valor é debitado da carteira do
     * comprador e fica retido (escrow) na Order até a dupla confirmação.
     *
     * Múltiplas escritas inter-dependentes em tabelas distintas
     * (wallets, orders, listings) → transação explícita + lockForUpdate
     * para impedir venda dupla e corrida de saldo (regra 8 + concorrência).
     */
    public function purchase(User $buyer, Listing $listing): Order
    {
        if ($listing->seller_id === $buyer->id) {
            throw new DomainException('Você não pode comprar seu próprio anúncio.');
        }

        DB::beginTransaction();
        try {
            // Re-lê o anúncio com lock para travar o estado durante a compra.
            $lockedListing = Listing::whereKey($listing->id)->lockForUpdate()->first();

            if ($lockedListing === null || $lockedListing->status !== ListingStatus::Active) {
                throw new DomainException('Anúncio indisponível.');
            }

            $buyerWallet = Wallet::where('user_id', $buyer->id)->lockForUpdate()->first();
            $price = $lockedListing->price_cents;

            if ($buyerWallet === null || $buyerWallet->balance_cents < $price) {
                throw new DomainException('Saldo insuficiente.');
            }

            // Taxa depende do nível do vendedor (perk de XP) — lock pra não ler
            // xp stale enquanto outra venda do mesmo vendedor concede XP.
            $seller = User::whereKey($lockedListing->seller_id)->lockForUpdate()->firstOrFail();
            $feeCents = $this->feeFor($price, $seller);
            $payoutCents = $price - $feeCents;

            $buyerWallet->decrement('balance_cents', $price);

            $order = Order::create([
                'listing_id' => $lockedListing->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $lockedListing->seller_id,
                'amount_cents' => $price,
                'fee_cents' => $feeCents,
                'seller_payout_cents' => $payoutCents,
                'status' => OrderStatus::AwaitingConfirmation,
            ]);

            $lockedListing->update(['status' => ListingStatus::Sold]);

            $this->ledger->record(
                $buyerWallet,
                LedgerEntryType::EscrowDebit,
                LedgerDirection::Debit,
                $price,
                $buyerWallet->balance_cents,
                'order',
                $order->id,
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $order;
    }

    /** Vendedor confirma que entregou. Libera o escrow se o comprador já confirmou. */
    public function confirmDelivery(Order $order): Order
    {
        return $this->registerConfirmation($order, 'seller_confirmed_at');
    }

    /** Comprador confirma que recebeu. Libera o escrow se o vendedor já confirmou. */
    public function confirmReceipt(Order $order): Order
    {
        return $this->registerConfirmation($order, 'buyer_confirmed_at');
    }

    /**
     * Marca o lado ($column) que confirmou e, quando ambos confirmaram,
     * libera o escrow. Idempotente: confirmar de novo é no-op.
     *
     * Escrita na order + possível crédito na carteira do vendedor (tabelas
     * distintas) → transação explícita + lock na order para serializar as
     * duas confirmações concorrentes e liberar o escrow exatamente uma vez.
     */
    private function registerConfirmation(Order $order, string $column): Order
    {
        DB::beginTransaction();
        try {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            // Já concluída ou cancelada: nada a fazer (idempotente).
            if ($locked->status !== OrderStatus::AwaitingConfirmation) {
                DB::commit();

                return $locked;
            }

            if ($locked->{$column} === null) {
                $locked->{$column} = now();
                $locked->save();
            }

            $bothConfirmed = $locked->seller_confirmed_at !== null
                && $locked->buyer_confirmed_at !== null;

            if ($bothConfirmed) {
                $this->release($locked);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $locked->refresh();
    }

    /** Libera o valor retido: credita o vendedor (valor - taxa) e conclui a order. */
    private function release(Order $order): void
    {
        // Lock na carteira do vendedor (mesma disciplina do purchase) para
        // serializar o crédito. A carteira é garantida na criação do usuário;
        // se faltar (edge), cria — mas o normal é existir e ser travada.
        $sellerWallet = Wallet::where('user_id', $order->seller_id)->lockForUpdate()->first()
            ?? Wallet::create(['user_id' => $order->seller_id]);
        $sellerWallet->increment('balance_cents', $order->seller_payout_cents);
        $this->ledger->record(
            $sellerWallet,
            LedgerEntryType::EscrowReleaseCredit,
            LedgerDirection::Credit,
            $order->seller_payout_cents,
            $sellerWallet->balance_cents,
            'order',
            $order->id,
        );
        // A taxa (fee_cents) permanece retida pela plataforma — o MVP não
        // mantém uma conta de plataforma, então o valor simplesmente não é creditado.

        $order->status = OrderStatus::Completed;
        $order->completed_at = now();
        $order->save();

        // XP por transação concluída (reputação/perks/ranking).
        $this->xp->award($order->seller_id, XpService::SELL);
        $this->xp->award($order->buyer_id, XpService::BUY);
    }

    /**
     * Taxa da plataforma em centavos inteiros. Em basis points, com desconto
     * pelo nível do vendedor (perk): base 5%, piso 3%. Arredonda para baixo.
     */
    private function feeFor(int $amountCents, User $seller): int
    {
        $baseBps = ((int) config('marketplace.fee_percent', 5)) * 100;
        $level = XpService::levelForXp((int) $seller->xp);
        $feeBps = XpService::feeBpsForLevel($level, $baseBps);

        return intdiv($amountCents * $feeBps, 10000);
    }
}
