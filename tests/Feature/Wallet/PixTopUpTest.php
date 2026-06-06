<?php

namespace Tests\Feature\Wallet;

use App\Jobs\ProcessPushinPayWebhookJob;
use App\Models\PixCharge;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PixChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PixTopUpTest extends TestCase
{
    use RefreshDatabase;

    private function fakeCashIn(string $id = 'ABC-123-DEF', int $value = 5000): void
    {
        Http::fake([
            '*/api/pix/cashIn' => Http::response([
                'id' => $id,
                'value' => $value,
                'status' => 'created',
                'qr_code' => '00020126850014br.gov.bcb.pix...6304ABCD',
                'qr_code_base64' => 'data:image/png;base64,iVBORw0KGgo=',
            ], 200),
        ]);
    }

    public function test_create_pix_charge_returns_qr(): void
    {
        $this->fakeCashIn();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/pix', ['amount_cents' => 5000])
            ->assertCreated()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.amount_cents', 5000)
            ->assertJsonStructure(['data' => ['id', 'qr_code', 'qr_code_base64', 'expires_at']]);

        $this->assertDatabaseHas('pix_charges', [
            'user_id' => $user->id,
            'pushinpay_id' => 'abc-123-def', // normalizado lowercase
            'amount_cents' => 5000,
            'status' => 'created',
        ]);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/wallet/pix', ['amount_cents' => 5000])->assertUnauthorized();
    }

    public function test_amount_below_minimum_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/pix', ['amount_cents' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_cents']);
    }

    public function test_gateway_failure_returns_503(): void
    {
        Http::fake(['*/api/pix/cashIn' => Http::response('', 500)]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/pix', ['amount_cents' => 5000])
            ->assertStatus(503);
    }

    public function test_show_is_scoped_to_owner_returns_404(): void
    {
        $charge = PixCharge::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/wallet/pix/{$charge->id}")
            ->assertNotFound();
    }

    public function test_polling_confirms_paid_and_credits_wallet(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(); // saldo 0
        $charge = PixCharge::factory()->for($user)->create([
            'pushinpay_id' => 'abc-123-def',
            'amount_cents' => 5000,
        ]);

        // Gateway responde "paid" na consulta autoritativa.
        Http::fake([
            '*/api/transactions/*' => Http::response([
                'id' => 'ABC-123-DEF',
                'status' => 'paid',
                'value' => '5000',
                'end_to_end_id' => 'E2E-XYZ',
            ], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/wallet/pix/{$charge->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame(5000, Wallet::where('user_id', $user->id)->value('balance_cents'));
    }

    public function test_confirm_paid_is_idempotent(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();
        $charge = PixCharge::factory()->for($user)->create(['amount_cents' => 5000]);

        $service = app(PixChargeService::class);
        $service->confirmPaid($charge->fresh());
        $service->confirmPaid($charge->fresh()); // 2ª vez não credita de novo

        $this->assertSame(5000, Wallet::where('user_id', $user->id)->value('balance_cents'));
    }

    public function test_webhook_with_invalid_token_returns_404(): void
    {
        $this->postJson('/api/webhooks/pushinpay/wrong-secret', ['id' => 'abc-123'])
            ->assertNotFound();
    }

    public function test_webhook_with_valid_token_dispatches_job(): void
    {
        Queue::fake();
        $token = config('services.pushinpay.webhook_secret');

        $this->postJson("/api/webhooks/pushinpay/{$token}", ['id' => 'abc-123-def'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Queue::assertPushed(ProcessPushinPayWebhookJob::class, function ($job) {
            return $job->pushinpayId === 'abc-123-def';
        });
    }
}
