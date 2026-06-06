<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Endpoint é cacheado; cache do array store persiste entre métodos no
        // mesmo processo. Flush garante teste determinístico.
        Cache::flush();
    }

    private function completedSale(array $listing = [], ?User $seller = null): Order
    {
        $seller ??= User::factory()->create();
        $model = Listing::factory()->for($seller, 'seller')->create($listing);

        return Order::factory()->create([
            'listing_id' => $model->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function test_activity_feed_is_public_and_lists_completed_sales(): void
    {
        $this->completedSale(
            ['type' => 'gold', 'game' => 'WoW Retail', 'title' => '500k Gold'],
            User::factory()->create(['name' => 'Vendedor X']),
        );

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['type', 'game', 'title', 'amount_cents', 'completed_at', 'seller_name']],
                'in_escrow_count',
            ])
            ->assertJsonPath('data.0.title', '500k Gold')
            ->assertJsonPath('data.0.seller_name', 'Vendedor X');
    }

    public function test_activity_feed_excludes_non_completed_orders(): void
    {
        Order::factory()->create(['status' => OrderStatus::AwaitingConfirmation]);
        Order::factory()->create(['status' => OrderStatus::Cancelled]);

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_activity_feed_never_exposes_buyer_data(): void
    {
        $this->completedSale();

        $item = $this->getJson('/api/activity')->assertOk()->json('data.0');

        $this->assertEqualsCanonicalizing(
            ['type', 'game', 'title', 'amount_cents', 'completed_at', 'seller_name'],
            array_keys($item),
        );
    }

    public function test_activity_feed_counts_orders_in_escrow(): void
    {
        Order::factory()->count(3)->create(['status' => OrderStatus::AwaitingConfirmation]);
        $this->completedSale();

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonPath('in_escrow_count', 3);
    }

    public function test_activity_feed_limits_to_eight_most_recent(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->completedSale();
        }

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }
}
