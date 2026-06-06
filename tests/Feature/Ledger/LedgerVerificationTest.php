<?php

namespace Tests\Feature\Ledger;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithEntry(): array
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();
        app(WalletService::class)->deposit($user, 5_000);
        $entry = LedgerEntry::where('user_id', $user->id)->sole();

        return [$user, $entry];
    }

    public function test_owner_verifies_own_entry_as_valid(): void
    {
        [$user, $entry] = $this->ownerWithEntry();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/ledger/{$entry->hash}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.type', 'deposit_credit')
            ->assertJsonPath('data.amount_cents', 5_000);
    }

    public function test_tampered_entry_verifies_as_invalid(): void
    {
        [$user, $entry] = $this->ownerWithEntry();

        $entry->amount_cents = 999_999;
        $entry->saveQuietly();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/ledger/{$entry->hash}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_stranger_cannot_verify_others_entry_returns_404(): void
    {
        [, $entry] = $this->ownerWithEntry();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/ledger/{$entry->hash}/verify")
            ->assertNotFound();
    }

    public function test_order_counterparty_can_verify_order_entry(): void
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();
        $listing = \App\Models\Listing::factory()->for($seller, 'seller')->create([
            'price_cents' => 10_000,
            'status' => \App\Enums\ListingStatus::Active,
        ]);

        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])->json('data.id');
        $this->actingAs($seller, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-delivery");
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt");

        $credit = LedgerEntry::where('user_id', $seller->id)->sole();

        // Comprador NÃO é dono da wallet do vendedor, mas é parte da order.
        $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/ledger/{$credit->hash}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', true);
    }

    public function test_verify_requires_auth(): void
    {
        [, $entry] = $this->ownerWithEntry();
        $this->getJson("/api/ledger/{$entry->hash}/verify")->assertUnauthorized();
    }
}
