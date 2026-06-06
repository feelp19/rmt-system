<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'game' => fake()->randomElement(['World of Warcraft', 'RuneScape', 'Tibia', 'Path of Exile', 'Diablo IV']),
            'type' => fake()->randomElement(ListingType::cases()),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(10),
            'quantity' => fake()->numberBetween(1, 1000),
            'price_cents' => fake()->numberBetween(500, 200000),
            'status' => ListingStatus::Active,
        ];
    }
}
