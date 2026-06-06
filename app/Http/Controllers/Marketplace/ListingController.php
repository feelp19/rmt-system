<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\BoostTier;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreListingRequest;
use App\Http\Requests\Marketplace\UpdateListingRequest;
use App\Http\Resources\Marketplace\ListingResource;
use App\Models\Boost;
use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingController extends Controller
{
    public function __construct(private readonly ListingService $listings) {}

    /** Vitrine pública: anúncios ativos, paginados. Boosts intermediário/avançado flutuam pro topo. */
    public function index(Request $request): JsonResponse
    {
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
            ->orderByDesc('grid_boost_weight')
            ->latest()
            ->paginate(20);

        $paginator->through(
            fn (Listing $listing) => ListingResource::make($listing)->resolve($request),
        );

        return response()->json($paginator);
    }

    /** Faixa "Em destaque": anúncios com boost ativo, ordenados por tier (avançado primeiro). */
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
            ->whereHas('boosts', fn ($query) => $query->active())
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
        $listing = $this->listings->create(
            $request->user(),
            $request->validated(),
            $request->file('photo'),
        );

        return response()->json([
            'data' => ListingResource::make($listing->load(['seller', 'activeBoost']))->resolve($request),
        ], 201);
    }

    public function update(UpdateListingRequest $request, int $listing): JsonResponse
    {
        // Escopo por dono → 404 anti-enumeração (regra 4).
        $model = Listing::where('id', $listing)
            ->where('seller_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('update', $model);

        $updated = $this->listings->update($model, $request->validated(), $request->file('photo'));

        return response()->json([
            'data' => ListingResource::make($updated->load(['seller', 'activeBoost']))->resolve($request),
        ]);
    }

    public function destroy(Request $request, int $listing): JsonResponse
    {
        $model = Listing::where('id', $listing)
            ->where('seller_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('delete', $model);

        $cancelled = $this->listings->cancel($model);

        return response()->json([
            'data' => ListingResource::make($cancelled->load(['seller', 'activeBoost']))->resolve($request),
        ]);
    }

    /** Stream público da foto do anúncio (disco privado). 404 se não tiver foto. */
    public function photo(int $listing): StreamedResponse
    {
        $model = Listing::findOrFail($listing);

        abort_if(
            $model->photo_path === null || ! Storage::disk('local')->exists($model->photo_path),
            404,
        );

        return Storage::disk('local')->response(
            $model->photo_path,
            null,
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=300'],
            'inline',
        );
    }
}
