<?php

namespace App\Policies;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\User;

class LedgerPolicy
{
    /** Qualquer usuário autenticado pode listar o próprio extrato (a query escopa por user_id). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Pode ver/verificar a linha: dono da carteira da linha, ou contraparte
     * (comprador/vendedor) da order referenciada. O controller de verify chama
     * via cannot() e devolve 404 (anti-enumeração, regra 4), nunca 403.
     */
    public function view(User $user, LedgerEntry $entry): bool
    {
        if ($entry->user_id === $user->id) {
            return true;
        }

        if ($entry->reference_type === 'order' && $entry->reference_id !== null) {
            return Order::whereKey($entry->reference_id)
                ->where(function ($query) use ($user) {
                    $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
                })
                ->exists();
        }

        return false;
    }
}
