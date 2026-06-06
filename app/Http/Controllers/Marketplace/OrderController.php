<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreOrderRequest;
use App\Http\Resources\Marketplace\OrderResource;
use App\Models\Listing;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** Pedidos do usuário — como comprador ou vendedor. */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $paginator = Order::query()
            ->where(function ($query) use ($userId) {
                $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
            })
            ->with(['listing', 'buyer', 'seller'])
            ->latest()
            ->paginate(20);

        $paginator->through(
            fn (Order $order) => OrderResource::make($order)->resolve($request),
        );

        return response()->json($paginator);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $model = $this->findParticipantOrder($request, $order);

        return $this->respondWith($request, $model);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $listing = Listing::findOrFail((int) $request->validated('listing_id'));

        $order = $this->orders->purchase($request->user(), $listing);

        return $this->respondWith($request, $order, 201);
    }

    public function confirmDelivery(Request $request, int $order): JsonResponse
    {
        $model = $this->findParticipantOrder($request, $order);
        $this->authorize('confirmDelivery', $model);

        $updated = $this->orders->confirmDelivery($model);

        return $this->respondWith($request, $updated);
    }

    public function confirmReceipt(Request $request, int $order): JsonResponse
    {
        $model = $this->findParticipantOrder($request, $order);
        $this->authorize('confirmReceipt', $model);

        $updated = $this->orders->confirmReceipt($model);

        return $this->respondWith($request, $updated);
    }

    /**
     * Busca o pedido escopado às partes (comprador/vendedor).
     * Estranho ao pedido recebe 404 — anti-enumeração (regra 4).
     */
    private function findParticipantOrder(Request $request, int $orderId): Order
    {
        $userId = $request->user()->id;

        return Order::where('id', $orderId)
            ->where(function ($query) use ($userId) {
                $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
            })
            ->firstOrFail();
    }

    private function respondWith(Request $request, Order $order, int $status = 200): JsonResponse
    {
        $order->load(['listing', 'buyer', 'seller']);

        return response()->json([
            'data' => OrderResource::make($order)->resolve($request),
        ], $status);
    }
}
