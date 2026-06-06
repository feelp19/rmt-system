<?php

namespace Tests\Feature\Marketplace;

use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class XpTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_completion_awards_xp_to_both_sides(): void
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();
        $listing = Listing::factory()->for($seller, 'seller')->create(['price_cents' => 10_000]);

        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->json('data.id');

        $this->actingAs($seller, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-delivery");
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt");

        $this->assertSame(50, (int) $seller->fresh()->xp);
        $this->assertSame(20, (int) $buyer->fresh()->xp);
    }

    public function test_high_level_seller_pays_lower_fee(): void
    {
        $seller = User::factory()->create(['xp' => 4_500]); // nível 10 → 3.2%
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();
        $listing = Listing::factory()->for($seller, 'seller')->create(['price_cents' => 10_000]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertCreated()
            ->assertJsonPath('data.fee_cents', 320) // 3,2% de 10000
            ->assertJsonPath('data.seller_payout_cents', 9_680);
    }

    public function test_boost_awards_xp(): void
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->withBalance(100_000)->create();
        $listing = Listing::factory()->for($seller, 'seller')->create();

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'basic', 'payment_method' => 'wallet'])
            ->assertCreated();

        $this->assertSame(15, (int) $seller->fresh()->xp);
    }

    public function test_first_listing_awards_xp_only_once(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $payload = fn () => [
            'game' => 'Tibia', 'type' => 'gold', 'title' => 'x', 'quantity' => 1, 'price_cents' => 1000,
            'photo' => UploadedFile::fake()->image('p.jpg', 400, 400),
        ];

        $this->actingAs($user, 'sanctum')->post('/api/listings', $payload(), ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(10, (int) $user->fresh()->xp);

        $this->actingAs($user, 'sanctum')->post('/api/listings', $payload(), ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(10, (int) $user->fresh()->xp); // não ganha de novo
    }
}
