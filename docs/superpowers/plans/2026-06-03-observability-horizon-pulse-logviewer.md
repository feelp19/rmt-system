# Horizon + Pulse + Log Viewer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Laravel Horizon (`/horizon`), Pulse (`/pulse`), and opcodesio/log-viewer (`/log-viewer`) dashboards to the Octane/FrankenPHP Laravel backend, switch the queue and cache to Redis, and run Horizon as the queue worker — all served on the existing single-origin Caddy.

**Architecture:** Three Composer packages installed and published on the host (config/providers/assets committed). Queue moves to Redis; the `queue` compose service becomes `horizon` running `php artisan horizon`. Cache moves to Redis. Caddy's `@laravel` matcher is extended so the dashboard paths + `/livewire*` + `/vendor/*` reach the Laravel worker instead of Nuxt. Authorization gates are scaffolded (open in `local`, locked otherwise).

**Tech Stack:** laravel/horizon 5.x, laravel/pulse 1.x, opcodesio/log-viewer 3.x; Redis (phpredis); FrankenPHP/Octane; Caddy; Docker Compose.

**Reference spec:** `docs/superpowers/specs/2026-06-03-observability-horizon-pulse-logviewer-design.md`

**Testing note:** Dashboards are gated, session-based UIs — not meaningfully unit-testable without an auth flow. Those are verified by HTTP checks in the end-to-end task (Task 8). The existing `HealthTest` is the regression guard. This is called out per task.

**Environment notes:**
- No local MySQL/Redis running — host `artisan` is used ONLY in Task 1 (publish commands; no DB/Redis needed) and runs BEFORE the `.env` Redis switch (Task 2). All DB/Redis-dependent commands (`migrate`, dashboard checks, `horizon`) run INSIDE Docker in Task 8.
- Branch: create a feature branch off `main` (Task 0). git user already configured locally.

---

## File Structure

- Modify `composer.json` / `composer.lock` — add the 3 packages
- Create `config/horizon.php`, `config/pulse.php`, `config/log-viewer.php` (published)
- Create `app/Providers/HorizonServiceProvider.php` (published) — `viewHorizon` gate
- Modify `bootstrap/providers.php` — register HorizonServiceProvider (done by `horizon:install`)
- Modify `app/Providers/AppServiceProvider.php` — `viewPulse` gate + `LogViewer::auth`
- Create `public/vendor/horizon/**`, `public/vendor/log-viewer/**` (published assets, committed)
- Modify `.env` / `.env.example` — `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `CACHE_PREFIX`, `REDIS_CACHE_DB`
- Modify `Dockerfile` — add `posix` extension
- Modify `Caddyfile` — extend the `@laravel` path matcher
- Modify `compose.yaml` — rename `queue` → `horizon`, command `php artisan horizon`
- Modify `Makefile` — `logs-queue` → `logs-horizon`
- Modify `routes/console.php` — `horizon:snapshot` schedule

---

## Task 0: Feature branch

**Files:** none (git).

- [ ] **Step 1: Branch off main**

```bash
cd /home/felipe/Desktop/rmt-system
git checkout main
git checkout -b feat/observability-horizon-pulse-logviewer
```

- [ ] **Step 2: Verify**

Run: `git branch --show-current`
Expected: `feat/observability-horizon-pulse-logviewer`

---

## Task 1: Install + publish the three packages (host)

**Files:** Modify `composer.json`/`composer.lock`; Create `config/horizon.php`, `config/pulse.php`, `config/log-viewer.php`, `app/Providers/HorizonServiceProvider.php`, `public/vendor/horizon/**`, `public/vendor/log-viewer/**`; Modify `bootstrap/providers.php`.

Infra task — verify by file existence (no unit test). These commands need neither DB nor Redis.

- [ ] **Step 1: Require the packages**

Run: `composer require laravel/horizon laravel/pulse opcodesio/log-viewer`
Expected: composer adds `laravel/horizon` (^5), `laravel/pulse` (^1), `opcodesio/log-viewer` (^3) to `require`; install succeeds.

- [ ] **Step 2: Install Horizon (config + provider + assets)**

Run: `php artisan horizon:install`
Expected: creates `config/horizon.php`, `app/Providers/HorizonServiceProvider.php`, registers it in `bootstrap/providers.php`, and publishes assets to `public/vendor/horizon/`.

- [ ] **Step 3: Publish Pulse config**

Run: `php artisan vendor:publish --tag=pulse-config`
Expected: creates `config/pulse.php`. (Pulse migrations load from the package automatically — they are NOT published; they run in Task 8's `migrate`.)

- [ ] **Step 4: Publish Log Viewer config + assets**

Run: `php artisan vendor:publish --tag=log-viewer-config && php artisan log-viewer:publish`
Expected: creates `config/log-viewer.php` and publishes assets to `public/vendor/log-viewer/`.

- [ ] **Step 5: Verify the published artifacts exist**

```bash
ls config/horizon.php config/pulse.php config/log-viewer.php app/Providers/HorizonServiceProvider.php
ls public/vendor/horizon | head
ls public/vendor/log-viewer | head
grep -n HorizonServiceProvider bootstrap/providers.php
```
Expected: all config files + provider present; both asset dirs non-empty; HorizonServiceProvider registered in `bootstrap/providers.php`.

- [ ] **Step 6: Confirm the app still boots (no regression)**

Run: `php artisan route:list --path=horizon | head` and `php artisan test --filter=HealthTest`
Expected: horizon routes listed; HealthTest PASS.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/horizon.php config/pulse.php config/log-viewer.php \
  app/Providers/HorizonServiceProvider.php bootstrap/providers.php public/vendor/horizon public/vendor/log-viewer
git commit -m "feat: install horizon, pulse, log-viewer packages"
```

---

## Task 2: Switch queue + cache to Redis (env)

**Files:** Modify `.env` and `.env.example`.

Infra task — verify by grep. `.env` is git-ignored (edit locally, NOT committed); only `.env.example` is committed.

- [ ] **Step 1: Edit `.env`**

Set / change these keys in `.env` (no duplicates):

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
CACHE_PREFIX=rmt_cache
REDIS_CACHE_DB=1
```
(Keep existing `REDIS_CLIENT=phpredis`, `REDIS_HOST=redis`, `REDIS_PORT=6379`, `SESSION_DRIVER=database`.)

- [ ] **Step 2: Mirror the same keys into `.env.example`**

Apply the identical four keys to `.env.example` (no duplicates).

- [ ] **Step 3: Verify**

```bash
grep -E '^(QUEUE_CONNECTION|CACHE_STORE|CACHE_PREFIX|REDIS_CACHE_DB|SESSION_DRIVER)=' .env
grep -E '^(QUEUE_CONNECTION|CACHE_STORE|CACHE_PREFIX|REDIS_CACHE_DB)=' .env.example
```
Expected: `.env` shows `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `CACHE_PREFIX=rmt_cache`, `REDIS_CACHE_DB=1`, `SESSION_DRIVER=database`; `.env.example` shows the four redis keys. Each key appears once.

- [ ] **Step 4: Commit**

```bash
git add .env.example
git commit -m "feat: move queue and cache to redis"
```

---

## Task 3: Add the posix PHP extension (Dockerfile)

**Files:** Modify `Dockerfile`.

Infra task — verify by build.

- [ ] **Step 1: Edit the extensions line**

In `Dockerfile`, change:

```dockerfile
RUN install-php-extensions pdo_mysql redis intl zip bcmath pcntl opcache
```
to:
```dockerfile
RUN install-php-extensions pdo_mysql redis intl zip bcmath pcntl posix opcache
```

- [ ] **Step 2: Verify the image builds with posix**

```bash
docker build -t rmt-app:obs .
docker run --rm rmt-app:obs php -m | grep -i posix
```
Expected: build succeeds; `posix` listed in loaded modules. (Clean up: `docker image rm rmt-app:obs` optional.)

- [ ] **Step 3: Commit**

```bash
git add Dockerfile
git commit -m "feat: add posix extension for horizon"
```

---

## Task 4: Authorization gates

**Files:** Modify `app/Providers/HorizonServiceProvider.php`, `app/Providers/AppServiceProvider.php`.

Infra/security task — verify by `php -l` + boot.

- [ ] **Step 1: Set the Horizon gate**

In `app/Providers/HorizonServiceProvider.php`, replace the body of the `gate()` method with:

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function (User $user) {
        return in_array($user->email, [
            'flp.pietro19@gmail.com',
        ]);
    });
}
```
Ensure the imports `use Illuminate\Support\Facades\Gate;` and `use App\Models\User;` are present (the published stub already imports them; add if missing).

- [ ] **Step 2: Add the Pulse + Log Viewer gates to AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add imports at the top:

```php
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Opcodes\LogViewer\Facades\LogViewer;
```
and inside `boot()` add:

```php
Gate::define('viewPulse', function (?User $user) {
    return $user?->email === 'flp.pietro19@gmail.com';
});

LogViewer::auth(function ($request) {
    return $request->user()?->email === 'flp.pietro19@gmail.com';
});
```

- [ ] **Step 3: Verify syntax + boot**

```bash
php -l app/Providers/HorizonServiceProvider.php
php -l app/Providers/AppServiceProvider.php
php artisan route:list --path=pulse | head
php artisan test --filter=HealthTest
```
Expected: no syntax errors; pulse routes listed; HealthTest PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Providers/HorizonServiceProvider.php app/Providers/AppServiceProvider.php
git commit -m "feat: authorization gates for horizon, pulse, log-viewer"
```

---

## Task 5: Extend Caddy routing

**Files:** Modify `Caddyfile`.

Infra task — runtime-verified in Task 8; syntax-validated here.

- [ ] **Step 1: Extend the `@laravel` matcher**

In `Caddyfile`, change the matcher line:

```caddyfile
@laravel path /api/* /sanctum/*
```
to:
```caddyfile
@laravel path /api/* /sanctum/* /horizon* /pulse* /log-viewer* /livewire* /vendor/*
```
Leave the `handle @laravel { php_server { ... } }` block and the catch-all `handle { reverse_proxy nuxt:3000 }` unchanged. (Order matters: `@laravel` must stay above the catch-all — it already is.)

- [ ] **Step 2: Validate syntax**

```bash
docker run --rm -v "$PWD/Caddyfile":/etc/caddy/Caddyfile:ro dunglas/frankenphp:php8.5 \
  frankenphp validate --config /etc/caddy/Caddyfile --adapter caddyfile
```
Expected: `Valid configuration`.

- [ ] **Step 3: Commit**

```bash
git add Caddyfile
git commit -m "feat: route horizon/pulse/log-viewer/livewire/vendor to laravel in caddy"
```

---

## Task 6: Compose service rename + Makefile

**Files:** Modify `compose.yaml`, `Makefile`.

Infra task — `compose config` validates here; runtime in Task 8.

- [ ] **Step 1: Rename `queue` → `horizon` and change the command in `compose.yaml`**

Replace the `queue` service block:

```yaml
  queue:
    build:
      context: .
    restart: unless-stopped
    env_file: .env
    command: php artisan queue:work --tries=3 --max-time=3600
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - rmt
```
with:
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

- [ ] **Step 2: Update the Makefile log target**

In `Makefile`, replace the `logs-queue` target:

```makefile
logs-queue: ## Tail queue worker logs
	$(COMPOSE) logs -f queue
```
with:
```makefile
logs-horizon: ## Tail Horizon (queue worker) logs
	$(COMPOSE) logs -f horizon
```
Also update the `.PHONY` line: change `logs-queue` to `logs-horizon`.

- [ ] **Step 3: Validate**

```bash
docker compose config -q && echo "compose ok"
make -n logs-horizon
```
Expected: `compose ok`; `make -n logs-horizon` prints `docker compose logs -f horizon`.

- [ ] **Step 4: Commit**

```bash
git add compose.yaml Makefile
git commit -m "feat: run horizon as the queue service"
```

---

## Task 7: Horizon snapshot schedule

**Files:** Modify `routes/console.php`.

Infra task — verify by `php -l` / schedule:list.

- [ ] **Step 1: Add the schedule entry**

Append to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
```
(If `use Illuminate\Support\Facades\Schedule;` is already imported, do not duplicate it.)

- [ ] **Step 2: Verify**

```bash
php -l routes/console.php
php artisan schedule:list | grep -i horizon
```
Expected: no syntax errors; the `horizon:snapshot` entry appears in the schedule list.

- [ ] **Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat: schedule horizon:snapshot for metrics"
```

---

## Task 8: End-to-end verification (Docker)

**Files:** none (verification only).

- [ ] **Step 1: Build and start the stack**

Run: `docker compose up --build -d`
Expected: services `app`, `horizon`, `nuxt`, `mysql`, `redis` start; mysql/redis become healthy. Use a long timeout (build may take minutes).

- [ ] **Step 2: Run migrations (creates pulse_* tables)**

Run: `docker compose exec -T app php artisan migrate --force`
Expected: migrations run, including `create_pulse_*` tables. Retry once after a few seconds if MySQL is still warming up.

- [ ] **Step 3: Confirm Horizon is running**

```bash
docker compose ps horizon
docker compose logs --tail=20 horizon
```
Expected: `horizon` state Up/running (not restarting); logs show Horizon started (e.g. "Horizon started successfully").

- [ ] **Step 4: Verify the dashboards through Caddy (APP_ENV=local → gates open)**

```bash
BASE=http://localhost
curl -s -o /dev/null -w "horizon:%{http_code}\n"    $BASE/horizon
curl -s -o /dev/null -w "pulse:%{http_code}\n"      $BASE/pulse
curl -s -o /dev/null -w "logviewer:%{http_code}\n"  $BASE/log-viewer
curl -s $BASE/horizon    | grep -o 'id="horizon"'   | head -1
curl -s $BASE/pulse      | grep -oi 'pulse'         | head -1
curl -s $BASE/log-viewer | grep -oi 'log.viewer'    | head -1
```
Expected: all three return `200`; the grep markers print (`id="horizon"`, `pulse`, a `log viewer`/`log-viewer` match). If any returns 403, confirm `APP_ENV=local` in `.env`; if 404, re-check the Caddy matcher (Task 5).

- [ ] **Step 5: Verify no regression (API + SSR home)**

```bash
curl -s http://localhost/api/health
curl -s http://localhost/ | grep -o 'API status: <strong>[^<]*</strong>'
```
Expected: `{"status":"ok"}` and `API status: <strong>ok</strong>` (Nuxt SSR still served by the catch-all).

- [ ] **Step 6: Tear down**

Run: `docker compose down`
Expected: containers removed; named volumes persist.

- [ ] **Step 7: Final commit**

```bash
git add -A
git commit -m "chore: verified horizon/pulse/log-viewer stack end-to-end" --allow-empty
```

---

## Self-Review

**Spec coverage:** packages+publish (T1), Redis queue/cache env (T2), posix ext (T3), gates (T4), Caddy routing incl. `/livewire*` + `/vendor/*` (T5), compose `queue`→`horizon` + Makefile (T6), `horizon:snapshot` schedule (T7), e2e incl. all three dashboards + regression (T8). All spec sections mapped. Out-of-scope items (auth flow, cron container, `pulse:check`, redis ingest) correctly excluded.

**Placeholder scan:** No TBD/TODO; every code/edit step shows exact content; verification commands have explicit expected output.

**Type/name consistency:** Service name `horizon` consistent across compose (T6), Makefile target `logs-horizon` (T6), and e2e checks (T8). Gate names `viewHorizon`/`viewPulse`/`viewLogViewer` (via `LogViewer::auth`) consistent (T4). Caddy paths `/horizon* /pulse* /log-viewer* /livewire* /vendor/*` (T5) match the dashboard URLs checked in T8. Env keys `QUEUE_CONNECTION`/`CACHE_STORE`/`CACHE_PREFIX`/`REDIS_CACHE_DB` consistent (T2, spec).

**Known risks:** Pulse has no `pulse:install` (use `vendor:publish --tag=pulse-config` — T1 Step 3). Pulse is Livewire → `/livewire*` routed (T5). posix required for Horizon (T3). log-viewer API 403-behind-proxy only affects non-local (auth flow out of scope).
