<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** Retorna a carteira do usuário, criando-a se ainda não existir. */
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Credita saldo na carteira (top-up de demonstração do MVP).
     *
     * Antes era um increment lock-free; agora a escrita do saldo e o append no
     * ledger encadeado precisam ser atômicos e serializados → transação +
     * lockForUpdate na wallet (a cadeia por-carteira lê a cabeça sob o mesmo lock).
     */
    public function deposit(User $user, int $amountCents): Wallet
    {
        $this->walletFor($user); // garante a carteira fora do lock (evita create sob lock)

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance_cents', $amountCents);

            $this->ledger->record(
                $wallet,
                LedgerEntryType::DepositCredit,
                LedgerDirection::Credit,
                $amountCents,
                $wallet->balance_cents,
                'deposit',
                null,
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $wallet->refresh();
    }
}
