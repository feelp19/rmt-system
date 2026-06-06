---
date: 2026-06-05
type: adr
status: accepted
area: arquitetura
tags: [escrow, pagamentos, wallet, concorrencia]
---

# ADR: Escrow de dupla confirmação com carteira interna simulada

## Contexto

O marketplace precisa que o dinheiro **não** chegue ao vendedor enquanto comprador e
vendedor não confirmarem a transação. Era preciso decidir (a) como representar o dinheiro
e (b) como modelar a retenção (escrow) e a liberação, num MVP "para ver".

## Decisão

- **Carteira interna simulada** (`wallets.balance_cents`), sem gateway de pagamento real. Depósito é top-up de demonstração.
- **Dinheiro em centavos inteiros**; taxa de 5% (`config('marketplace.fee_percent')`) calculada com `intdiv` — determinística, sem float.
- **Escrow vive na própria `Order`**: comprar debita a wallet do comprador e cria a Order `awaiting_confirmation` com `amount_cents`/`fee_cents`/`seller_payout_cents` travados; o listing vira `sold`.
- **Liberação por dupla confirmação**: `seller_confirmed_at` (vendedor) + `buyer_confirmed_at` (comprador). Quando ambos preenchidos, `release` credita `seller_payout_cents` ao vendedor e marca `completed`. A taxa fica retida pela plataforma.
- **Concorrência**: `purchase` e `release` em `DB::beginTransaction` + `lockForUpdate`; confirmações idempotentes; liberação única (guard de status sob lock).

## Consequências

**Positivas:**
- Lógica de escrow testável ponta-a-ponta sem integração externa.
- Valores monetários exatos (centavos), sem erro de ponto flutuante.
- Seguro sob Octane/concorrência (locks + idempotência).

**Negativas / trade-offs:**
- Não é dinheiro real — trocar por gateway exige nova camada (pagamentos, payout, idempotency keys de provedor).
- Sem cancelamento/reembolso → fundos podem ficar presos (DT-01).
- Taxa não é creditada a uma conta de plataforma (DT-02).

## Alternativas descartadas

| Alternativa | Motivo do descarte |
|---|---|
| Gateway real (Stripe/Pix) no MVP | Complexidade desproporcional ao objetivo "para ver"; escrow é o foco |
| Saldo "held" separado na wallet | Redundante — a Order já carrega o valor retido |
| Liberar com 1 confirmação | Viola o requisito central (ambos precisam dar "sim") |
| `DB::transaction(fn)` | Proibido pela regra 8; usado `beginTransaction` explícito |

## Features que aplicam esta decisão

- [[2026-06-05 marketplace-escrow-mvp]]

## Atualizações no vault

- [x] Link no cluster "Decisões Arquiteturais" do `MOC.md`
- [x] Skills atualizadas (`rmt-architecture`, `rmt-security`, `rmt-schema`)

## Links relacionados

- [[2026-06-05 marketplace-escrow-mvp]]
- [[Divida-Tecnica]]
