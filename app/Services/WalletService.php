<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;

class WalletService
{
    /** Retorna a carteira do usuário, criando-a se ainda não existir. */
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Credita saldo na carteira (top-up de demonstração do MVP).
     *
     * Escrita única em uma linha → increment atômico, sem transação
     * explícita (regra 8: transação só para múltiplas escritas distintas).
     */
    public function deposit(User $user, int $amountCents): Wallet
    {
        $wallet = $this->walletFor($user);
        $wallet->increment('balance_cents', $amountCents);

        return $wallet->refresh();
    }
}
