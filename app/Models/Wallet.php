<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ledger_head_hash / ledger_seq NÃO entram no fillable de propósito: são campos de
// integridade da cadeia, escritos só pelo LedgerService via atribuição direta + save()
// (mass-assignment defeitaria a âncora anti-truncamento). Mesmo padrão do User.xp.
#[Fillable(['user_id', 'balance_cents'])]
class Wallet extends Model
{
    /** @use HasFactory<\Database\Factories\WalletFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'ledger_seq' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
