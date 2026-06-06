<?php

namespace App\Jobs;

use App\Enums\BoostStatus;
use App\Models\Boost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Limpeza diária: marca boosts vencidos como `expired`. A vitrine já filtra por
 * data (scopeActive), então isto é só consistência/relatório do banco.
 */
class ExpireBoostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('payments');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('boost-expire'))->releaseAfter(120)->expireAfter(600)->dontRelease()];
    }

    public function handle(): void
    {
        Boost::query()
            ->where('status', BoostStatus::Active)
            ->where('expires_at', '<', now())
            ->update(['status' => BoostStatus::Expired]);
    }
}
