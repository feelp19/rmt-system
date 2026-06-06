<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 32);        // enum App\Enums\LedgerEntryType
            $table->string('direction', 6);    // enum App\Enums\LedgerDirection (credit|debit)
            $table->bigInteger('amount_cents');          // sempre positivo; sinal vem do direction
            $table->bigInteger('balance_after_cents');   // snapshot do saldo após o movimento

            $table->string('reference_type', 32)->nullable(); // order|pix_charge|boost|deposit
            $table->bigInteger('reference_id')->nullable();    // id da linha de origem

            $table->unsignedBigInteger('seq');         // sequência por carteira (1,2,3…)
            $table->char('prev_hash', 64)->nullable(); // hash da linha anterior desta wallet (null = genesis)
            $table->char('hash', 64);                  // HMAC-SHA256 hex — código de confiabilidade

            $table->timestamps();

            $table->unique(['wallet_id', 'seq']); // detecta gap na cadeia da carteira
            $table->unique('hash');               // lookup por código no endpoint de verificação
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
