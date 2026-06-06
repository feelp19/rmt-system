<?php

namespace App\Jobs;

use App\Services\PixChargeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processa um evento de webhook da PushinPay. Re-valida a autorização/estado
 * pela API (não confia no corpo do webhook) e credita a carteira — idempotente.
 */
class ProcessPushinPayWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly string $pushinpayId)
    {
        $this->onQueue('payments');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(PixChargeService $charges): void
    {
        $charges->handleWebhookById($this->pushinpayId);
    }
}
