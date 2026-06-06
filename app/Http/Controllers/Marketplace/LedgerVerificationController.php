<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\LedgerVerificationResource;
use App\Models\LedgerEntry;
use App\Models\Order;
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

        if ($entry === null || ! $this->canView($request->user()->id, $entry)) {
            abort(404);
        }

        return response()->json([
            'data' => LedgerVerificationResource::make($entry, $this->ledger->verifyEntry($entry))
                ->resolve($request),
        ]);
    }

    /** Dono da carteira da linha, ou parte da order quando a linha referencia uma order. */
    private function canView(int $userId, LedgerEntry $entry): bool
    {
        if ($entry->user_id === $userId) {
            return true;
        }

        if ($entry->reference_type === 'order' && $entry->reference_id !== null) {
            return Order::whereKey($entry->reference_id)
                ->where(function ($query) use ($userId) {
                    $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
                })
                ->exists();
        }

        return false;
    }
}
