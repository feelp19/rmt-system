# Design — Laravel API (Octane/FrankenPHP) + Nuxt 4 SSR + PrimeVue

**Date:** 2026-06-03
**Status:** Approved (design), pending implementation plan
**Project:** rmt-system

## Goal

Add a decoupled **Nuxt 4 (SSR)** frontend with **PrimeVue** (styled, Aura theme) to the
existing **Laravel 13** project, with Laravel reduced to an **API backend** running under
**Laravel Octane + FrankenPHP** (worker mode). Everything served on a single origin behind
**Caddy** (FrankenPHP's embedded Caddy): `/api/*` → Laravel, everything else → Nuxt SSR.

## Decisions (locked)

| Topic | Decision |
|---|---|
| Frontend framework | Nuxt 4 (latest), source in `app/` (srcDir) |
| Rendering | SSR (`ssr: true`) |
| Coupling | Decoupled — Nuxt in `/frontend`, Laravel = API |
| UI library | PrimeVue v4 styled mode, **Aura** preset, primeicons; auto-import on |
| App server | Laravel Octane on **FrankenPHP** (worker mode, automatic via Octane) |
| Topology | FrankenPHP unified: replace Sail web service; Caddy routes `/api`→Laravel, `/`→Nuxt |
| API↔front integration | **Dual-URL**: browser calls relative `/api/*` (prod: Caddy → Laravel; dev: Nitro devProxy → Laravel) = no CORS. SSR calls the internal Laravel URL directly |
| Auth | Minimal scaffold now (`install:api` + Sanctum + sample protected route). Full login flow = later spec |
| Async | `queue:work` in a separate container (Octane::concurrently is Swoole-only, unavailable on FrankenPHP) |
| Dev workflow | Hybrid: Nuxt dev `:3000` proxying `/api` → Laravel Octane `:8000` |

## Repo layout (monorepo)

```
rmt-system/
  app/ bootstrap/ config/ database/ ...   # Laravel = API only
  routes/api.php                          # new (install:api)
  Dockerfile                              # FrankenPHP/Octane (backend)
  Caddyfile                               # /api → php_server, * → reverse_proxy nuxt:3000
  compose.yaml                            # rewritten: app + nuxt + queue + mysql + redis
  frontend/                               # Nuxt 4 SSR app
    app/
      pages/index.vue                     # PrimeVue UI hitting /api/health (stack proof)
      composables/useAPI.ts               # baseURL: internal on SSR, '' (relative) on client
    nuxt.config.ts                        # ssr + primevue + runtimeConfig + nitro devProxy
    Dockerfile                            # node SSR
    .env / .env.example
  docs/superpowers/specs/                 # this spec
```

## Component 1 — Backend: Laravel → API + Octane/FrankenPHP

### Purpose
Run Laravel as a resident, high-performance API under Octane/FrankenPHP worker mode; expose
versioned API routes; offload async work to a queue worker.

### Steps
1. `composer require laravel/octane`
2. `php artisan octane:install --server=frankenphp` → writes `config/octane.php`, sets
   `OCTANE_SERVER=frankenphp`. Worker mode is automatic (Octane provides the worker script;
   do **not** hand-write a worker loop).
3. `php artisan install:api` → installs Sanctum, creates `routes/api.php` (auto-prefixed `/api`),
   registers it in `bootstrap/app.php`, publishes `personal_access_tokens` migration. Then
   `php artisan migrate`.
4. Add proof endpoints to `routes/api.php`:
   - `GET /api/health` (public) → `['status' => 'ok']`
   - `GET /api/user` → `auth:sanctum` (sample protected route)
5. Enable Sanctum SPA statefulness in `bootstrap/app.php`:
   `->withMiddleware(fn ($m) => $m->statefulApi())`. Add `HasApiTokens` to `App\Models\User`.
6. Configure `TrustProxies` so `X-Forwarded-*` from Caddy is honored (correct scheme/HTTPS/IP).
7. Strip the now-vestigial Laravel Vite frontend: remove `resources/js`, `resources/css`,
   simplify `resources/views/welcome.blade.php` and `routes/web.php`, drop Vite/Tailwind/
   laravel-vite-plugin from `package.json` and `vite.config.js`. (Laravel keeps no SPA frontend.)

### Local dev run
`php artisan octane:start --server=frankenphp --workers=4 --max-requests=500`
(`--watch` needs `npm i -D chokidar`; use `--poll` on Docker bind mounts; never in prod.)

### Async (queue)
`php artisan queue:work --tries=3 --max-time=3600` runs as its own container/process.
Redis (already in compose) backs the queue. Octane does **not** run the queue.

### config/octane.php (key bits)
```php
'server' => env('OCTANE_SERVER', 'frankenphp'),
'https'  => env('OCTANE_HTTPS', false),
'watch'  => ['app','bootstrap','config/**/*.php','database/**/*.php',
             'public/**/*.php','resources/**/*.php','routes','composer.lock','.env'],
```

### routes/api.php
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);
Route::get('/user', fn (Request $r) => $r->user())->middleware('auth:sanctum');
```

## Component 2 — Frontend: Nuxt 4 SSR + PrimeVue Aura

### Purpose
SSR frontend served at the site root; PrimeVue components; a home page that proves the full
stack by rendering the Laravel `/api/health` result.

### Steps
1. `npm create nuxt@latest frontend` → Nuxt 4, code under `app/`. `cd frontend && npm install`.
2. `npm i primevue @primeuix/themes primeicons` + `npm i -D @primevue/nuxt-module`.
3. Configure `nuxt.config.ts`: `ssr: true`, PrimeVue module + Aura preset, primeicons CSS,
   runtimeConfig (private `apiBase` = internal Laravel URL for SSR; `public.apiBase = ''` so the
   browser uses relative `/api`), and a Nitro **devProxy** so dev browser calls to `/api` reach
   Laravel `:8000` (avoids dev CORS).
4. Add `app/composables/useAPI.ts`: a `useFetch` wrapper whose `baseURL` is the internal
   `apiBase` during SSR (`import.meta.server`) and `''` (relative, same-origin) on the client.
5. `app/pages/index.vue` calls `useAPI('/health')` and renders status in a PrimeVue Card/Button.
6. Build: `npm run build` → `.output/server/index.mjs`. Run: `node .output/server/index.mjs`
   (`PORT=3000 HOST=0.0.0.0`).

### nuxt.config.ts
```ts
import Aura from '@primeuix/themes/aura'

export default defineNuxtConfig({
  ssr: true,
  compatibilityDate: '2025-01-01',
  modules: ['@primevue/nuxt-module'],
  css: ['primeicons/primeicons.css'],
  primevue: {
    options: {
      ripple: true,
      theme: { preset: Aura, options: { darkModeSelector: 'system', cssLayer: false } },
    },
  },
  runtimeConfig: {
    // SSR (internal). Prod: Caddy in the app container. Dev: override NUXT_API_BASE=http://localhost:8000/api
    apiBase: 'http://app/api',                   // NUXT_API_BASE
    public: { apiBase: '/api' },                 // browser: relative same-origin, NUXT_PUBLIC_API_BASE
  },
  // dev only: browser /api → Laravel Octane :8000 (devProxy is not used in the built node server)
  nitro: { devProxy: { '/api': { target: 'http://localhost:8000/api', changeOrigin: true } } },
})
```
> Both bases already include `/api`, so the composable is a single expression. Prod: browser hits
> relative `/api/*` → **Caddy** routes to the Laravel worker. SSR (Nuxt node) hits `apiBase`
> (`http://app/api`) — the same Caddy in the `app` container, which routes `/api` to the worker
> in-process. No Nuxt-side API proxy route needed.

### app/composables/useAPI.ts
```ts
export function useAPI<T>(path: string, opts: Parameters<typeof useFetch>[1] = {}) {
  const config = useRuntimeConfig()
  const base = import.meta.server ? config.apiBase : config.public.apiBase
  return useFetch<T>(`${base}${path}`, opts)
  // SSR  -> http://app/api/health   (config.apiBase + /health)
  // client -> /api/health           (config.public.apiBase + /health)
}
```

### app/pages/index.vue (stack proof)
```vue
<script setup lang="ts">
const { data: health } = await useAPI<{ status: string }>('/health')
</script>

<template>
  <div style="padding: 2rem">
    <Card>
      <template #title>rmt-system</template>
      <template #content>
        <p>API status: <strong>{{ health?.status ?? 'down' }}</strong></p>
        <Button label="Recarregar" icon="pi pi-refresh" @click="refreshNuxtData()" />
      </template>
    </Card>
  </div>
</template>
```

## Component 3 — Docker / Caddy (prod) + dev

### Prod topology (single origin)
```
Caddy (FrankenPHP) :80/:443
  handle /api/*  ->  php_server (Laravel worker, in-process)   # handle, NOT handle_path
  handle         ->  reverse_proxy nuxt:3000 (Nuxt SSR)
```

### Dockerfile (backend — FrankenPHP/Octane)
```dockerfile
FROM dunglas/frankenphp:php8.5 AS base   # verify exact PHP 8.5 tag on Docker Hub
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN install-php-extensions pdo_mysql redis intl zip bcmath pcntl opcache
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . /app
RUN composer dump-autoload --optimize --no-dev \
 && php artisan config:cache && php artisan route:cache && php artisan event:cache
CMD ["php","artisan","octane:frankenphp","--caddyfile=/app/Caddyfile","--admin-port=2019","--workers=4","--max-requests=500"]
```
> The custom `--caddyfile` governs the listener (`:80`/`:443`); do **not** also pass `--port`
> (it would contradict the Caddyfile site address). Internal SSR reaches the API at `http://app/api`.

### Caddyfile
```caddyfile
{
  # Octane injects the FrankenPHP worker line in worker mode.
}

:80 {
  root * /app/public
  encode zstd br gzip

  handle /api/* {
    php_server { try_files {path} index.php }
  }
  handle {
    reverse_proxy nuxt:3000
  }
}
```
> Use `handle /api/*` (not `handle_path`): `handle_path` strips the prefix and breaks Laravel's
> `/api/...` routes.

### frontend/Dockerfile (Nuxt SSR)
```dockerfile
FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:22-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production PORT=3000 HOST=0.0.0.0
COPY --from=build /app/.output ./.output
EXPOSE 3000
CMD ["node", ".output/server/index.mjs"]
```

### compose.yaml (rewrite)
Services: `app` (FrankenPHP/Octane, builds backend Dockerfile, ports 80/443, depends on
`nuxt`+`mysql`+`redis`), `nuxt` (builds `frontend/Dockerfile`, expose 3000), `queue` (backend
image, `queue:work`), `mysql` (8.4, kept), `redis` (alpine, kept). Volumes: `caddy_data`,
`caddy_config`, `sail-mysql`, `sail-redis`.

### Dev workflow (hybrid)
- DB + Redis via Docker (or local).
- Laravel: `php artisan octane:start --server=frankenphp` → `:8000`.
- Nuxt: `cd frontend && npm run dev` → `:3000`, Nitro proxy `/api` → `http://localhost:8000/api`.

## Pinned versions
`nuxt@4.x` · `primevue@4.5.5` · `@primevue/nuxt-module@4.5.5` · `@primeuix/themes@2.0.3` ·
`primeicons@7.0.0` · `laravel/octane@2.x` · `dunglas/frankenphp:php8.5` · `node:22-alpine`.

⚠️ Verify `dunglas/frankenphp:php8.5` tag exists on Docker Hub before pinning (research could
not 100% confirm the exact tag string).

## Out of scope (future specs)
Full login/auth flow (Sanctum SPA cookie + SSR cookie forwarding) · domain CRUD/features ·
CI/CD · real TLS/domain config · e2e tests.

## Acceptance (done when)
1. `docker compose up` brings up app + nuxt + queue + mysql + redis.
2. `GET /api/health` returns `{ "status": "ok" }` through Caddy on the single origin.
3. Nuxt home renders (SSR) a **PrimeVue Aura** component showing the API health status.
4. `queue:work` runs in its own container.
5. Hybrid dev works: Nuxt `:3000` proxies `/api` to Laravel Octane `:8000`.

## Risks / gotchas (from research)
- **FrankenPHP async**: `Octane::concurrently()` + `--task-workers` are Swoole-only → use queues.
- **Copy whole project** into the backend image (not just `public/`) or the app breaks.
- **`handle` vs `handle_path`** for `/api` (prefix must be preserved).
- **`--watch`** needs Node + chokidar; `--poll` on Docker volumes; never in prod.
- **TrustProxies** required behind Caddy for correct scheme/HTTPS/IP and secure cookies.
- **runtimeConfig**: only `public.*` reaches the browser; keep internal URL/secrets private;
  `NUXT_*`/`NUXT_PUBLIC_*` override naming is strict (camelCase → SCREAMING_SNAKE).
- **PrimeVue v4**: no v3-style theme CSS imports; install `@primeuix/themes` + `primeicons`
  explicitly; let the module register PrimeVue (no manual plugin) to avoid double registration.
- **`--max-requests`** so workers recycle; mutate global/static state carefully (app is resident).

## Note
This project is **not** a git repository, so the spec is not committed. Recommend `git init`
before implementation if version control is desired.
