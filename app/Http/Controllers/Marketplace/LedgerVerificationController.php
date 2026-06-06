<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\LedgerVerificationResource;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerVerificationController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Verifica a confiabilidade de uma linha pelo código (hash). Escopo: dono da
     * carteira OU contraparte da order referenciada. Mismatch → 404 (anti-enumeração).
     */
    public function show(Request $request, string $hash): JsonResponse
    {
        $entry = LedgerEntry::where('hash', $hash)->first();

        // 404 (não 403) para linha inexistente OU fora do escopo — anti-enumeração
        // (regra 4). A visibilidade vive na LedgerPolicy; chamamos via cannot() para
        // preservar o 404 (authorize() devolveria 403).
        abort_if($entry === null || $request->user()->cannot('view', $entry), 404);

        return response()->json([
            'data' => LedgerVerificationResource::make($entry, $this->ledger->verifyEntry($entry))
                ->resolve($request),
        ]);
    }
}
