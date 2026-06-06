<?php

namespace Tests\Feature\Marketplace;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_browse_active_listings(): void
    {
        Listing::factory()->count(3)->create();

        $this->getJson('/api/listings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'game', 'type', 'title', 'price_cents', 'status', 'seller' => ['id', 'name']]],
                'current_page',
                'total',
            ]);
    }

    public function test_authenticated_user_can_create_listing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/listings', [
                'game' => 'Tibia',
                'type' => 'gold',
                'title' => '100kk Tibia Coins',
                'description' => 'Entrega rápida',
                'quantity' => 100,
                'price_cents' => 5_000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', '100kk Tibia Coins')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.seller.id', $user->id);
    }

    public function test_listing_creation_requires_authentication(): void
    {
        $this->postJson('/api/listings', ['game' => 'x', 'type' => 'item', 'title' => 'y', 'quantity' => 1, 'price_cents' => 1])
            ->assertUnauthorized();
    }

    public function test_listing_validates_type_enum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/listings', [
                'game' => 'Tibia',
                'type' => 'invalid-type',
                'title' => 'x',
                'quantity' => 1,
                'price_cents' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_non_owner_cannot_cancel_listing_returns_404(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = Listing::factory()->for($owner, 'seller')->create();

        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/listings/{$listing->id}")
            ->assertNotFound();
    }

    public function test_owner_can_cancel_listing(): void
    {
        $owner = User::factory()->create();
        $listing = Listing::factory()->for($owner, 'seller')->create();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }
}
