<?php

namespace Database\Factories;

use App\Enums\PixChargeStatus;
use App\Models\PixCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PixCharge>
 */
class PixChargeFactory extends Factory
{
    protected $model = PixCharge::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pushinpay_id' => fake()->uuid(),
            'amount_cents' => fake()->numberBetween(500, 50000),
            'status' => PixChargeStatus::Created,
            'qr_code' => '000201'.fake()->numerify('##########'),
            'qr_code_base64' => 'data:image/png;base64,'.base64_encode('fake-png'),
            'end_to_end_id' => null,
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PixChargeStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
