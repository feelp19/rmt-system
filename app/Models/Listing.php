<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['seller_id', 'game', 'type', 'title', 'description', 'quantity', 'price_cents', 'status'])]
class Listing extends Model
{
    /** @use HasFactory<\Database\Factories\ListingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ListingType::class,
            'status' => ListingStatus::class,
            'quantity' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function boosts(): HasMany
    {
        return $this->hasMany(Boost::class);
    }

    /** Boost ativo atual do anúncio (para badge e ordenação). */
    public function activeBoost(): HasOne
    {
        return $this->hasOne(Boost::class)->active()->latestOfMany();
    }
}
