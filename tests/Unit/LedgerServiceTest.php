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

    public function test_verify_entry_true_for_valid_genesis(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);
        $entry = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);

        $this->assertTrue($service->verifyEntry($entry->fresh()));
    }

    public function test_verify_entry_false_when_genesis_has_prev_hash(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);
        $entry = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);

        // Genesis forjada com prev_hash não-nulo → quebra o invariante (e a assinatura).
        $entry->prev_hash = str_repeat('a', 64);
        $entry->saveQuietly();

        $this->assertFalse($service->verifyEntry($entry->fresh()));
    }

    public function test_verify_entry_true_for_valid_chained_entry(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);
        $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);
        $second = $service->record($wallet, LedgerEntryType::BoostDebit, LedgerDirection::Debit, 2_000, 3_000, 'boost', 7);

        $this->assertTrue($service->verifyEntry($second->fresh()));
    }

    public function test_verify_entry_false_when_predecessor_missing(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);
        $first = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);
        $second = $service->record($wallet, LedgerEntryType::BoostDebit, LedgerDirection::Debit, 2_000, 3_000, 'boost', 7);

        // Apaga a antecessora sem tocar na 'second': a assinatura própria de 'second'
        // continua válida, mas o elo da cadeia quebra (predecessor sumiu).
        $first->delete();

        $this->assertTrue($service->signatureValid($second->fresh()));
        $this->assertFalse($service->verifyEntry($second->fresh()));
    }

    public function test_verify_entry_false_when_predecessor_hash_altered(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);
        $first = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);
        $second = $service->record($wallet, LedgerEntryType::BoostDebit, LedgerDirection::Debit, 2_000, 3_000, 'boost', 7);

        // Adultera o hash da antecessora: a 'second' continua com assinatura válida,
        // mas seu prev_hash não bate mais com a antecessora → elo quebrado.
        $first->hash = str_repeat('b', 64);
        $first->saveQuietly();

        $this->assertFalse($service->verifyEntry($second->fresh()));
    }
}
