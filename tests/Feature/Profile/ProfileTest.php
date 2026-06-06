<?php

namespace Tests\Feature\Profile;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_returns_user_and_stats(): void
    {
        $user = User::factory()->create();
        Listing::factory()->count(2)->for($user, 'seller')->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'xp', 'level', 'next_level_xp', 'avatar_url'],
                'stats' => ['active_listings', 'sales', 'purchases'],
            ])
            ->assertJsonPath('stats.active_listings', 2);
    }

    public function test_my_listings_returns_only_own(): void
    {
        $user = User::factory()->create();
        Listing::factory()->count(3)->for($user, 'seller')->create();
        Listing::factory()->count(2)->create(); // de outros

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/listings')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_avatar_upload_and_serve(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('a.png', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('user.avatar_url', "/api/users/{$user->id}/avatar");

        $this->assertNotNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertExists($user->fresh()->avatar_path);

        $this->get("/api/users/{$user->id}/avatar")->assertOk();
    }

    public function test_avatar_rejects_non_image_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/me/avatar', [
                'avatar' => UploadedFile::fake()->create('malware.pdf', 200, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_404_when_none(): void
    {
        $user = User::factory()->create();

        $this->get("/api/users/{$user->id}/avatar")->assertNotFound();
    }

    public function test_avatar_requires_authentication(): void
    {
        $this->postJson('/api/me/avatar')->assertUnauthorized();
    }

    public function test_leaderboard_ordered_by_xp(): void
    {
        $low = User::factory()->create(['xp' => 200]);
        $high = User::factory()->create(['xp' => 2_000]);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertJsonPath('data.0.id', $high->id)
            ->assertJsonPath('data.0.level', 6)
            ->assertJsonPath('data.1.id', $low->id);
    }
}
