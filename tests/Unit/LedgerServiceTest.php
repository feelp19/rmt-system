<?php

namespace Tests\Unit;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function wallet(): Wallet
    {
        $user = User::factory()->create();

        return Wallet::factory()->for($user)->create();
    }

    public function test_record_creates_genesis_entry_and_updates_wallet_head(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);

        $entry = $service->record(
            $wallet,
            LedgerEntryType::DepositCredit,
            LedgerDirection::Credit,
            5_000,
            5_000,
            'deposit',
            null,
        );

        $this->assertSame(1, $entry->seq);
        $this->assertNull($entry->prev_hash);
        $this->assertSame(64, strlen($entry->hash));

        $wallet->refresh();
        $this->assertSame($entry->hash, $wallet->ledger_head_hash);
        $this->assertSame(1, $wallet->ledger_seq);
    }

    public function test_record_chains_second_entry_to_first(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);

        $first = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);
        $second = $service->record($wallet, LedgerEntryType::BoostDebit, LedgerDirection::Debit, 2_000, 3_000, 'boost', 7);

        $this->assertSame(2, $second->seq);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertNotSame($first->hash, $second->hash);

        $wallet->refresh();
        $this->assertSame($second->hash, $wallet->ledger_head_hash);
        $this->assertSame(2, $wallet->ledger_seq);
    }

    public function test_signature_valid_true_for_untampered_and_false_after_tamper(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);

        $entry = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);
        $this->assertTrue($service->signatureValid($entry->fresh()));

        // Adultera o valor direto no banco (sem recomputar o HMAC).
        $entry->amount_cents = 9_999;
        $entry->saveQuietly();

        $this->assertFalse($service->signatureValid($entry->fresh()));
    }

    public function test_missing_hmac_key_fails_closed(): void
    {
        config(['ledger.hmac_key' => '']);
        $wallet = $this->wallet();

        $this->expectException(\RuntimeException::class);
        app(LedgerService::class)->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 1, 1, 'deposit', null);
    }
}
