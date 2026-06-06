<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('game');                       // qualquer jogo — texto livre
            $table->string('type', 16);                   // enum App\Enums\ListingType (string em PHP)
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('price_cents');            // preço total do anúncio em centavos
            $table->string('status', 16)->default('active'); // enum App\Enums\ListingStatus
            $table->timestamps();

            $table->index(['status', 'game']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
