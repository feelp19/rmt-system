<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscrowFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fundedBuyer(int $cents): User
    {
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance($cents)->create();

        return $buyer;
    }

    private function seller(): User
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();

        return $seller;
    }

    public function test_escrow_holds_funds_then_releases_to_seller_after_both_confirm(): void
    {
        $seller = $this->seller();
        $buyer = $this->fundedBuyer(100_000);

        $listing = Listing::factory()->for($seller, 'seller')->create([
            'price_cents' => 10_000,
            'status' => ListingStatus::Active,
        ]);

        // Compra → debita comprador, retém no escrow, marca anúncio vendido.
        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_confirmation')
            ->assertJsonPath('data.amount_cents', 10_000)
            ->assertJsonPath('data.fee_cents', 500)
            ->assertJsonPath('data.seller_payout_cents', 9_500)
            ->json('data.id');

        $this->assertSame(90_000, Wallet::where('user_id', $buyer->id)->value('balance_cents'));
        $this->assertSame(0, Wallet::where('user_id', $seller->id)->value('balance_cents'));
        $this->assertSame('sold', Listing::find($listing->id)->status->value);

        // Só o vendedor confirmou → ainda não libera.
        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/orders/{$orderId}/confirm-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_confirmation');
        $this->assertSame(0, Wallet::where('user_id', $seller->id)->value('balance_cents'));

        // Comprador confirma → libera escrow (valor - taxa 5%).
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/orders/{$orderId}/confirm-receipt")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(9_500, Wallet::where('user_id', $seller->id)->value('balance_cents'));
    }

    public function test_buyer_cannot_purchase_own_listing(): void
    {
        // A trava de "anúncio próprio" acontece antes de qualquer débito.
        $seller = $this->seller();
        $listing = Listing::factory()->for($seller, 'seller')->create(['price_cents' => 1_000]);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertStatus(422);
    }

    public function test_purchase_fails_with_insufficient_balance(): void
    {
        $seller = $this->seller();
        $buyer = $this->fundedBuyer(100); // 1 real só
        $listing = Listing::factory()->for($seller, 'seller')->create(['price_cents' => 10_000]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Saldo insuficiente.']);

        $this->assertSame(100, Wallet::where('user_id', $buyer->id)->value('balance_cents'));
        $this->assertSame('active', Listing::find($listing->id)->status->value);
    }

    public function test_stranger_cannot_view_order_returns_404(): void
    {
        $order = $this->createPaidOrder();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/orders/{$order['id']}")
            ->assertNotFound();
    }

    public function test_buyer_cannot_confirm_delivery_returns_403(): void
    {
        $order = $this->createPaidOrder();

        // O comprador é parte do pedido (não é 404), mas papel errado → 403.
        $this->actingAs($order['buyer'], 'sanctum')
            ->postJson("/api/orders/{$order['id']}/confirm-delivery")
            ->assertForbidden();
    }

    public function test_double_confirm_is_idempotent(): void
    {
        $order = $this->createPaidOrder();

        $this->actingAs($order['seller'], 'sanctum')
            ->postJson("/api/orders/{$order['id']}/confirm-delivery")->assertOk();
        // Confirmar de novo não muda nada nem libera.
        $this->actingAs($order['seller'], 'sanctum')
            ->postJson("/api/orders/{$order['id']}/confirm-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_confirmation');

        $this->assertSame(0, Wallet::where('user_id', $order['seller']->id)->value('balance_cents'));
    }

    public function test_purchase_requires_authentication(): void
    {
        $listing = Listing::factory()->for($this->seller(), 'seller')->create();

        $this->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertUnauthorized();
    }

    /** @return array{id:int, buyer:User, seller:User} */
    private function createPaidOrder(): array
    {
        $seller = $this->seller();
        $buyer = $this->fundedBuyer(100_000);
        $listing = Listing::factory()->for($seller, 'seller')->create(['price_cents' => 10_000]);

        $id = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertCreated()
            ->json('data.id');

        return ['id' => $id, 'buyer' => $buyer, 'seller' => $seller];
    }
}
