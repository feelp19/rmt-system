<?php

namespace App\Services;

use App\Enums\PixChargeStatus;
use App\Models\PixCharge;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class PixChargeService
{
    public function __construct(
        private readonly PushinPayService $gateway,
        private readonly WalletService $wallets,
    ) {}

    /** Gera uma cobrança PIX para carregar saldo na carteira do usuário. */
    public function createForTopUp(User $user, int $amountCents): PixCharge
    {
        // Garante a carteira já aqui (fora de qualquer transação concorrente) —
        // `confirmPaid` então sempre a encontra, sem `create` sob lock (corrida de unique).
        $this->wallets->walletFor($user);

        $data = $this->gateway->createPix($amountCents, $this->webhookUrl());

        return PixCharge::create([
            'user_id' => $user->id,
            'pushinpay_id' => $this->normalizeId((string) ($data['id'] ?? '')),
            'amount_cents' => (int) ($data['value'] ?? $amountCents),
            'status' => PixChargeStatus::Created,
            'qr_code' => (string) ($data['qr_code'] ?? ''),
            'qr_code_base64' => (string) ($data['qr_code_base64'] ?? ''),
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * Re-verifica o status na PushinPay (fonte autoritativa — webhook não tem
     * assinatura, então NUNCA confiamos no corpo dele) e aplica o efeito.
     */
    public function refreshFromGateway(PixCharge $charge): PixCharge
    {
        if ($charge->status !== PixChargeStatus::Created) {
            return $charge; // já terminal — nada a fazer
        }

        $data = $this->gateway->getTransaction($charge->pushinpay_id);
        $status = strtolower((string) ($data['status'] ?? ''));

        if ($status === 'paid') {
            return $this->confirmPaid($charge, $data['end_to_end_id'] ?? null);
        }

        if (in_array($status, ['canceled', 'cancelled', 'expired'], true)) {
            $charge->update(['status' => PixChargeStatus::Canceled]);
        }

        return $charge->refresh();
    }

    /**
     * Marca a cobrança como paga e credita a carteira — **exatamente uma vez**.
     * Idempotente: o guard de status sob lock impede crédito duplo (webhook +
     * reconcile + refresh on-demand podem competir).
     */
    public function confirmPaid(PixCharge $charge, ?string $endToEndId = null): PixCharge
    {
        DB::beginTransaction();
        try {
            $locked = PixCharge::whereKey($charge->id)->lockForUpdate()->first();

            // Só credita na transição created → paid. Qualquer outro estado
            // (paid já creditado, expired, canceled) é no-op — idempotente e
            // imune à corrida com o ReconcilePendingPixChargesJob.
            if ($locked->status !== PixChargeStatus::Created) {
                DB::commit();

                return $locked;
            }

            // Carteira garantida em createForTopUp + no registro — sem create sob lock.
            $wallet = Wallet::where('user_id', $locked->user_id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance_cents', $locked->amount_cents);

            $locked->update([
                'status' => PixChargeStatus::Paid,
                'paid_at' => now(),
                'end_to_end_id' => $endToEndId,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $locked->refresh();
    }

    /** Entrada do webhook: re-verifica pela API (não confia no corpo). Idempotente. */
    public function handleWebhookById(string $pushinpayId): void
    {
        $charge = PixCharge::where('pushinpay_id', $this->normalizeId($pushinpayId))->first();

        if ($charge !== null) {
            $this->refreshFromGateway($charge);
        }
    }

    private function normalizeId(string $id): string
    {
        // A PushinPay devolve o id em UPPERCASE na consulta e lowercase na criação.
        return strtolower($id);
    }

    private function webhookUrl(): string
    {
        $secret = (string) config('services.pushinpay.webhook_secret');

        return rtrim((string) config('app.url'), '/').'/api/webhooks/pushinpay/'.$secret;
    }
}
