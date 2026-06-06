---
name: rmt-context
description: Visão geral do rmt-system — stack, arquitetura, áreas do sistema, onde começar no código e convenções de desenvolvimento.
user-invocable: false
---

# rmt-system — Contexto Geral

## Quando usar

- Início de qualquer conversa ou tarefa sobre o projeto para ganhar contexto
- Dúvidas sobre stack, arquitetura de origem única e serviços Docker
- Antes de criar ou alterar controllers, rotas ou integrações entre backend e frontend
- Entender como o tráfego é roteado entre Laravel, Nuxt e serviços de observabilidade

## Resumo do produto

rmt-system é um scaffold de aplicação full-stack API + SSR com arquitetura de origem única.

- **Backend**: Laravel 13 API-only (sem views Blade; frontend desacoplado via Nuxt)
- **Frontend**: Nuxt 4 SSR com PrimeVue
- **Domínio**: marketplace de itens/gold de jogos com **escrow de dupla confirmação** + taxa de 5% (MVP). Entidades: `User`, `Wallet`, `Listing`, `Order`. Todo movimento de dinheiro gera uma entrada no **ledger de confiabilidade** (HMAC-SHA256 encadeado por wallet). Ver `rmt-schema` para o modelo de dados, `rmt-architecture` para o fluxo do escrow (`OrderService`) e o `LedgerService`.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13, PHP 8.3+, Laravel Octane 2.x |
| App server | FrankenPHP (Caddy embedded) — worker mode |
| Frontend | Nuxt 4 (SSR), Vue 3, PrimeVue 4.5 + @primeuix/themes (Aura), primeicons |
| Dados | MySQL 8.4, Redis (cache / filas) |
| Proxy | Caddy (single-origin): `/api`, `/sanctum` → Laravel worker; resto → Nuxt SSR |
| Auth | Laravel Sanctum (scaffold) |
| Filas | Laravel Horizon (container próprio, filas Redis; painel em `/horizon`) |
| Observabilidade | Laravel Pulse (`/pulse`), opcodesio/log-viewer (`/log-viewer`) |

**Nota sobre concorrência**: `Octane::concurrently()` é Swoole-only e **não existe** com FrankenPHP. Async real = jobs via Horizon.

## Áreas do sistema

### API (`routes/api.php`)

Público:
- `GET /api/health` — health check, retorna `{"status":"ok"}`
- `POST /api/register` · `POST /api/login` — auth Sanctum (token Bearer), `throttle:10,1`. `register` cria a `Wallet`.
- `GET /api/listings` · `GET /api/listings/{id}` — vitrine de anúncios ativos (paginada; boostados intermediário/avançado flutuam pro topo)
- `GET /api/listings/featured` — faixa "Em destaque": anúncios com boost ativo, ordem por tier
- `GET /api/activity` — feed público de atividade recente (`throttle:60,1`); retorna `{ data: [{ type, game, title, amount_cents, completed_at, seller_name }], in_escrow_count }`; **nunca expõe comprador**; cache Redis 15s

Autenticado (`auth:sanctum`):
- `GET /api/me` · `POST /api/logout` · `GET /api/user`
- `GET /api/wallet` · `POST /api/wallet/deposit` — carteira + top-up demo (`throttle:30,1`)
- `POST /api/wallet/pix` · `GET /api/wallet/pix/{id}` — carregar saldo via PIX (PushinPay): cria cobrança + polling do status (confirma on-demand)
- `POST /api/webhooks/pushinpay/{token}` — webhook público da PushinPay (autenticidade pelo secret na URL + re-verificação no job; sem auth:sanctum)
- `POST /api/listings` · `DELETE /api/listings/{id}` — criar / cancelar anúncio próprio
- `POST /api/listings` (multipart, **foto obrigatória**) · `PUT /api/listings/{id}` (multipart, `_method=PUT`) — criar/editar anúncio próprio (`throttle:20,1`)
- `GET /api/listings/{id}/photo` · `GET /api/users/{id}/avatar` (públicos) — stream de imagem (disco privado)
- `POST /api/listings/{id}/boosts` — turbinar anúncio próprio (boost pago; Inc 1: carteira, `throttle:30,1`)
- `GET /api/me/profile` · `GET /api/me/listings` · `POST /api/me/avatar` (multipart) — perfil + avatar
- `GET /api/leaderboard` (público) — ranking por XP
- `GET /api/orders` · `POST /api/orders` — listar / comprar (gera escrow)
- `GET /api/orders/{id}`
- `POST /api/orders/{id}/confirm-delivery` (vendedor) · `POST /api/orders/{id}/confirm-receipt` (comprador) — dupla confirmação; ambos → libera escrow
- `GET /api/wallet/ledger` — extrato paginado do ledger de confiabilidade, escopado por `user_id` (`throttle:60,1`)
- `GET /api/ledger/{hash}/verify` — verifica integridade de uma entrada do ledger; escopo: dono da wallet ou contraparte da order (`throttle:60,1`)

Controllers em `app/Http/Controllers/{Auth,Wallet,Marketplace}/` + `Marketplace\ActivityController`. Respostas via Resources em `app/Http/Resources/{User,Wallet,Marketplace}/`. Listagens usam envelope paginado (`response()->json($paginator->through(...))`); mutações/leitura única usam `{ "data": ... }`.

### Frontend (`frontend/`)
- Nuxt 4 SSR com PrimeVue Aura
- SSR interno usa `http://app/api` (rede Docker); browser usa `/api` relativo (mesma origem, sem CORS)
- Dev: proxy Nitro redireciona `/api` → `localhost:8000`
- Ponto de entrada: `frontend/app/`

### Filas e observabilidade
- Horizon em `/horizon` — painel de monitoramento de filas Redis
- Pulse em `/pulse` — métricas da aplicação
- log-viewer em `/log-viewer` — visualizador de logs

### Infra
- Docker Compose: containers `app` (FrankenPHP/Caddy), `horizon`, `nuxt`, `mysql`, `redis`
- Makefile: comandos de setup, migrate, test, shell, octane-reload, etc. (`make help` lista tudo)
- Caddyfile: roteamento single-origin (não há CORS a gerenciar)

## Onde começar no código

- Rotas da API: `routes/api.php`
- Controllers: `app/Http/Controllers/`
- Models: `app/Models/`
- Frontend (pages, components, composables): `frontend/app/`
- Config Nuxt: `frontend/nuxt.config.ts`
- Serviços Docker: `compose.yaml`
- Operações: `Makefile`

## Regras transversais

As regras abaixo refletem convenções definidas no `CLAUDE.md` do projeto. São direção para o crescimento do domínio; a maioria ainda não tem implementações concretas (Services, Policies, Resources, Enums não existem como diretórios ainda).

- **Controller fino**: lógica de negócio em Services (a criar em `app/Services/`)
- **Autorização**: via Policies (a criar em `app/Policies/`) + FormRequest; IDOR retorna 404, nunca 403
- **Eloquent como padrão**: sem query builder cru desnecessário
- **Respostas JSON**: via API Resources (a criar em `app/Http/Resources/`)
- **Frontend**: componentes PrimeVue como padrão; não criar CSS custom quando o tema Aura atende
- **Octane / worker mode**: nunca usar singletons com estado mutável; resolver dependências via container a cada request

## Referências rápidas

- `README.md` — setup, arquitetura, variáveis de ambiente, comandos `make`
- `routes/api.php` — superfície atual da API
- `frontend/nuxt.config.ts` — config SSR, PrimeVue, proxy dev, runtimeConfig
- `compose.yaml` — topologia Docker
- `Caddyfile` — roteamento single-origin
- `CLAUDE.md` — regras imutáveis do projeto
