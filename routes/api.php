<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Marketplace\BoostController;
use App\Http\Controllers\Marketplace\ListingController;
use App\Http\Controllers\Marketplace\OrderController;
use App\Http\Controllers\Wallet\PixChargeController;
use App\Http\Controllers\Wallet\WalletController;
use App\Http\Controllers\Webhooks\PushinPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

// ─── Auth público (rate-limited) ───────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Webhook PushinPay (sem auth; autenticidade pelo secret na URL + re-verificação no job).
Route::post('/webhooks/pushinpay/{token}', [PushinPayWebhookController::class, 'handle'])->middleware('throttle:120,1');

// ─── Vitrine pública (browse de anúncios ativos) ──────────────────────────
Route::get('/listings', [ListingController::class, 'index']);
Route::get('/listings/featured', [ListingController::class, 'featured']); // faixa "Em destaque" (boostados)
Route::get('/listings/{listing}', [ListingController::class, 'show'])->whereNumber('listing');

// ─── Área autenticada ─────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/deposit', [WalletController::class, 'deposit'])->middleware('throttle:30,1');
    // Carregar saldo via PIX (PushinPay): cria a cobrança + polling do status.
    Route::post('/wallet/pix', [PixChargeController::class, 'store'])->middleware('throttle:20,1');
    // Polling do status (3s no front) — rate limit pra não floodar o servidor.
    Route::get('/wallet/pix/{pixCharge}', [PixChargeController::class, 'show'])->whereNumber('pixCharge')->middleware('throttle:60,1');

    Route::post('/listings', [ListingController::class, 'store']);
    Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->whereNumber('listing');
    // Turbinar anúncio próprio (boost pago — Inc 1: carteira).
    Route::post('/listings/{listing}/boosts', [BoostController::class, 'store'])->whereNumber('listing')->middleware('throttle:30,1');

    Route::get('/orders', [OrderController::class, 'index']);
    // Mutações financeiras: rate-limited (defesa contra abuso/flood).
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::post('/orders/{order}/confirm-delivery', [OrderController::class, 'confirmDelivery'])->whereNumber('order')->middleware('throttle:30,1');
    Route::post('/orders/{order}/confirm-receipt', [OrderController::class, 'confirmReceipt'])->whereNumber('order')->middleware('throttle:30,1');
});
