<?php

namespace App\Http\Controllers\Profile;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\Marketplace\ListingResource;
use App\Http\Resources\User\RankingResource;
use App\Http\Resources\User\UserResource;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly ImageUploadService $images) {}

    /** Perfil do próprio usuário + estatísticas. */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'user' => UserResource::make($request->user())->resolve($request),
            'stats' => [
                'active_listings' => Listing::where('seller_id', $userId)->where('status', ListingStatus::Active)->count(),
                'sales' => Order::where('seller_id', $userId)->where('status', OrderStatus::Completed)->count(),
                'purchases' => Order::where('buyer_id', $userId)->where('status', OrderStatus::Completed)->count(),
            ],
        ]);
    }

    /** Ranking público: top 20 por XP. */
    public function leaderboard(Request $request): JsonResponse
    {
        $top = User::query()->orderByDesc('xp')->limit(20)->get();

        return response()->json([
            'data' => RankingResource::collection($top)->resolve($request),
        ]);
    }

    /** Anúncios do próprio usuário (todos os status). */
    public function listings(Request $request): JsonResponse
    {
        $listings = Listing::where('seller_id', $request->user()->id)
            ->with(['seller', 'activeBoost'])
            ->latest()
            ->get();

        return response()->json([
            'data' => ListingResource::collection($listings)->resolve($request),
        ]);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $oldPath = $user->avatar_path;

        $user->update([
            'avatar_path' => $this->images->storeImage($request->file('avatar'), 'avatars', 512),
        ]);
        $this->images->delete($oldPath);

        return response()->json([
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    /** Stream público do avatar (disco privado). 404 se não tiver. */
    public function avatar(int $user): StreamedResponse
    {
        $model = User::findOrFail($user);

        abort_if(
            $model->avatar_path === null || ! Storage::disk('local')->exists($model->avatar_path),
            404,
        );

        return Storage::disk('local')->response(
            $model->avatar_path,
            null,
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=300'],
            'inline',
        );
    }
}
