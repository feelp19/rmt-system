<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerEntryType;
use App\Enums\ListingStatus;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_and_release_each_append_one_signed_entry(): void
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();

        $listing = Listing::factory()->for($seller, 'seller')->create([
            'price_cents' => 10_000,
            'status' => ListingStatus::Active,
        ]);

        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertCreated()
            ->json('data.id');

        // Débito do comprador no ato da compra.
        $debit = LedgerEntry::where('user_id', $buyer->id)->sole();
        $this->assertSame(LedgerEntryType::EscrowDebit, $debit->type);
        $this->assertSame(10_000, $debit->amount_cents);
        $this->assertSame(90_000, $debit->balance_after_cents);
        $this->assertSame('order', $debit->reference_type);
        $this->assertSame($orderId, $debit->reference_id);

        // Dupla confirmação → libera; crédito do vendedor (valor - taxa).
        $this->actingAs($seller, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-delivery")->assertOk();
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt")->assertOk();

        $credit = LedgerEntry::where('user_id', $seller->id)->sole();
        $this->assertSame(LedgerEntryType::EscrowReleaseCredit, $credit->type);
        $this->assertSame(9_500, $credit->amount_cents);
        $this->assertSame(9_500, $credit->balance_after_cents);

        $ledger = app(LedgerService::class);
        $this->assertTrue($ledger->signatureValid($debit));
        $this->assertTrue($ledger->signatureValid($credit));
    }

    public function test_double_confirm_does_not_double_credit_ledger(): void
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();
        $listing = Listing::factory()->for($seller, 'seller')->create(['price_cents' => 10_000, 'status' => ListingStatus::Active]);

        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])->json('data.id');

        $this->actingAs($seller, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-delivery")->assertOk();
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt")->assertOk();
        // Reconfirma — deve ser no-op, sem segunda linha de crédito.
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt")->assertOk();

        $this->assertSame(1, LedgerEntry::where('user_id', $seller->id)->count());
    }
}
