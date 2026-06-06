<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** Qualquer usuário autenticado pode comprar. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Apenas as partes do pedido podem visualizá-lo. */
    public function view(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id || $order->seller_id === $user->id;
    }

    /** Só o vendedor confirma a entrega. */
    public function confirmDelivery(User $user, Order $order): bool
    {
        return $order->seller_id === $user->id;
    }

    /** Só o comprador confirma o recebimento. */
    public function confirmReceipt(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id;
    }
}
