<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
