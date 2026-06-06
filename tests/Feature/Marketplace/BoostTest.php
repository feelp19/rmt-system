<?php

namespace Tests\Feature\Marketplace;

use App\Enums\BoostTier;
use App\Enums\ListingStatus;
use App\Models\Boost;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoostTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithBalance(int $cents): User
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->withBalance($cents)->create();

        return $seller;
    }

    public function test_owner_boosts_own_listing_with_wallet(): void
    {
        $seller = $this->sellerWithBalance(100_000);
        $listing = Listing::factory()->for($seller, 'seller')->create(['status' => ListingStatus::Active]);

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'intermediate', 'payment_method' => 'wallet'])
            ->assertCreated()
            ->assertJsonPath('data.tier', 'intermediate')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.price_cents', 1500);

        // Carteira debitada e badge no anúncio.
        $this->assertSame(98_500, Wallet::where('user_id', $seller->id)->value('balance_cents'));
        $this->getJson("/api/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.boost.tier', 'intermediate');
    }

    public function test_boost_fails_with_insufficient_balance(): void
    {
        $seller = $this->sellerWithBalance(100);
        $listing = Listing::factory()->for($seller, 'seller')->create();

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'basic', 'payment_method' => 'wallet'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Saldo insuficiente para o boost.']);

        $this->assertDatabaseCount('boosts', 0);
        $this->assertSame(100, Wallet::where('user_id', $seller->id)->value('balance_cents'));
    }

    public function test_non_owner_cannot_boost_returns_404(): void
    {
        $owner = $this->sellerWithBalance(100_000);
        $stranger = $this->sellerWithBalance(100_000);
        $listing = Listing::factory()->for($owner, 'seller')->create();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'basic', 'payment_method' => 'wallet'])
            ->assertNotFound();

        $this->assertDatabaseCount('boosts', 0);
    }

    public function test_cannot_boost_already_boosted_listing(): void
    {
        $seller = $this->sellerWithBalance(100_000);
        $listing = Listing::factory()->for($seller, 'seller')->create();

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'basic', 'payment_method' => 'wallet'])
            ->assertCreated();

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'advanced', 'payment_method' => 'wallet'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Este anúncio já está turbinado.']);

        $this->assertDatabaseCount('boosts', 1);
    }

    public function test_pix_payment_rejected_in_increment_1(): void
    {
        $seller = $this->sellerWithBalance(100_000);
        $listing = Listing::factory()->for($seller, 'seller')->create();

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'basic', 'payment_method' => 'pix'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_boost_requires_authentication(): void
    {
        $listing = Listing::factory()->create();

        $this->postJson("/api/listings/{$listing->id}/boosts", ['tier' => 'basic', 'payment_method' => 'wallet'])
            ->assertUnauthorized();
    }

    public function test_featured_returns_boosted_ordered_by_tier(): void
    {
        $this->boostedListing(BoostTier::Basic);
        $this->boostedListing(BoostTier::Advanced);
        $this->boostedListing(BoostTier::Intermediate);
        Listing::factory()->create(); // não-boostado: não aparece no destaque

        $this->getJson('/api/listings/featured')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.boost.tier', 'advanced')
            ->assertJsonPath('data.1.boost.tier', 'intermediate')
            ->assertJsonPath('data.2.boost.tier', 'basic');
    }

    public function test_grid_floats_intermediate_and_advanced_to_top(): void
    {
        $advanced = $this->boostedListing(BoostTier::Advanced);
        $intermediate = $this->boostedListing(BoostTier::Intermediate);
        $this->boostedListing(BoostTier::Basic); // básico NÃO flutua no grid
        Listing::factory()->create();

        $response = $this->getJson('/api/listings')->assertOk();

        // Os dois primeiros são avançado e intermediário (nessa ordem).
        $response->assertJsonPath('data.0.id', $advanced->id);
        $response->assertJsonPath('data.1.id', $intermediate->id);
    }

    private function boostedListing(BoostTier $tier): Listing
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::Active]);
        Boost::factory()->tier($tier)->for($listing)->create(['user_id' => $listing->seller_id]);

        return $listing;
    }
}
