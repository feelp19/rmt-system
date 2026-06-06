<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\ActivityItemResource;
use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityFeedService $activity) {}

    /** Feed público da home: vendas concluídas recentes + nº em escrow. */
    public function index(Request $request): JsonResponse
    {
        $snapshot = $this->activity->snapshot();

        return response()->json([
            'data' => ActivityItemResource::collection($snapshot['sales'])->resolve($request),
            'in_escrow_count' => $snapshot['in_escrow_count'],
        ]);
    }
}
