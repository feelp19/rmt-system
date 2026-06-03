---
name: rmt-architecture
description: Padrões arquiteturais do rmt-system — Service Pattern, autorização com Policies, jobs e filas (Horizon), observabilidade (Pulse), cache Redis, integrações externas e convenções de onde cada lógica deve viver.
user-invocable: false
---

# rmt-system — Arquitetura e Padrões

## Quando usar

- Refactor ou criação de Services, Policies, Jobs, cache, filas
- Dúvida sobre "onde essa lógica deve viver" (controller vs service vs policy)
- Configurar ou ajustar Octane, Horizon, Pulse, ou integrações externas

## Fluxo arquitetural obrigatório

```text
Request → FormRequest (rules + authorize) → Controller → Service → API Resource → Response
```

- Controller é thin wrapper: valida, autoriza, chama Service, retorna Resource
- Lógica de negócio vive exclusivamente no Service
- Autorização via Policy + `$this->authorize()`
- Resposta sempre via API Resource (`app/Http/Resources/`)
- rmt é API pura — todo endpoint retorna JSON; nenhuma view PHP é renderizada pelo backend

## Nuances de Eloquent

- Sempre Eloquent ORM; `DB::table`/`DB::raw` apenas com comentário justificando
- Bulk update atômico: `Model::where(...)->update(...)`
- Hidratar 100–500 modelos é negligível; "micro-performance" não é argumento para bypasse do ORM
- Tabelas pivot sem model próprio: criar model ou usar pivot do relacionamento

## API Resource — regras e armadilhas

- Pasta: `app/Http/Resources/<Dominio>/<Nome>Resource.php`
- Resource é apresentação — nunca lógica de negócio
- **Paginação preservando envelope Laravel**: `$paginator->through(fn ($row) => Resource::make($row)->resolve($request))` + `response()->json($paginator)`. Frontend consome `data.data`/`current_page`/`total`.
- **Endpoint com array nu** (sem envelope): `Resource::collection($data)->resolve($request)` + `response()->json(...)`. `::collection()` sozinho embrulha em `{data:[...]}` via `AnonymousResourceCollection`; `$wrap = null` no Resource afeta só `Resource::make($single)`, não collections.
- **Array de resposta montado à mão no Service é red flag** — a mesma entidade deve sair pela MESMA Resource tanto no load quanto na resposta de mutação (store/update). Dois caminhos de serialização divergem em silêncio quando um campo novo entra no model.
- Ao tocar endpoint que retorna `response()->json($model)` diretamente, migrar para Resource no mesmo PR.
- **Nunca serializar `User` raw em payload** — use Resource enxuta que exponha apenas os campos necessários (ex.: `AssigneeResource` com `id, name, avatar_url`). Campos como `email`, `two_factor_confirmed_at` etc. não devem vazar.

## Autorização e escopo

- `FormRequest::authorize()` com checagem real (nunca `return true`)
- Recursos não acessíveis retornam **404** (anti-enumeração, não 403)
- Toda query de recurso de owner/tenant filtra pela coluna correspondente (ex.: `owner_id`, `tenant_id`)
- **Apertar gate compartilhado quebra outros consumidores.** Quando uma Policy method existente é reusada por mais de um controller, criar método novo em vez de apertar o antigo. Antes de modificar, fazer grep pelos consumidores.
- Lógica de "quem pertence ao contexto" vive no Service ou Policy, nunca repetida no Controller.

## Filas e Jobs (Horizon)

O serviço `horizon` no compose (`compose.yaml`) executa `php artisan horizon` com `QUEUE_CONNECTION=redis`. Supervisores definidos em `config/horizon.php`.

**Configuração atual** (`config/horizon.php`):
- `supervisor-1`: fila `default`, balance `auto`, autoScaling por tempo
- Prod: `maxProcesses=10`; Local: `maxProcesses=3`

**Regras de job novo:**
- Escolher fila por **natureza** do trabalho (curta/notificação vs. pesada/lenta vs. integração externa)
- Se a natureza não couber no supervisor existente, criar fila + supervisor antes de mergear
- Operações pesadas nunca síncronas no request
- Jobs devem ser idempotentes e ter `tries` e `timeout` explícitos

**`WithoutOverlapping` — regra obrigatória:**
- Sempre definir `->releaseAfter(N)` com N que espaçe tentativas além do TTL do lock
- `->expireAfter(M)` com M **maior** que o tempo máximo do handler — protege contra lock órfão (worker morto no timeout). Sanidade: `2·N ≳ M`.
- `dontRelease()` apenas quando descartar o job contendido é seguro (idempotente, sem ação distinta perdida)
- O default `releaseAfter=0` recoloca o job na fila sem delay, esgotando `tries` em milissegundos — nunca usar sem valor explícito

**Defense-in-depth em jobs assíncronos:**
- Jobs que entregam conteúdo sensível re-validam autorização no `handle()`, nunca confiam apenas no gate do dispatch (proteção contra downgrade/remoção entre dispatch e execução, CWE-863)
- Se qualquer validação falhar: abortar silenciosamente, sem side-effects

## Pulse (observabilidade)

`config/pulse.php` — storage `database` (driver padrão), retenção de 7 dias.

**Recorders ativos:**
- `CacheInteractions` — hits/misses de cache
- `Exceptions` — exceções da aplicação
- `Queues` — throughput de filas
- `Servers` — CPU/memória do servidor
- `SlowJobs` — jobs lentos (threshold: 1000ms)
- `SlowOutgoingRequests` — chamadas HTTP externas lentas
- `SlowQueries` — queries lentas (threshold: 1000ms)
- `SlowRequests` — requests lentos (threshold: 1000ms)
- `UserJobs` / `UserRequests` — atividade por usuário

**Uso prático:**
- Pulse roda no processo `app` (Octane/FrankenPHP); os dados são gravados no banco via ingest
- `PULSE_ENABLED=false` em teste (`phpunit.xml`)
- Para monitorar um fluxo novo: verificar se o recorder adequado já o cobre; custom cards ficam em `app/Livewire/Pulse/`
- Pulse é read-only de produção — não alterar dados Pulse diretamente

## Octane / FrankenPHP

`config/octane.php` — servidor `frankenphp` (setado via env `OCTANE_SERVER=frankenphp` no compose).

**Regras para ambiente long-running:**
- Sem estado mutável em singleton/service entre requests
- Credenciais passadas por parâmetro no método — não em propriedade interna de serviço
- Usar `app()->scoped(...)` para contexto de request (escopo limpo entre requests pelo Octane)
- **Proibido** `singleton`, `bind`, `instance` para dado de contexto de request — vazam entre requests do mesmo worker
- Listeners e jobs fora do request HTTP **nunca** leem contexto de request — recebem IDs por construtor

**FrankenPHP em produção:**
- Caddy integrado ao FrankenPHP faz roteamento: `/api` → worker PHP; demais → Nuxt (port 3000)
- `max_execution_time` configurado em `config/octane.php` (padrão: 30s)
- Deploy roda `php artisan optimize` para OPcache warming

## Cache e invalidação

- Cache de domínio usa `Cache::tags(...)` para invalidação granular
- `CACHE_STORE=redis` em produção (`CACHE_STORE=array` em teste via `phpunit.xml`)
- Chaves dependentes de autorização precisam ser scoped por owner/tenant
- Mudou dado? invalidar imediatamente com a tag/chave correspondente
- **Nunca usar `Cache::remember()` simples** para dados que dependem de flush por tenant/owner — usar tags

**Exemplos de tag por contexto:**
```php
Cache::tags(['owner.'.$ownerId])->remember('key', ttl, fn () => ...);
Cache::tags(['owner.'.$ownerId])->flush(); // invalida todo o contexto do owner
```

## Integrações externas

- Toda chamada HTTP externa: `->timeout(N)` + `->connectTimeout(M)`
- Em integrações instáveis: `retry()` para `ConnectionException` quando idempotente
- Service de integração centraliza timeout/retry em config (`config/services.php`) e lança exception tipada que o controller traduz em resposta controlada (sem stack trace para o cliente)
- URLs fornecidas pelo usuário passam por validação anti-SSRF antes de qualquer `Http::` (regra `PublicHttpsUrl` ou equivalente): resolver DNS, rejeitar IP privado/loopback/link-local/reservado
- `->withOptions(['allow_redirects' => false])` em chamadas a URL de usuário (impede redirect para IP interno após validação)
- Logs de integração externa: apenas `status` + `request_id`, **nunca** payload completo

## Autenticação e sessão

- rmt usa Sanctum (tabela `personal_access_tokens`)
- Tokens têm abilities que controlam o escopo de acesso
- Identidade de `auth()` nunca vem do request — derivar de `auth()->user()` e binding de rota
- Credentials (tokens, keys) nunca voltam ao frontend depois de salvas — expor apenas flag booleana (`configured: true`)

## Audit e segurança operacional

- Eventos sensíveis (auth, token, acesso privilegiado) registrados em audit log
- Proteger fluxo da aplicação com try/catch — falha de audit nunca quebra o request
- PII: se a entidade armazena dado pessoal, ter comando de prune agendado em `routes/console.php`

## Health check

- `GET /api/health` é o endpoint público de saúde (coberto por `tests/Feature/HealthTest.php`)
- Retorna `{"status": "ok"}` — ponto de referência para o compose healthcheck
- Para adicionar componente monitorado, adicionar rota ou expandir o serviço correspondente

## Não fazer

- Regra de negócio no controller
- `authorize()` vazio/`return true` em FormRequest protegido
- Cache sem estratégia de invalidação
- Evento de domínio antes do commit (usar `DB::afterCommit`)
- Service com estado mutável em ambiente Octane
- `singleton`/`instance` para contexto de request (vaza entre workers Octane)
- Chamar `Http::` em URL de usuário sem validação anti-SSRF
- Usar `response()->json($model)` diretamente — sempre via Resource
- `WithoutOverlapping` sem `releaseAfter` explícito

## Referências rápidas

- Services: `app/Services/`
- Policies: `app/Policies/`
- Resources: `app/Http/Resources/`
- Jobs: `app/Jobs/`
- Horizon config: `config/horizon.php`
- Pulse config: `config/pulse.php`
- Octane config: `config/octane.php`
- Compose: `compose.yaml` (serviço `horizon` → `php artisan horizon`)
