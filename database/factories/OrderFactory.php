<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 100000);
        $fee = intdiv($amount * 5, 100);

        return [
            'listing_id' => Listing::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'amount_cents' => $amount,
            'fee_cents' => $fee,
            'seller_payout_cents' => $amount - $fee,
            'status' => OrderStatus::AwaitingConfirmation,
        ];
    }
}
