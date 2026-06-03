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
- **Domínio ainda mínimo (sem entidades de negócio além de User). As convenções abaixo são direção para quando o domínio crescer.**

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
- `GET /api/health` — health check público, retorna `{"status":"ok"}`
- `GET /api/user` — usuário autenticado (middleware `auth:sanctum`)

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
