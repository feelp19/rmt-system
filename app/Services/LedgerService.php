<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use RuntimeException;

/**
 * Registro append-only de movimentos de saldo com HMAC encadeado por carteira.
 *
 * Stateless / Octane-safe: lê a chave de config a cada chamada, sem estado em
 * propriedade. `record` DEVE ser chamado dentro da transação do caller, com a
 * wallet já travada (lockForUpdate) — a cadeia depende disso para ser determinística.
 */
class LedgerService
{
    /**
     * Apenda uma linha na cadeia da carteira e atualiza a cabeça (head).
     *
     * Pré-condição: $lockedWallet já travada e dentro de uma transação aberta.
     */
    public function record(
        Wallet $lockedWallet,
        LedgerEntryType $type,
        LedgerDirection $direction,
        int $amountCents,
        int $balanceAfterCents,
        ?string $referenceType,
        ?int $referenceId,
    ): LedgerEntry {
        $prevHash = $lockedWallet->ledger_head_hash;
        $seq = (int) $lockedWallet->ledger_seq + 1;

        $hash = $this->hash(
            $lockedWallet->id,
            $seq,
            $type->value,
            $direction->value,
            $amountCents,
            $balanceAfterCents,
            $referenceType,
            $referenceId,
            $prevHash,
        );

        $entry = LedgerEntry::create([
            'wallet_id' => $lockedWallet->id,
            'user_id' => $lockedWallet->user_id,
            'type' => $type,
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $balanceAfterCents,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'seq' => $seq,
            'prev_hash' => $prevHash,
            'hash' => $hash,
        ]);

        $lockedWallet->ledger_head_hash = $hash;
        $lockedWallet->ledger_seq = $seq;
        $lockedWallet->save();

        return $entry;
    }

    /** A assinatura HMAC da linha confere com os campos armazenados? */
    public function signatureValid(LedgerEntry $entry): bool
    {
        $expected = $this->hash(
            $entry->wallet_id,
            (int) $entry->seq,
            $entry->type->value,
            $entry->direction->value,
            (int) $entry->amount_cents,
            (int) $entry->balance_after_cents,
            $entry->reference_type,
            $entry->reference_id !== null ? (int) $entry->reference_id : null,
            $entry->prev_hash,
        );

        return hash_equals($expected, (string) $entry->hash);
    }

    /**
     * Linha íntegra E ligada corretamente à antecessora da mesma carteira.
     * Genesis (seq=1) exige prev_hash null; demais exigem prev_hash == hash da seq-1.
     */
    public function verifyEntry(LedgerEntry $entry): bool
    {
        if (! $this->signatureValid($entry)) {
            return false;
        }

        if ((int) $entry->seq === 1) {
            return $entry->prev_hash === null;
        }

        $predecessor = LedgerEntry::where('wallet_id', $entry->wallet_id)
            ->where('seq', (int) $entry->seq - 1)
            ->first();

        return $predecessor !== null
            && $entry->prev_hash !== null
            && hash_equals((string) $predecessor->hash, (string) $entry->prev_hash);
    }

    /** Monta o payload canônico (ordem fixa, só campos determinísticos) e devolve o HMAC. */
    private function hash(
        int $walletId,
        int $seq,
        string $type,
        string $direction,
        int $amountCents,
        int $balanceAfterCents,
        ?string $referenceType,
        ?int $referenceId,
        ?string $prevHash,
    ): string {
        $canonical = json_encode([
            'wallet_id' => $walletId,
            'seq' => $seq,
            'type' => $type,
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $balanceAfterCents,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'prev_hash' => $prevHash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $canonical, $this->key());
    }

    /** Chave HMAC — fail-closed se ausente (nunca grava ledger sem assinatura válida). */
    private function key(): string
    {
        $key = (string) config('ledger.hmac_key');

        if ($key === '') {
            throw new RuntimeException('LEDGER_HMAC_KEY não configurada — ledger não pode ser assinado.');
        }

        return $key;
    }
}
