---
date: 2026-06-05
type: feature
status: implemented
area: arquitetura
tags: [upload, imagem, perfil, avatar, xp, gamificacao, ranking, listing]
---

# Feature: Imagens + editar/excluir anúncio, Perfil+avatar, XP/níveis/perks/ranking

Batch em 3 incrementos (A→B→C).

## Inc A — Imagens + anúncio completo
- **Foto obrigatória no anúncio** + upload hardening em 4 camadas (`ImageUploadService`): FormRequest `image|mimes|max` → `finfo` magic bytes → **reprocessa via GD pra WebP** → disco privado + nome random. `ext-gd` adicionado ao Dockerfile.
- Servido por endpoint **id-based** `GET /api/listings/{id}/photo` (não `/storage`, que cai no Nuxt no single-origin). `nosniff` + cache 5min.
- **Editar** (`PUT /api/listings/{id}`, multipart `_method=PUT`, só ativo, troca foto e apaga antiga) e **excluir** (cancel). Antigos sem foto → placeholder.

## Inc B — Perfil + avatar
- `GET /api/me/profile` (user + stats: ativos/vendas/compras), `GET /api/me/listings`, `POST /api/me/avatar` (reusa o pipeline, dir `avatars/`), `GET /api/users/{id}/avatar`.
- Frontend `/perfil` (`ProfileSummary`, `AvatarUploader`) + avatar/level no header e nos cards.

## Inc C — XP / níveis / perks / ranking
- `XpService`: XP por uso — vender +50, comprar +20 (em `OrderService::release`), turbinar +15 (`BoostService`), **1º anúncio +10 (uma vez)** (`ListingService`). Níveis por faixas (0/100/300/600/1000…).
- **Perk**: taxa do escrow cai pelo nível do **vendedor** (`feeBpsForLevel`, base 5% → piso 3%), em basis points (`OrderService::feeFor($amount, $seller)`).
- **Ranking** público `GET /api/leaderboard`. Frontend `/ranking` + `LevelBadge`.

## Decisões técnicas
- **Imagem em disco privado + endpoint id-based** (não `/storage`): no single-origin do Caddy, `/storage` iria pro Nuxt. O endpoint passa pelo worker (`/api`).
- **GD puro** (sem Intervention) pra reprocessar → WebP — evita dependência composer; `ext-gd` no Dockerfile.
- **XP não-fillable** — só `XpService::award` incrementa (atômico). Increments ficam dentro das transações existentes (escrita única).
- **Taxa em basis points** pra suportar percentuais fracionários (4.8% etc.) com `intdiv` em centavos.
- Editar anúncio multipart → `_method=PUT` (PHP não parseia multipart PUT direto).

## Pitfalls
- Container **não tinha GD** (nem Imagick) — precisei adicionar `gd` ao `install-php-extensions` do Dockerfile (com WebP). Host já tinha GD (testes ok).
- `/storage` não é servido pelo Laravel no Caddyfile (vai pro Nuxt) → servir imagem só via endpoint `/api`.
- Factories **setam `xp`** mesmo não-fillable (ignoram o mass-assignment guard) — útil pros testes de nível/perk.
- Hero estava cortado: `overflow-x: clip` no `.page` (max-width) cortava o full-bleed → virou painel contido + clip no wrapper `.shell`.

## Dívida técnica
- **DT-07**: foto/avatar com cache 300s e URL sem versão → editar a imagem reflete em outros lugares em até 5min.
- **DT-08**: XP sem anti-fraude de wash-trading (duas contas comprando/vendendo entre si farmam XP e desconto de taxa). Comprar do próprio anúncio já é bloqueado, mas conluio entre contas não.

## Skills atualizadas
- [x] `rmt-schema` · `rmt-context` · `rmt-architecture` · `rmt-security` · `rmt-frontend` · `rmt-tests`

## Links
- [[2026-06-05 marketplace-escrow-mvp]] · [[2026-06-05 boost-destaque-inc1]] · [[2026-06-05 homepage]]
