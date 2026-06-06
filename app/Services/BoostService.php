<?php

namespace App\Services;

use App\Enums\BoostPaymentMethod;
use App\Enums\BoostStatus;
use App\Enums\BoostTier;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Boost;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use DomainException;
use Illuminate\Support\Facades\DB;

class BoostService
{
    public function __construct(
        private readonly XpService $xp,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Compra um boost pagando com o saldo da carteira do anunciante.
     *
     * Escritas em wallets + boosts (tabelas distintas) → transação explícita
     * + lock na carteira (serializa débitos e a checagem de boost ativo
     * para o mesmo usuário) — padrão do escrow (regra 8 + concorrência).
     */
    public function purchaseWithWallet(User $user, Listing $listing, BoostTier $tier): Boost
    {
        $priceCents = $tier->priceCents();

        DB::beginTransaction();
        try {
            // Ordem de lock = listing → wallet (mesma do OrderService, evita deadlock).
            // Lock no anúncio garante "máx. 1 boost ativo" mesmo quando o pagamento
            // não passa pela carteira (ex.: PIX no Inc 2).
            Listing::whereKey($listing->id)->lockForUpdate()->first();
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if ($this->hasActiveBoost($listing)) {
                throw new DomainException('Este anúncio já está turbinado.');
            }

            if ($wallet === null || $wallet->balance_cents < $priceCents) {
                throw new DomainException('Saldo insuficiente para o boost.');
            }

            $wallet->decrement('balance_cents', $priceCents);

            $boost = $this->createActiveBoost($listing, $user, $tier, BoostPaymentMethod::Wallet);

            $this->ledger->record(
                $wallet,
                LedgerEntryType::BoostDebit,
                LedgerDirection::Debit,
                $priceCents,
                $wallet->balance_cents,
                'boost',
                $boost->id,
            );

            $this->xp->award($user->id, XpService::BOOST);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $boost;
    }

    public function hasActiveBoost(Listing $listing): bool
    {
        return $listing->boosts()->active()->exists();
    }

    /** Cria o boost já ativo (now → now + boost_days). Reutilizado pelo pagamento PIX (Inc 2). */
    private function createActiveBoost(
        Listing $listing,
        User $user,
        BoostTier $tier,
        BoostPaymentMethod $method,
    ): Boost {
        $now = now();
        $days = (int) config('marketplace.boost_days', 7);

        return Boost::create([
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'tier' => $tier,
            'weight' => $tier->weight(),
            'price_cents' => $tier->priceCents(),
            'payment_method' => $method,
            'status' => BoostStatus::Active,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addDays($days),
            'paid_at' => $now,
        ]);
    }
}
