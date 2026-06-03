# rmt-system

API **Laravel 13** (sob **Octane + FrankenPHP**, worker mode) + frontend **Nuxt 4 SSR** com **PrimeVue** (tema Aura), servidos em **origem única** por **Caddy**. Orquestrado por Docker Compose.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13, PHP 8.5, Laravel Octane 2.x |
| App server | FrankenPHP (Caddy embedded) — worker mode |
| Frontend | Nuxt 4 (SSR), Vue 3, PrimeVue 4.5 + @primeuix/themes (Aura), primeicons |
| Dados | MySQL 8.4, Redis (cache/queue) |
| Proxy | Caddy: `/api`,`/sanctum` → Laravel worker · resto → Nuxt SSR |
| Auth | Laravel Sanctum (scaffold) |

## Arquitetura

Origem única atrás do Caddy do container `app`:

```
                  ┌─────────────────────────── app (FrankenPHP/Caddy :80) ──┐
Browser ── :80 ──▶│  /api/*  /sanctum/*  ──▶  Laravel (Octane worker)        │
                  │  /*                  ──▶  reverse_proxy ─▶ nuxt:3000 (SSR)│
                  └──────────────────────────────────────────────────────────┘
                                                   │ SSR fetch
                              nuxt ──▶ http://app/api ──▶ Laravel (mesma rede)

   queue (php artisan queue:work)        mysql:8.4        redis:alpine
```

- **Browser** sempre chama `/api` relativo (mesma origem) → sem CORS.
- **SSR** (servidor Nuxt) chama a URL interna (`http://app/api`) na rede Docker.
- **Async** real = `queue:work` em container próprio (`Octane::concurrently()` é Swoole-only, não existe no FrankenPHP).

## Requisitos

- **Docker** + Docker Compose (caminho principal).
- Para dev híbrido no host: **PHP 8.3+**, **Composer**, **Node 22+**.

## Setup rápido (Docker)

```bash
git clone https://github.com/feelp19/rmt-system.git
cd rmt-system

cp .env.example .env        # ajuste credenciais se quiser
php artisan key:generate    # gera APP_KEY no .env (precisa de PHP+vendor; ou use `make env`)

make setup                  # build + sobe stack + migra
# abre http://localhost
```

Verificação:

```bash
curl http://localhost/api/health   # {"status":"ok"}
# http://localhost/  -> home Nuxt (SSR) com card PrimeVue mostrando o status da API
```

> Sem PHP/Composer no host? Gere o `APP_KEY` dentro do container depois do `make up`:
> `docker compose exec app php artisan key:generate --show` e cole o valor em `.env`, depois `make up` de novo.

## Setup dev (híbrido — hot reload)

Banco/Redis no Docker, Laravel e Nuxt no host:

```bash
docker compose up -d mysql redis          # dados
make front-install                         # deps do Nuxt (1ª vez)

# terminal A — API (host, :8000)
php artisan octane:start --server=frankenphp --port=8000

# terminal B — Nuxt dev (host, :3000, proxy /api -> :8000)
make front-dev
# abre http://localhost:3000
```

> Rodando a API no host contra o MySQL do Docker: use `DB_HOST=127.0.0.1` e a porta forwarded `3307` no `.env` (no container é `DB_HOST=mysql`).

## Comandos `make`

`make` (ou `make help`) lista tudo. Principais:

| Comando | Ação |
|---|---|
| `make setup` | 1ª vez: build + sobe stack + migra |
| `make up` | build (se preciso) + sobe detached |
| `make build` | build das imagens |
| `make down` | para e remove containers (volumes mantidos) |
| `make start` / `make stop` / `make restart` | controle dos containers |
| `make ps` | status dos serviços |
| `make logs` | logs de tudo (`logs-app`, `logs-nuxt`, `logs-queue`) |
| `make migrate` | roda migrations |
| `make migrate-fresh` | dropa tudo e migra de novo |
| `make seed` / `make fresh` | seeders / fresh + seed |
| `make rollback` | desfaz último batch de migration |
| `make test` | suíte de testes Laravel |
| `make optimize` | cache de config/rotas/eventos (rode `octane-reload` depois) |
| `make optimize-clear` | limpa caches |
| `make octane-reload` | reload zero-downtime dos workers |
| `make shell` | shell no container `app` |
| `make tinker` | Laravel Tinker |
| `make artisan c="route:list"` | qualquer comando artisan |
| `make env` | cria `.env` do exemplo + gera APP_KEY (host) |
| `make front-install` / `front-dev` / `front-build` / `front-preview` | Nuxt |
| `make down-volumes` | ⚠️ remove containers **e** volumes (apaga dados do DB) |
| `make prune` | limpa imagens/cache dangling do Docker |

> Comandos de Laravel (`migrate`, `test`, etc.) rodam **dentro do container `app`** — a stack precisa estar `up`.

## Estrutura

```
rmt-system/
  app/ bootstrap/ config/ database/ routes/   # Laravel (API-only)
  routes/api.php          # /api/health, /api/user (auth:sanctum)
  Dockerfile              # imagem FrankenPHP/Octane (backend)
  Caddyfile               # roteamento single-origin
  compose.yaml            # app + nuxt + queue + mysql + redis
  Makefile                # operações
  frontend/               # Nuxt 4 SSR
    app/                  # pages, components, composables
    nuxt.config.ts        # ssr + PrimeVue Aura + runtimeConfig + devProxy
    Dockerfile            # imagem node SSR
  docs/superpowers/       # spec + plano de implementação
```

## Variáveis de ambiente

Backend (`.env`): `APP_KEY`, `DB_*` (`DB_HOST=mysql` no Docker), `REDIS_HOST=redis`, `OCTANE_SERVER=frankenphp`, `SANCTUM_STATEFUL_DOMAINS`, `QUEUE_CONNECTION`.

Frontend (`frontend/.env`): `NUXT_API_BASE` (URL interna p/ SSR), `NUXT_PUBLIC_API_BASE` (base do browser, relativa).

## Testes

```bash
make test                 # dentro do container
# ou no host: php artisan test
```

## Fora de escopo (próximas specs)

Fluxo de login/auth completo (Sanctum SPA cookie + forward de cookie no SSR), CRUD de domínio, CI/CD, TLS/domínio real, testes e2e.
