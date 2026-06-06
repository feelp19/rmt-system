<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\BoostTier;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreListingRequest;
use App\Http\Resources\Marketplace\ListingResource;
use App\Models\Boost;
use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(private readonly ListingService $listings) {}

    /** Vitrine pública: anúncios ativos, paginados. Boosts intermediário/avançado flutuam pro topo. */
    public function index(Request $request): JsonResponse
    {
        // Peso do boost que flutua na vitrine (só intermediário/avançado).
        // Subquery via query builder — sem DB::raw (regra 5).
        $gridBoostWeight = Boost::query()
            ->select('weight')
            ->whereColumn('listing_id', 'listings.id')
            ->active()
            ->whereIn('tier', [BoostTier::Intermediate, BoostTier::Advanced])
            ->orderByDesc('weight')
            ->limit(1);

        $paginator = Listing::query()
            ->where('status', ListingStatus::Active)
            ->with(['seller', 'activeBoost'])
            ->select('listings.*')
            ->addSelect(['grid_boost_weight' => $gridBoostWeight])
            ->orderByDesc('grid_boost_weight') // boostados primeiro (NULL = não-boostado vai pro fim)
            ->latest()
            ->paginate(20);

        $paginator->through(
            fn (Listing $listing) => ListingResource::make($listing)->resolve($request),
        );

        return response()->json($paginator);
    }

    /** Faixa "Em destaque": todos os anúncios com boost ativo, ordenados por tier (avançado primeiro). */
    public function featured(Request $request): JsonResponse
    {
        $featuredWeight = Boost::query()
            ->select('weight')
            ->whereColumn('listing_id', 'listings.id')
            ->active()
            ->orderByDesc('weight')
            ->limit(1);

        $featured = Listing::query()
            ->where('status', ListingStatus::Active)
            ->whereHas('boosts', fn ($query) => $query->active()) // qualquer tier entra no destaque
            ->with(['seller', 'activeBoost'])
            ->select('listings.*')
            ->addSelect(['featured_weight' => $featuredWeight])
            ->orderByDesc('featured_weight')
            ->latest()
            ->limit(12)
            ->get();

        return response()->json([
            'data' => ListingResource::collection($featured)->resolve($request),
        ]);
    }

    public function show(Request $request, int $listing): JsonResponse
    {
        $model = Listing::with(['seller', 'activeBoost'])->findOrFail($listing);

        return response()->json([
            'data' => ListingResource::make($model)->resolve($request),
        ]);
    }

    public function store(StoreListingRequest $request): JsonResponse
    {
        $listing = $this->listings->create($request->user(), $request->validated());

        return response()->json([
            'data' => ListingResource::make($listing->load(['seller', 'activeBoost']))->resolve($request),
        ], 201);
    }

    public function destroy(Request $request, int $listing): JsonResponse
    {
        // Escopo por dono → 404 anti-enumeração para quem não é o vendedor (regra 4).
        $model = Listing::where('id', $listing)
            ->where('seller_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('delete', $model);

        $cancelled = $this->listings->cancel($model);

        return response()->json([
            'data' => ListingResource::make($cancelled->load(['seller', 'activeBoost']))->resolve($request),
        ]);
    }
}
