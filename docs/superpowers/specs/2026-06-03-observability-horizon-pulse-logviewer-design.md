# Design — Observabilidade: Horizon + Pulse + Log Viewer

**Date:** 2026-06-03
**Status:** Approved (design), pending implementation plan
**Project:** rmt-system
**Builds on:** `2026-06-03-nuxt-laravel-octane-primevue-design.md`

## Goal

Adicionar três dashboards de observabilidade ao backend Laravel (sob Octane/FrankenPHP,
atrás do Caddy single-origin):

- **Laravel Horizon** — gestão das filas Redis (`/horizon`), substitui `queue:work`.
- **Laravel Pulse** — performance/monitor (`/pulse`), storage em database.
- **opcodesio/log-viewer** — leitor de logs (`/log-viewer`).

Junto: migrar `QUEUE_CONNECTION` e `CACHE_STORE` para Redis.

## Decisões (locked)

| Tópico | Decisão |
|---|---|
| Horizon queue | `QUEUE_CONNECTION=redis`; container de fila roda `php artisan horizon` |
| Pulse storage | `database` (ingest=storage, síncrono, sem worker) |
| Cache | `CACHE_STORE=redis` (session continua `database`) |
| Log viewer | `opcodesio/log-viewer` (dashboard `/log-viewer`) |
| Assets `public/vendor/*` | commitados (funciona host + Docker, sem build step) |
| Serviço compose | `queue` → renomeado `horizon`, command `php artisan horizon` |
| Gates | default (só `local`) + scaffold p/ não-local; em `local` abrem sem login |
| Scheduler | entry `horizon:snapshot` adicionado; container de cron = follow-up |

## Versões (verificadas jun/2026)

`laravel/horizon` 5.x (v5.47.2) · `laravel/pulse` 1.x (v1.7.4) · `opcodesio/log-viewer` 3.x (v3.24.0).
Todas suportam Laravel 13 + PHP 8.5.

## Componente 1 — Pacotes + publish (host, commitado)

```bash
composer require laravel/horizon laravel/pulse opcodesio/log-viewer
php artisan horizon:install                       # config/horizon.php + HorizonServiceProvider + public/vendor/horizon
php artisan vendor:publish --tag=pulse-config     # config/pulse.php
php artisan vendor:publish --tag=log-viewer-config # config/log-viewer.php
php artisan log-viewer:publish                     # public/vendor/log-viewer
```

⚠️ **Pulse não tem `pulse:install`** — install canônico é `vendor:publish` + `migrate`. As
migrations `pulse_*` vêm do pacote (rodadas no e2e/Docker, não localmente — sem DB local).

Arquivos-fonte commitados: `config/horizon.php`, `config/pulse.php`, `config/log-viewer.php`,
`app/Providers/HorizonServiceProvider.php`, assets `public/vendor/horizon/**` e
`public/vendor/log-viewer/**`. (`bootstrap/providers.php` passa a registrar o HorizonServiceProvider.)

## Componente 2 — Redis: queue + cache (`.env` + `.env.example`)

```dotenv
QUEUE_CONNECTION=redis      # Horizon exige (era database)
CACHE_STORE=redis           # era database; session continua database
CACHE_PREFIX=rmt_cache      # pin: Laravel 13 mudou default de prefixo
REDIS_CACHE_DB=1            # isola cache do queue/horizon (db 0)
# já presentes: REDIS_CLIENT=phpredis, REDIS_HOST=redis, REDIS_PORT=6379
```

Notas:
- `php artisan migrate` (no e2e) cria `pulse_*`. Tabela `jobs` (de migrations default) fica
  ociosa — queue agora é Redis. Sem problema.
- Sob Octane, mudança de `.env` exige `octane:reload`/restart do container (workers cacheiam config).

## Componente 3 — Dockerfile (backend)

Adicionar extensão **posix** (Horizon usa pcntl+posix; pcntl e redis já presentes):

```dockerfile
RUN install-php-extensions pdo_mysql redis intl zip bcmath pcntl posix opcache
```

Assets já commitados → `COPY . /app` os inclui; nenhum publish no build.

## Componente 4 — compose.yaml

Renomear o serviço `queue` para `horizon`, trocar o command:

```yaml
  horizon:
    build:
      context: .
    restart: unless-stopped
    env_file: .env
    command: php artisan horizon
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - rmt
```

`app` (web/Octane) e `horizon` (worker) compartilham a mesma imagem; processos separados.

**Efeito colateral**: o `Makefile` tem `logs-queue` apontando p/ o serviço `queue` — renomear
para target `logs-horizon` (serviço `horizon`).

## Componente 5 — Caddyfile

Estender o matcher `@laravel` (resto continua → `reverse_proxy nuxt:3000`):

```caddyfile
@laravel path /api/* /sanctum/* /horizon* /pulse* /log-viewer* /livewire* /vendor/*
```

- `/horizon*` (não `/horizon/*`) p/ casar o root `/horizon` e sub-rotas/API.
- `/livewire*` — **Pulse é Livewire**; runtime + update endpoints ficam em `/livewire/*`.
- `/vendor/*` → `public/vendor/` (assets Horizon/log-viewer); o `vendor/` do composer está
  fora do docroot, inacessível.
- Tudo polling (Horizon XHR, Pulse `wire:poll`, log-viewer XHR) → sem diretiva especial de proxy.

## Componente 6 — Gates de autorização

```php
// app/Providers/HorizonServiceProvider.php
protected function gate(): void
{
    Gate::define('viewHorizon', fn (User $user) => in_array($user->email, [
        'flp.pietro19@gmail.com',
    ]));
}

// app/Providers/AppServiceProvider.php (boot)
Gate::define('viewPulse', fn (?User $user) => $user?->email === 'flp.pietro19@gmail.com');
LogViewer::auth(fn ($request) => $request->user()?->email === 'flp.pietro19@gmail.com');
```

- Default dos três = acessível só em `APP_ENV=local`. No Docker atual (`APP_ENV=local`) abrem
  sem login — conveniente p/ dev/demo.
- Em não-local, exigem usuário autorizado → como não há fluxo de auth ainda, ficam efetivamente
  fechados em prod até a spec de auth. Gates já scaffoldados.

## Componente 7 — Scheduler (parcial)

`routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('horizon:snapshot')->everyFiveMinutes();
```
⚠️ Sem container de scheduler (`schedule:work`/cron) a entry não executa — dashboards funcionam,
só os gráficos de métricas do Horizon ficam vazios. Container de cron = follow-up (fora de escopo).

## Pronto quando (aceite, via Docker)

1. `docker compose up --build` sobe `app` + `horizon` + `nuxt` + `mysql` + `redis`.
2. `docker compose exec app php artisan migrate --force` cria `pulse_*` (sem erro).
3. `GET /horizon` → 200 HTML (dashboard) via Caddy.
4. `GET /pulse` → 200 HTML (dashboard) via Caddy.
5. `GET /log-viewer` → 200 HTML (dashboard) via Caddy.
6. `GET /api/health` → `{"status":"ok"}` e home Nuxt SSR seguem ok (sem regressão).
7. container `horizon` Up rodando `php artisan horizon` (sem crash-loop).

## Riscos / gotchas (da pesquisa)

- **posix obrigatório** p/ Horizon (supervisor/worker). Sem ele `php artisan horizon` falha.
- **Pulse sem `pulse:install`** — usar `vendor:publish --tag=pulse-config`.
- **Pulse = Livewire** → rotear `/livewire*` no Caddy, senão o dashboard quebra.
- **Pulse assets** não vão pra `public/` (servidos por rota) → `/pulse*` deve ir ao PHP.
- **log-viewer API 403 atrás de proxy** (issues #366/#396): em não-local pode precisar `'auth'`
  no `api_middleware` + stateful domains. Em `local` não afeta. Anotar p/ quando houver auth.
- **Octane**: registrar gates/`LogViewer::auth` em `boot()` de provider (roda no boot do worker);
  não guardar estado de request em singletons.
- **Caddy matcher order**: `@laravel handle` deve ficar ANTES do catch-all (já está).
- **`octane:reload`** após mudar `.env`/config (workers long-lived).
- **CACHE_PREFIX pin** evita "cache frio" pela mudança de default do Laravel 13.

## Fora de escopo (specs futuras)

Fluxo de auth/login p/ liberar dashboards em produção · container de scheduler (cron) ·
`pulse:check` (card Servers) · redis ingest do Pulse · alertas/notificações.
