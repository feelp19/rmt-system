<?php

namespace App\Models;

use App\Enums\PixChargeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'pushinpay_id',
    'amount_cents',
    'status',
    'qr_code',
    'qr_code_base64',
    'end_to_end_id',
    'paid_at',
    'expires_at',
])]
class PixCharge extends Model
{
    /** @use HasFactory<\Database\Factories\PixChargeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PixChargeStatus::class,
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PixChargeStatus::Paid;
    }
}
