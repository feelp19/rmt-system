<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_appends_signed_credit_entry(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();

        app(WalletService::class)->deposit($user, 7_500);

        $entry = LedgerEntry::where('user_id', $user->id)->sole();
        $this->assertSame(LedgerEntryType::DepositCredit, $entry->type);
        $this->assertSame(7_500, $entry->amount_cents);
        $this->assertSame(7_500, $entry->balance_after_cents);
        $this->assertSame('deposit', $entry->reference_type);
        $this->assertSame(1, $entry->seq);
        $this->assertTrue(app(LedgerService::class)->signatureValid($entry));
    }
}
