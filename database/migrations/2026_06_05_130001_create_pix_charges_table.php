<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pix_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // id da transação na PushinPay (normalizado em lowercase) — idempotência.
            $table->string('pushinpay_id')->unique();
            $table->bigInteger('amount_cents');
            $table->string('status', 20)->default('created'); // enum App\Enums\PixChargeStatus
            $table->text('qr_code');            // copia-e-cola (EMV BR Code)
            $table->longText('qr_code_base64'); // data:image/png;base64,...
            $table->string('end_to_end_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_charges');
    }
};
