<?php

namespace App\Jobs;

use App\Enums\PixChargeStatus;
use App\Models\PixCharge;
use App\Services\PixChargeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Fallback de confirmação: o webhook da PushinPay pode falhar (3 tentativas e
 * para). Este job, agendado, expira cobranças vencidas e re-verifica as
 * pendentes recentes consultando o status na API. Idempotente.
 */
class ReconcilePendingPixChargesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('payments');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('pix-reconcile'))->releaseAfter(60)->expireAfter(180)->dontRelease()];
    }

    public function handle(PixChargeService $charges): void
    {
        // Cobranças vencidas e não pagas → expired (bulk Eloquent).
        PixCharge::query()
            ->where('status', PixChargeStatus::Created)
            ->where('expires_at', '<', now())
            ->update(['status' => PixChargeStatus::Expired]);

        // Re-verifica as ainda pendentes (criadas há mais de 30s).
        PixCharge::query()
            ->where('status', PixChargeStatus::Created)
            ->where('created_at', '<', now()->subSeconds(30))
            ->limit(100)
            ->get()
            ->each(function (PixCharge $charge) use ($charges): void {
                try {
                    $charges->refreshFromGateway($charge);
                } catch (\Throwable) {
                    // Gateway instável: a próxima execução agendada tenta de novo.
                }
            });
    }
}
