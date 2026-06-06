<?php

namespace App\Enums;

enum BoostPaymentMethod: string
{
    case Wallet = 'wallet';
    case Pix = 'pix';
}
