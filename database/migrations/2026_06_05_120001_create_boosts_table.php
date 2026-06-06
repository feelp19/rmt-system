<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // anunciante (dono do anúncio)
            $table->string('tier', 20);                 // enum App\Enums\BoostTier
            $table->unsignedTinyInteger('weight');      // denormalizado do tier p/ ordenação
            $table->bigInteger('price_cents');
            $table->string('payment_method', 12);       // enum App\Enums\BoostPaymentMethod
            $table->string('status', 20)->default('active'); // enum App\Enums\BoostStatus
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boosts');
    }
};
