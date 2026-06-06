<?php

namespace Tests\Feature\Marketplace;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'game' => 'Tibia',
            'type' => 'gold',
            'title' => '100kk Tibia Coins',
            'description' => 'Entrega rápida',
            'quantity' => 100,
            'price_cents' => 5_000,
            'photo' => UploadedFile::fake()->image('p.jpg', 800, 600),
        ], $overrides);
    }

    public function test_anyone_can_browse_active_listings(): void
    {
        Listing::factory()->count(3)->create();

        $this->getJson('/api/listings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'game', 'type', 'title', 'price_cents', 'status', 'photo_url', 'seller' => ['id', 'name']]],
                'current_page',
                'total',
            ]);
    }

    public function test_authenticated_user_can_create_listing_with_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/listings', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.title', '100kk Tibia Coins')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.seller.id', $user->id)
            ->assertJsonPath('data.photo_url', "/api/listings/1/photo");

        $listing = Listing::first();
        $this->assertNotNull($listing->photo_path);
        Storage::disk('local')->assertExists($listing->photo_path);
    }

    public function test_listing_requires_a_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/listings', $this->validPayload(['photo' => null]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_listing_rejects_non_image_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/listings', $this->validPayload([
                'photo' => UploadedFile::fake()->create('malware.pdf', 200, 'application/pdf'),
            ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_listing_creation_requires_authentication(): void
    {
        $this->postJson('/api/listings', ['game' => 'x', 'type' => 'item', 'title' => 'y', 'quantity' => 1, 'price_cents' => 1])
            ->assertUnauthorized();
    }

    public function test_listing_validates_type_enum(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/listings', $this->validPayload(['type' => 'invalid-type']), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_owner_can_update_listing(): void
    {
        $owner = User::factory()->create();
        $listing = Listing::factory()->for($owner, 'seller')->create(['title' => 'antigo', 'price_cents' => 1000]);

        $this->actingAs($owner, 'sanctum')
            ->put("/api/listings/{$listing->id}", [
                'game' => 'WoW',
                'type' => 'item',
                'title' => 'novo título',
                'quantity' => 2,
                'price_cents' => 9_999,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.title', 'novo título')
            ->assertJsonPath('data.price_cents', 9_999);
    }

    public function test_non_owner_cannot_update_listing_returns_404(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = Listing::factory()->for($owner, 'seller')->create();

        $this->actingAs($stranger, 'sanctum')
            ->put("/api/listings/{$listing->id}", [
                'game' => 'x', 'type' => 'item', 'title' => 'hack', 'quantity' => 1, 'price_cents' => 1,
            ], ['Accept' => 'application/json'])
            ->assertNotFound();
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

    public function test_photo_endpoint_streams_image(): void
    {
        Storage::fake('local');
        $listing = Listing::factory()->create(['photo_path' => 'listings/sample.webp']);
        Storage::disk('local')->put('listings/sample.webp', 'fake-webp-bytes');

        $this->get("/api/listings/{$listing->id}/photo")->assertOk();
    }

    public function test_photo_endpoint_404_without_photo(): void
    {
        $listing = Listing::factory()->create(['photo_path' => null]);

        $this->get("/api/listings/{$listing->id}/photo")->assertNotFound();
    }
}
