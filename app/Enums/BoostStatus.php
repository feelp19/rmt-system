<?php

namespace App\Enums;

enum BoostStatus: string
{
    case PendingPayment = 'pending_payment'; // aguardando confirmação PIX (Inc 2)
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
