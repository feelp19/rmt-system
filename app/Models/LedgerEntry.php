<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'wallet_id',
    'user_id',
    'type',
    'direction',
    'amount_cents',
    'balance_after_cents',
    'reference_type',
    'reference_id',
    'seq',
    'prev_hash',
    'hash',
])]
class LedgerEntry extends Model
{
    /** @use HasFactory<\Database\Factories\LedgerEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'direction' => LedgerDirection::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'reference_id' => 'integer',
            'seq' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
