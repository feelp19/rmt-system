<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Monta o feed público da home: últimas vendas concluídas + nº em escrow.
 * Cacheado 15s — a home bate nisto com frequência. Octane-safe: sem estado
 * mutável de instância (cache via facade).
 */
class ActivityFeedService
{
    private const FEED_LIMIT = 8;

    private const CACHE_KEY = 'activity.feed';

    private const CACHE_TTL = 15;

    /** @return array{sales: Collection<int, Order>, in_escrow_count: int} */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $sales = Order::query()
                ->where('status', OrderStatus::Completed)
                ->with(['listing', 'seller'])
                ->latest('completed_at')
                ->limit(self::FEED_LIMIT)
                ->get();

            $inEscrowCount = Order::query()
                ->where('status', OrderStatus::AwaitingConfirmation)
                ->count();

            return [
                'sales' => $sales,
                'in_escrow_count' => $inEscrowCount,
            ];
        });
    }
}
