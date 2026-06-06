<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPushinPayWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushinPayWebhookController extends Controller
{
    /**
     * Webhook da PushinPay. O gateway NÃO assina o payload (sem HMAC), então:
     *  1. Autenticidade pela URL: secret na rota validado com hash_equals.
     *  2. Nunca confiamos no corpo — só pegamos o `id` e o job re-verifica o
     *     status pela API antes de creditar.
     */
    public function handle(Request $request, string $token): JsonResponse
    {
        $expected = (string) config('services.pushinpay.webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $token), 404);

        $id = $request->input('id');

        if (is_string($id) && $id !== '') {
            ProcessPushinPayWebhookJob::dispatch($id);
        }

        // Sempre 200 — não fazer a PushinPay re-tentar por erro de parse nosso.
        return response()->json(['ok' => true]);
    }
}
