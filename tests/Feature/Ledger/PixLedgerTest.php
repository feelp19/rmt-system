<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerEntryType;
use App\Enums\PixChargeStatus;
use App\Models\LedgerEntry;
use App\Models\PixCharge;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use App\Services\PixChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PixLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_paid_appends_signed_credit_entry(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();

        $charge = PixCharge::factory()->for($user)->create([
            'amount_cents' => 12_000,
            'status' => PixChargeStatus::Created,
        ]);

        app(PixChargeService::class)->confirmPaid($charge, 'E2E-TEST-123');

        $entry = LedgerEntry::where('user_id', $user->id)->sole();
        $this->assertSame(LedgerEntryType::PixTopupCredit, $entry->type);
        $this->assertSame(12_000, $entry->amount_cents);
        $this->assertSame(12_000, $entry->balance_after_cents);
        $this->assertSame('pix_charge', $entry->reference_type);
        $this->assertSame($charge->id, $entry->reference_id);
        $this->assertTrue(app(LedgerService::class)->signatureValid($entry));
    }

    public function test_confirm_paid_twice_does_not_double_append(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();
        $charge = PixCharge::factory()->for($user)->create(['amount_cents' => 12_000, 'status' => PixChargeStatus::Created]);

        $service = app(PixChargeService::class);
        $service->confirmPaid($charge, 'E2E-1');
        $service->confirmPaid($charge->fresh(), 'E2E-1'); // já paga → no-op

        $this->assertSame(1, LedgerEntry::where('user_id', $user->id)->count());
    }
}
