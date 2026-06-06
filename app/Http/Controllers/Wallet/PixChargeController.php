<?php

namespace App\Http\Controllers\Wallet;

use App\Enums\PixChargeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StorePixChargeRequest;
use App\Http\Resources\Wallet\PixChargeResource;
use App\Models\PixCharge;
use App\Services\PixChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PixChargeController extends Controller
{
    public function __construct(private readonly PixChargeService $charges) {}

    /** Gera uma cobrança PIX para carregar saldo. Retorna o QR. */
    public function store(StorePixChargeRequest $request): JsonResponse
    {
        $charge = $this->charges->createForTopUp(
            $request->user(),
            (int) $request->validated('amount_cents'),
        );

        return response()->json([
            'data' => PixChargeResource::make($charge)->resolve($request),
        ], 201);
    }

    /**
     * Polling do status. Escopado ao dono (404 anti-enumeração).
     * Enquanto pendente, re-verifica na PushinPay (throttle 3s) — confirma o
     * pagamento mesmo sem webhook público (ambiente local/sandbox).
     */
    public function show(Request $request, int $pixCharge): JsonResponse
    {
        $charge = PixCharge::where('id', $pixCharge)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($charge->status === PixChargeStatus::Created) {
            $charge = $this->refreshThrottled($charge);
        }

        return response()->json([
            'data' => PixChargeResource::make($charge)->resolve($request),
        ]);
    }

    private function refreshThrottled(PixCharge $charge): PixCharge
    {
        // No máximo 1 consulta ao gateway a cada 3s por cobrança.
        $lock = Cache::lock('pix-refresh:'.$charge->id, 3);

        if ($lock->get()) {
            try {
                return $this->charges->refreshFromGateway($charge);
            } catch (\Throwable) {
                // Gateway instável: devolve o estado atual sem quebrar o polling.
            }
        }

        return $charge;
    }
}
