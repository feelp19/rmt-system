<?php

namespace Tests\Feature\Ledger;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyLedgerCommandTest extends TestCase
{
    use RefreshDatabase;

    private function userWithChain(int $entries): User
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();
        $service = app(WalletService::class);
        for ($i = 0; $i < $entries; $i++) {
            $service->deposit($user, 1_000);
        }

        return $user;
    }

    public function test_clean_chain_passes(): void
    {
        $this->userWithChain(3);

        $this->artisan('ledger:verify')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    }

    public function test_tampered_entry_fails(): void
    {
        $user = $this->userWithChain(3);
        $entry = LedgerEntry::where('user_id', $user->id)->where('seq', 2)->sole();
        $entry->amount_cents = 999_999;
        $entry->saveQuietly();

        $this->artisan('ledger:verify')
            ->assertExitCode(1);
    }

    public function test_truncated_last_entry_is_detected_via_head_mismatch(): void
    {
        $user = $this->userWithChain(3);
        // Deleta a última linha sem ajustar a cabeça da wallet → head aponta p/ hash inexistente.
        LedgerEntry::where('user_id', $user->id)->where('seq', 3)->delete();

        $this->artisan('ledger:verify')
            ->assertExitCode(1);
    }

    public function test_wallet_with_no_entries_is_not_flagged(): void
    {
        // Carteira recém-criada, zero transações: ledger_seq=0, ledger_head_hash=null.
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();

        $this->artisan('ledger:verify')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    }
}
