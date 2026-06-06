<?php

namespace App\Services;

use App\Exceptions\PushinPayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da PushinPay (gateway PIX). Host fixo (sem SSRF). Octane-safe:
 * lê credenciais do config a cada chamada — sem estado mutável em propriedade.
 */
class PushinPayService
{
    /** Cria uma cobrança PIX. `value` é em centavos inteiros. */
    public function createPix(int $amountCents, string $webhookUrl): array
    {
        $response = Http::withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->post($this->baseUrl().'/api/pix/cashIn', [
                'value' => $amountCents,
                'webhook_url' => $webhookUrl,
            ]);

        if ($response->failed()) {
            // Loga só status — nunca o payload financeiro completo (rmt-security).
            Log::warning('pushinpay.create_failed', ['status' => $response->status()]);
            throw new PushinPayException('Não foi possível gerar o PIX agora.');
        }

        return $response->json();
    }

    /** Consulta autoritativa do status de uma transação por id. */
    public function getTransaction(string $id): array
    {
        $response = Http::withToken($this->token())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->get($this->baseUrl().'/api/transactions/'.$id);

        if ($response->failed()) {
            Log::warning('pushinpay.consult_failed', ['status' => $response->status(), 'id' => $id]);
            throw new PushinPayException('Não foi possível consultar o PIX.');
        }

        return $response->json();
    }

    private function token(): string
    {
        return (string) config('services.pushinpay.token');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.pushinpay.base_url'), '/');
    }
}
