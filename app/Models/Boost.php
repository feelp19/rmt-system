<?php

namespace App\Models;

use App\Enums\BoostPaymentMethod;
use App\Enums\BoostStatus;
use App\Enums\BoostTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'listing_id',
    'user_id',
    'tier',
    'weight',
    'price_cents',
    'payment_method',
    'status',
    'starts_at',
    'expires_at',
    'paid_at',
])]
class Boost extends Model
{
    /** @use HasFactory<\Database\Factories\BoostFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tier' => BoostTier::class,
            'status' => BoostStatus::class,
            'payment_method' => BoostPaymentMethod::class,
            'weight' => 'integer',
            'price_cents' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** Boost ativo = status active e ainda não expirou. */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', BoostStatus::Active)
            ->where('expires_at', '>', now());
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
