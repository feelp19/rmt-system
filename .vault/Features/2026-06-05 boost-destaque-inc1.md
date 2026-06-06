---
date: 2026-06-05
type: feature
status: implemented
area: arquitetura
tags: [boost, destaque, marketplace, wallet, monetizacao]
---

# Feature: Boost / destaque pago — Incremento 1 (carteira)

## O que foi implementado

O anunciante pode **turbinar** seu anúncio pagando um pacote e aparecer numa área de
destaque para vender mais rápido. 3 pacotes (preço fixo): **Básico R$5 · Intermediário
R$15 · Avançado R$25**. Duração **7 dias** (igual p/ todos; o tier muda só posição/alcance).

**Inc 1** entrega o domínio + pagamento por **carteira** + superfícies + UI + testes.
**Inc 2** (planejado) adiciona pagamento via **PIX (PushinPay)** — ver "Próximo passo".

## Decisões técnicas

- **Superfícies por tier** ("mostra pra mais pessoas"): básico = faixa "Em destaque" da home; intermediário = + topo da vitrine geral; avançado = + maior peso/slot patrocinado. Codificado em `BoostTier` (`weight()`, `floatsInGrid()`).
- **`weight` denormalizado** na tabela `boosts` (do tier) → ordenação por **subquery `addSelect`** sem `DB::raw` (regra 5): `orderByDesc(grid_boost_weight)`.
- **Pagamento por carteira**: `BoostService::purchaseWithWallet` em transação + `lockForUpdate` na carteira (mesma disciplina do escrow, regra 8). Saldo insuficiente → 422.
- **Máx. 1 boost ativo por anúncio** (checado sob lock).
- **Expiração**: verdade na query (`scopeActive` = `status active` E `expires_at > now`). Job diário de limpeza fica pro Inc 2 junto dos jobs de pagamento.
- **IDOR**: boost escopado por dono (`seller_id = auth` + `firstOrFail` → 404) + `ListingPolicy::boost`.
- `activeBoost` = HasOne `->active()->latestOfMany()` p/ badge sem N+1.

## Arquivos criados / modificados

| Arquivo | O que mudou |
|---|---|
| `app/Enums/{BoostTier,BoostStatus,BoostPaymentMethod}.php` | enums (tier com preço/peso) |
| `database/migrations/2026_06_05_120001_create_boosts_table.php` | tabela boosts |
| `app/Models/Boost.php` + `Listing.php`/`User.php` | model + relações (`activeBoost`) |
| `app/Services/BoostService.php` | compra por carteira (transação+lock) |
| `app/Policies/ListingPolicy.php` | método `boost` |
| `app/Http/Resources/Marketplace/BoostResource.php` + `ListingResource` | contrato + campo `boost` |
| `app/Http/Requests/Marketplace/StoreBoostRequest.php` | valida tier; Inc1 só `wallet` |
| `app/Http/Controllers/Marketplace/{BoostController,ListingController}.php` | store boost + `featured` + grid float |
| `routes/api.php`, `config/marketplace.php` | rotas + `boost_days` |
| `frontend/app/components/{FeaturedListings,BoostDialog}.vue` + `ListingCard.vue` + `pages/index.vue` | faixa destaque, dialog, badge/botão "Turbinar" |
| `tests/Feature/Marketplace/BoostTest.php` + `Unit/BoostTierTest.php` + `BoostFactory` | 11 testes (verde) |

## Pitfalls / o que me surpreendeu

- Ordenar por coluna de relação sem `DB::raw`: usar `addSelect(['alias' => SubqueryBuilder])` + `orderByDesc('alias')`. NULL (não-boostado) cai pro fim no `DESC` (MySQL e sqlite). Funciona no `paginate` (count remove orders).
- Frontend: `refreshNuxtData(['listings','featured'])` após boostar atualiza vitrine + faixa destaque sem plumbing de eventos.
- Container `app`/`nuxt` são image-baked → `docker compose up -d --build app nuxt` + `migrate` + `octane:reload` (quirk de cold-boot dos workers).

## Dívida técnica gerada

- Job de expiração de boost adiado p/ Inc 2 (hoje a query filtra por data — correto, mas status fica `active` no banco até limpeza).

## Próximo passo — Inc 2 (PushinPay PIX)

- `value` da PushinPay é em **centavos**; webhook **sem HMAC** → validar com `?token` secreto na URL + **re-buscar status via GET por id** (rmt-security) + idempotência (`pushinpay_id` unique).
- Jobs: `ProcessPushinPayWebhookJob` (idempotente), `ReconcilePendingPixChargesJob` (agendado, fallback de webhook), `ExpireBoostsJob` (diário). Fila `payments` + supervisor no Horizon.
- `StoreBoostRequest` libera `pix`; `BoostService` ganha `initiatePixPurchase` + `activateFromPayment`.

## Skills atualizadas

- [x] `rmt-schema` · `rmt-context` · `rmt-architecture` · `rmt-frontend` · `rmt-tests`

## Links relacionados

- [[2026-06-05 marketplace-escrow-mvp]]
- [[2026-06-05 homepage]]
