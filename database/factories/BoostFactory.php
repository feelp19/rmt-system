<?php

namespace Database\Factories;

use App\Enums\BoostPaymentMethod;
use App\Enums\BoostStatus;
use App\Enums\BoostTier;
use App\Models\Boost;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Boost>
 */
class BoostFactory extends Factory
{
    protected $model = Boost::class;

    public function definition(): array
    {
        $tier = fake()->randomElement(BoostTier::cases());
        $now = now();

        return [
            'listing_id' => Listing::factory(),
            'user_id' => User::factory(),
            'tier' => $tier,
            'weight' => $tier->weight(),
            'price_cents' => $tier->priceCents(),
            'payment_method' => BoostPaymentMethod::Wallet,
            'status' => BoostStatus::Active,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addDays(7),
            'paid_at' => $now,
        ];
    }

    public function tier(BoostTier $tier): static
    {
        return $this->state(fn () => [
            'tier' => $tier,
            'weight' => $tier->weight(),
            'price_cents' => $tier->priceCents(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => BoostStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }
}
