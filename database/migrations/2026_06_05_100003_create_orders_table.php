<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            // Valores travados no momento da compra (centavos inteiros).
            $table->bigInteger('amount_cents');          // valor escrowed (preço do anúncio)
            $table->bigInteger('fee_cents');             // taxa da plataforma (5%)
            $table->bigInteger('seller_payout_cents');   // amount - fee (o que o vendedor recebe)

            $table->string('status', 32)->default('awaiting_confirmation'); // enum App\Enums\OrderStatus

            // Dupla confirmação: ambos precisam dar "sim" para liberar o escrow.
            $table->timestamp('seller_confirmed_at')->nullable(); // vendedor: entreguei
            $table->timestamp('buyer_confirmed_at')->nullable();  // comprador: recebi
            $table->timestamp('completed_at')->nullable();        // momento da liberação

            $table->timestamps();

            $table->index('buyer_id');
            $table->index('seller_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
