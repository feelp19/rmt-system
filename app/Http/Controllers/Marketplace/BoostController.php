<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\BoostTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreBoostRequest;
use App\Http\Resources\Marketplace\BoostResource;
use App\Models\Listing;
use App\Services\BoostService;
use Illuminate\Http\JsonResponse;

class BoostController extends Controller
{
    public function __construct(private readonly BoostService $boosts) {}

    public function store(StoreBoostRequest $request, int $listing): JsonResponse
    {
        // Escopo por dono → 404 anti-enumeração para quem não é o vendedor (regra 4).
        $model = Listing::where('id', $listing)
            ->where('seller_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('boost', $model);

        $tier = BoostTier::from($request->validated('tier'));
        $boost = $this->boosts->purchaseWithWallet($request->user(), $model, $tier);

        return response()->json([
            'data' => BoostResource::make($boost->load('listing'))->resolve($request),
        ], 201);
    }
}
