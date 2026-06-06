<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class VerifyLedgerCommand extends Command
{
    protected $signature = 'ledger:verify {--wallet= : Verificar só a carteira informada}';

    protected $description = 'Audita a integridade das cadeias de ledger (HMAC + elo + cabeça)';

    public function handle(LedgerService $ledger): int
    {
        $query = Wallet::query();
        if ($this->option('wallet') !== null) {
            $query->whereKey((int) $this->option('wallet'));
        }

        $broken = 0;

        foreach ($query->cursor() as $wallet) {
            $breakReason = $this->verifyWalletChain($wallet, $ledger);

            if ($breakReason !== null) {
                $broken++;
                $this->error("wallet {$wallet->id}: {$breakReason}");
            }
        }

        if ($broken > 0) {
            $this->error("FALHA: {$broken} carteira(s) com cadeia quebrada.");

            return self::FAILURE;
        }

        $this->info('OK: todas as cadeias de ledger estão íntegras.');

        return self::SUCCESS;
    }

    /** Retorna o motivo da primeira quebra, ou null se a cadeia está íntegra. */
    private function verifyWalletChain(Wallet $wallet, LedgerService $ledger): ?string
    {
        $entries = LedgerEntry::where('wallet_id', $wallet->id)->orderBy('seq')->get();

        $expectedSeq = 1;
        $prevHash = null;

        foreach ($entries as $entry) {
            if ((int) $entry->seq !== $expectedSeq) {
                return "gap de seq: esperado {$expectedSeq}, achou {$entry->seq}";
            }

            if (! $ledger->signatureValid($entry)) {
                return "HMAC inválido na seq {$entry->seq}";
            }

            if ($entry->prev_hash !== $prevHash) {
                return "elo quebrado na seq {$entry->seq}";
            }

            $prevHash = $entry->hash;
            $expectedSeq++;
        }

        // Cabeça: a última linha calculada precisa bater com a head persistida (detecta truncamento).
        $lastSeq = $expectedSeq - 1;
        if ((int) $wallet->ledger_seq !== $lastSeq) {
            return "cabeça desalinhada: wallet.ledger_seq={$wallet->ledger_seq}, cadeia termina em {$lastSeq}";
        }

        if (($wallet->ledger_head_hash ?? null) !== $prevHash) {
            return 'cabeça desalinhada: ledger_head_hash não bate com a última linha';
        }

        return null;
    }
}
