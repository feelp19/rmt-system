<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // Âncora anti-truncamento: cabeça da cadeia de ledger desta carteira.
            $table->char('ledger_head_hash', 64)->nullable()->after('balance_cents');
            $table->unsignedBigInteger('ledger_seq')->default(0)->after('ledger_head_hash');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['ledger_head_hash', 'ledger_seq']);
        });
    }
};
