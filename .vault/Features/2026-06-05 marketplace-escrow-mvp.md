---
date: 2026-06-05
type: feature
status: implemented
area: arquitetura
tags: [marketplace, escrow, wallet, sanctum, primevue]
---

# Feature: Marketplace de itens/gold com escrow de dupla confirmação (MVP)

## O que foi implementado

Marketplace onde usuários anunciam itens/gold de qualquer jogo e compram entre si.
Núcleo: **escrow de dupla confirmação** — ao comprar, o valor sai da carteira do
comprador e fica **retido na Order**; só é liberado ao vendedor quando **ambos**
confirmam (vendedor "entreguei", comprador "recebi"). A plataforma retém **5%** de taxa.

- Auth Sanctum (token Bearer): `register` (cria carteira) / `login` / `me` / `logout`.
- Carteira simulada (`Wallet`, centavos inteiros) + depósito demo (`/wallet/deposit`).
- Anúncios (`Listing`): vitrine pública paginada, criar, cancelar (dono).
- Pedidos (`Order`): comprar (escrow), `confirm-delivery` (vendedor), `confirm-receipt` (comprador).
- Frontend Nuxt 4 + PrimeVue: vitrine, login/registro, carteira, pedidos (componente-first).

## Decisões técnicas

- **Carteira interna simulada**, sem gateway de pagamento — ver [[ADR — escrow dupla-confirmacao e carteira simulada]].
- **Dinheiro em centavos inteiros** (`bigInteger`), taxa via `intdiv` — nunca float.
- **Escrow = valor retido na própria Order** (não há saldo "held" separado na wallet): comprar debita a wallet; `release` credita o vendedor `amount - fee`; a taxa fica retida pela plataforma (sem conta de plataforma no MVP → DT-02).
- **Concorrência**: `purchase` e `release` usam `DB::beginTransaction` + `lockForUpdate` (regra 8) contra venda dupla / saldo negativo / liberação dupla sob Octane. Confirmações são idempotentes.
- **IDOR**: Order escopada por participante (`buyer_id`/`seller_id`) → 404 a estranho; papel errado (comprador confirmando entrega) → Policy → 403.
- PKs BigInt (não ULID) para simplicidade do MVO; anúncios são públicos, sem risco de enumeração sensível.

## Arquivos criados / modificados

| Arquivo | O que mudou |
|---|---|
| `app/Enums/{ListingType,ListingStatus,OrderStatus}.php` | enums string-backed |
| `database/migrations/2026_06_05_1000(01-03)_*` | wallets, listings, orders |
| `app/Models/{Wallet,Listing,Order}.php` + `User.php` | models + relações |
| `app/Services/{Wallet,Listing,Order}Service.php` | regra de negócio (escrow) |
| `app/Policies/{Listing,Order}Policy.php` | autorização |
| `app/Http/Resources/{User,Wallet,Marketplace}/*` | contrato JSON |
| `app/Http/Requests/{Auth,Marketplace,Wallet}/*` | validação + authorize |
| `app/Http/Controllers/{Auth,Wallet,Marketplace}/*` | thin wrappers |
| `routes/api.php`, `bootstrap/app.php` (DomainException→422), `config/marketplace.php` | rotas + infra |
| `frontend/app/{composables,utils,components,pages,layouts,middleware,plugins}/*` | UI Nuxt/PrimeVue |
| `tests/Feature/{Marketplace,Auth}/*` + factories | 20 testes (verde) |

## Pitfalls / o que me surpreendeu

- **`composer dump-autoload` dentro do container `app` com Octane vivo corrompe o autoloader em memória** → `Target class [config] does not exist` (erro mascarado: o `report()` do worker quebra ao logar). Fix: `octane:reload`/restart. Registrado em `rmt-tests`.
- O container `app` é **code-baked** (sem bind mount; só volumes do Caddy). Editar no host não reflete sem rebuild — para testar ao vivo usei `docker compose cp` + `octane:reload`. O usuário deve **rebuildar a imagem** (`make up` / `--build`) para persistir.
- Host sem `pdo_sqlite` carregado; `php artisan test` re-spawna sem `-d extension`. Rodar `php -d extension=pdo_sqlite vendor/bin/phpunit` com `DB_CONNECTION=sqlite DB_DATABASE=:memory:`.
- Cast `hashed` do model **não** re-hasheia valor já-hash (seguro passar `Hash::make` ou plaintext no seeder).
- **Quirk de cold-boot do Octane/FrankenPHP (PHP 8.5 + Octane 2.17)**: no primeiro fork de workers ao subir o container, o caminho de resposta do `POST /api/login` dá 500 mascarado (`Target class [config] does not exist` no `report()` do worker — a exceção primária escapa pro nível do worker). O mesmo request passa 200 pelo HTTP kernel em CLI e nos testes. **Workaround**: rodar `php artisan octane:reload` (ou `make octane-reload`) **uma vez após `make up`** — re-forka os workers e estabiliza. Candidato a colocar no entrypoint/healthcheck. Não é bug da aplicação.

## Dívida técnica gerada

- DT-01: sem fluxo de cancelamento/reembolso → fundos podem ficar presos no escrow.
- DT-02: taxa retida não vai para conta de plataforma (apenas "não creditada").
- DT-03: sem disputas/timeout de auto-liberação.
- DT-04: fetch no frontend é client-side (`server: false`) — sacrifica SSR dos dados.

Ver [[Divida-Tecnica]].

## Skills atualizadas

- [x] `rmt-context`
- [x] `rmt-schema`
- [x] `rmt-architecture`
- [x] `rmt-frontend`
- [x] `rmt-security`
- [x] `rmt-tests`

## Atualizações no vault

- [x] Link no `MOC.md`
- [x] ADR criado: [[ADR — escrow dupla-confirmacao e carteira simulada]]

## Links relacionados

- [[ADR — escrow dupla-confirmacao e carteira simulada]]
- [[Divida-Tecnica]]
