<?php

namespace App\Enums;

enum PixChargeStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Expired = 'expired';
    case Canceled = 'canceled';
}
