---
date: 2026-06-05
type: adr
status: accepted
area: seguranca
tags: [pix, pushinpay, webhook, pagamentos, idempotencia]
---

# ADR: Confiar no pagamento PIX sem assinatura de webhook

## Contexto

A integração de carga de saldo usa a **PushinPay** (gateway PIX). A confirmação do
pagamento chega por **webhook**, mas a PushinPay **não assina** o payload (sem HMAC,
sem header de assinatura). Confiar no corpo do webhook permitiria a qualquer um forjar
um `paid` e creditar saldo — risco financeiro direto.

## Decisão

Modelo de confiança em camadas, **sem nunca confiar no corpo do webhook**:

1. **Secret na URL**: rota `/api/webhooks/pushinpay/{token}`, validada com `hash_equals` contra `PUSHINPAY_WEBHOOK_SECRET` (env). Mismatch → 404.
2. **Re-verificação autoritativa**: o controller extrai só o `id` e despacha `ProcessPushinPayWebhookJob`, que chama `GET /api/transactions/{id}` na PushinPay e só credita se o status real for `paid`. O corpo do webhook é apenas um "ping".
3. **Idempotência**: `pix_charges.pushinpay_id` unique + `confirmPaid` com guard de status sob `lockForUpdate` → crédito exatamente uma vez, mesmo com webhook + reconcile + polling concorrentes.
4. **Fallback**: `ReconcilePendingPixChargesJob` (agendado) re-consulta cobranças pendentes — cobre webhook perdido. `PixChargeController::show` também re-consulta on-demand (throttle 3s) para confirmar sem webhook público (local/sandbox).

## Consequências

**Positivas:**
- Forjar `paid` é inútil: o crédito só ocorre após consulta direta à PushinPay pelo `id`.
- Funciona sem URL pública (local/sandbox) via polling + reconcile.
- Crédito idempotente — sem saldo duplicado.

**Negativas / trade-offs:**
- Cada confirmação faz 1 request extra à PushinPay (consulta) — custo aceitável.
- Reconcile e expiração dependem de um runner de scheduler (hoje inexistente — ver DT-06); o `show` on-demand cobre o caminho local.
- O `id` da PushinPay vem UPPERCASE na consulta e lowercase na criação → normalizar (`strtolower`).

## Alternativas descartadas

| Alternativa | Motivo do descarte |
|---|---|
| Confiar no `status` do corpo do webhook | Forjável — risco financeiro direto |
| Só polling no frontend (sem re-verificação server-side) | Cliente não pode ser a autoridade do pagamento |
| Exigir IP allowlist da PushinPay como única defesa | IP é spoofável/instável; combinado ok, sozinho não basta |

## Features que aplicam esta decisão

- [[2026-06-05 pix-topup-pushinpay-inc2]]

## Links relacionados

- [[2026-06-05 boost-destaque-inc1]]
- [[Divida-Tecnica]]
