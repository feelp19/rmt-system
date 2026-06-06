<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_appends_signed_credit_entry(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();

        app(WalletService::class)->deposit($user, 7_500);

        $entry = LedgerEntry::where('user_id', $user->id)->sole();
        $this->assertSame(LedgerEntryType::DepositCredit, $entry->type);
        $this->assertSame(7_500, $entry->amount_cents);
        $this->assertSame(7_500, $entry->balance_after_cents);
        $this->assertSame('deposit', $entry->reference_type);
        $this->assertSame(1, $entry->seq);
        $this->assertTrue(app(LedgerService::class)->signatureValid($entry));
    }

    public function test_wallet_ledger_lists_only_own_entries_paginated(): void
    {
        $me = User::factory()->create();
        Wallet::factory()->for($me)->create();
        $other = User::factory()->create();
        Wallet::factory()->for($other)->create();

        app(WalletService::class)->deposit($me, 5_000);
        app(WalletService::class)->deposit($me, 3_000);
        app(WalletService::class)->deposit($other, 9_000);

        $this->actingAs($me, 'sanctum')
            ->getJson('/api/wallet/ledger')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'deposit_credit')
            ->assertJsonPath('data.0.code', fn ($code) => is_string($code) && strlen($code) === 64);
    }

    public function test_wallet_ledger_requires_auth(): void
    {
        $this->getJson('/api/wallet/ledger')->assertUnauthorized();
    }
}
