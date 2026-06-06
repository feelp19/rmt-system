<?php

namespace Tests\Feature\Ledger;

use App\Enums\BoostTier;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BoostService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoostLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_boost_purchase_appends_signed_debit_entry(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->withBalance(100_000)->create();
        $listing = Listing::factory()->for($user, 'seller')->create();

        $tier = BoostTier::Basic;
        $boost = app(BoostService::class)->purchaseWithWallet($user, $listing, $tier);

        $entry = LedgerEntry::where('user_id', $user->id)->sole();
        $this->assertSame(LedgerEntryType::BoostDebit, $entry->type);
        $this->assertSame($tier->priceCents(), $entry->amount_cents);
        $this->assertSame('boost', $entry->reference_type);
        $this->assertSame($boost->id, $entry->reference_id);
        $this->assertSame(100_000 - $tier->priceCents(), $entry->balance_after_cents);
        $this->assertTrue(app(LedgerService::class)->signatureValid($entry));
    }
}
