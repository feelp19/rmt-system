---
date: 2026-06-05
type: feature
status: implemented
area: arquitetura
tags: [pix, pushinpay, pagamentos, carteira, jobs, horizon, boost]
---

# Feature: Carga de saldo via PIX (PushinPay) — Boost Incremento 2

## O que foi implementado

Pagamento real para **carregar saldo na carteira** via **PIX (PushinPay)**. O usuário
gera uma cobrança, paga no banco, e o saldo é creditado após confirmação. O boost
(Inc 1) continua pago pela carteira — agora o saldo pode vir de PIX real.

Fluxo: `POST /api/wallet/pix {amount_cents}` → cria cobrança na PushinPay → devolve QR
(`qr_code` copia-e-cola + `qr_code_base64`). Frontend mostra o QR e faz **polling** em
`GET /api/wallet/pix/{id}`. Confirmação por **webhook** (prod) ou **re-consulta** (job
agendado / on-demand no polling). Em `paid` → credita a carteira (idempotente).

## Decisões técnicas

- **PIX = top-up da carteira** (não paga o boost direto) — mais limpo/reutilizável. Boost segue `wallet`.
- **Webhook sem HMAC** → secret na URL + re-verificação por id + idempotência. Ver [[ADR — webhook PushinPay sem assinatura]].
- **`value` em centavos** (confirmado batendo no sandbox). `id` vem UPPERCASE na consulta, lowercase na criação → normalizado `strtolower`.
- **Confirmação local sem URL pública**: `show` re-consulta o gateway (throttle 3s via `Cache::lock`) enquanto `created`. Sandbox não paga sozinho → mantive "Crédito demo (instantâneo)" no `DepositDialog` p/ testar o resto.
- **Jobs** numa fila `payments` + `supervisor-payments` no Horizon: `ProcessPushinPayWebhookJob`, `ReconcilePendingPixChargesJob` (agendado, `WithoutOverlapping`), `ExpireBoostsJob` (diário).
- **Octane-safe**: `PushinPayService` lê config a cada chamada, sem estado em propriedade. `Http::timeout()+connectTimeout()`; falha → `PushinPayException` → 503.

## Arquivos criados / modificados

| Arquivo | O que mudou |
|---|---|
| `config/services.php`, `.env`(+`.env.example`) | bloco `pushinpay` (token/base_url/webhook_secret) |
| `app/Enums/PixChargeStatus.php` | created/paid/expired/canceled |
| `database/migrations/2026_06_05_130001_create_pix_charges_table.php` | tabela `pix_charges` |
| `app/Models/PixCharge.php` | model + casts |
| `app/Exceptions/PushinPayException.php` | exceção tipada (→503) |
| `app/Services/{PushinPayService,PixChargeService}.php` | client + orquestração (createForTopUp/refreshFromGateway/confirmPaid) |
| `app/Http/Resources/Wallet/PixChargeResource.php` | contrato (QR) |
| `app/Http/Requests/Wallet/StorePixChargeRequest.php` | valida amount (min R$5) |
| `app/Http/Controllers/Wallet/PixChargeController.php` + `Webhooks/PushinPayWebhookController.php` | store/show + webhook |
| `app/Jobs/{ProcessPushinPayWebhookJob,ReconcilePendingPixChargesJob,ExpireBoostsJob}.php` | jobs |
| `routes/api.php`, `routes/console.php`, `config/horizon.php`, `bootstrap/app.php` | rotas, agenda, fila `payments`, render 503 |
| `frontend/app/components/DepositDialog.vue` + `types/index.ts` | fluxo PIX (QR + polling) |
| `tests/Feature/Wallet/PixTopUpTest.php` + `PixChargeFactory` | 9 testes (verde) |

## Pitfalls / o que me surpreendeu

- Descobri o contrato batendo no **sandbox real** com o token: create `POST /api/pix/cashIn` (value centavos), consulta `GET /api/transactions/{id}` (não documentado claramente; achei testando paths). `id` da consulta vem **UPPERCASE**.
- Webhook **não tem assinatura** — exigiu o modelo de re-verificação por id (ADR).
- Sandbox não confirma pagamento sozinho → não dá pra ver o crédito de ponta-a-ponta sem pagar; cobri o crédito com `Http::fake` nos testes e mantive o crédito demo.
- Container `nuxt`/`app`/`horizon` são image-baked → `up --build` nos 3 + `migrate` + `octane:reload`.

## Dívida técnica gerada

- **DT-06**: jobs agendados (reconcile/expire) precisam de um runner de scheduler (`schedule:work`/cron) — não há container dedicado hoje; o `show` on-demand cobre o caminho local. Ver [[Divida-Tecnica]].
- Webhook real exige URL pública (prod) — em sandbox local não chega; polling/reconcile cobrem.

## Skills atualizadas

- [x] `rmt-schema` · `rmt-context` · `rmt-architecture` · `rmt-security` · `rmt-frontend` · `rmt-tests`

## Links relacionados

- [[ADR — webhook PushinPay sem assinatura]]
- [[2026-06-05 boost-destaque-inc1]]
