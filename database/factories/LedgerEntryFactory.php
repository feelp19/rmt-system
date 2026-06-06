<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'user_id' => User::factory(),
            'type' => LedgerEntryType::DepositCredit,
            'direction' => LedgerDirection::Credit,
            'amount_cents' => 1000,
            'balance_after_cents' => 1000,
            'reference_type' => 'deposit',
            'reference_id' => null,
            'seq' => 1,
            'prev_hash' => null,
            'hash' => str_repeat('0', 64),
        ];
    }
}
