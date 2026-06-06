<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\LedgerEntryResource;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletLedgerController extends Controller
{
    /** Extrato do ledger do próprio usuário (escopado por user_id). */
    public function index(Request $request): JsonResponse
    {
        $paginator = LedgerEntry::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        $paginator->through(
            fn (LedgerEntry $entry) => LedgerEntryResource::make($entry)->resolve($request),
        );

        return response()->json($paginator);
    }
}
