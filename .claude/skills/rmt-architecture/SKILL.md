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

## Domínio marketplace — Services, Policies e escrow

Services em `app/Services/`:

- **`WalletService`** — `walletFor` (firstOrCreate), `deposit`. Depósito é escrita única → `increment()` atômico, **sem** transação (regra 8).
- **`ListingService`** — `create`, `cancel` (lança `DomainException` se não-ativo).
- **`OrderService`** — núcleo do escrow:
  - `purchase(User $buyer, Listing $listing)`: valida (não comprar próprio anúncio, anúncio ativo, saldo). **`DB::beginTransaction` + `lockForUpdate`** no listing e na wallet do comprador (escritas em wallets+orders+listings = tabelas distintas inter-dependentes). Debita comprador, cria Order `awaiting_confirmation`, marca listing `sold`.
  - `confirmDelivery` / `confirmReceipt` → `registerConfirmation(order, coluna)`: transação + `lockForUpdate` na order, idempotente (no-op se já não está `awaiting_confirmation`). Quando **ambos** `*_confirmed_at` preenchidos → `release`.
  - `release` (privado): credita `seller_payout_cents` na wallet do vendedor, status → `completed`. A taxa (`fee_cents`, 5% via `config('marketplace.fee_percent')`, `intdiv` em centavos) é retida pela plataforma (MVP não credita conta de plataforma).
- **Dinheiro sempre em centavos inteiros** — nunca float. Taxa: `intdiv($amountCents * $feePercent, 100)`.
- **`ImageUploadService`** — upload hardening em 4 camadas (FormRequest `image|mimes|max` → `finfo` magic bytes → **reprocessa via GD pra WebP** (anula polyglot) → disco **privado** `local`, nome `Str::random(40).webp`). Servido por endpoint id-based (`GET /api/listings/{id}/photo`, `/api/users/{id}/avatar`) com `nosniff` (nunca `/storage`, que vai pro Nuxt no single-origin). Reusado por anúncio e avatar. Requer `ext-gd` (no Dockerfile).
- **`XpService`** — XP/níveis/perks. `award(userId, amount)` (increment atômico). Concede: vender +50, comprar +20 (em `OrderService::release`), turbinar +15 (`BoostService`), 1º anúncio +10 (`ListingService::create`, uma vez). `levelForXp`/`progress` (faixas 0/100/300/600/1000…). **Perk**: `feeBpsForLevel` reduz a taxa do escrow pelo nível do **vendedor** (base 5%, piso 3%) — aplicado em `OrderService::feeFor($amount, $seller)` em basis points (`intdiv($amount*$bps, 10000)`). Ranking público em `GET /api/leaderboard`.
- **`ListingService`** — `create` (recebe `UploadedFile $photo`, obriga foto), `update` (só ativo; troca foto e apaga a antiga), `cancel`.
- **`BoostService`** (destaque pago) — `purchaseWithWallet(User, Listing, BoostTier)`: transação + `lockForUpdate` na carteira, checa boost ativo sob lock, debita preço do tier (`BoostTier::priceCents()`), cria `Boost` ativo (`now` → `+config('marketplace.boost_days')`). Máx. 1 boost ativo/anúncio. Inc 2 (PIX) reusa `createActiveBoost` após confirmação.

## Ledger de confiabilidade (HMAC encadeado por carteira)

Todo movimento de dinheiro grava uma entrada imutável na tabela `ledger_entries` com cadeia HMAC-SHA256 por wallet — para rastreabilidade, tamper-evidence e legitimidade.

### `App\Services\LedgerService`

Stateless (Octane-safe). Lê `config('ledger.hmac_key')` a cada chamada — **fail-closed**: lança exceção se a chave estiver vazia.

**Métodos públicos:**

| Método | Descrição |
|---|---|
| `record(Wallet $lockedWallet, LedgerEntryType, LedgerDirection, int $amount, int $balanceAfter, ?string $refType, ?int $refId): LedgerEntry` | Grava a linha, computa prev_hash/seq da cabeça da wallet, atualiza `ledger_head_hash` e `ledger_seq`. **Precondição obrigatória**: deve ser chamado dentro da transação do caller, com a wallet já em `lockForUpdate`. |
| `signatureValid(LedgerEntry $entry): bool` | Recomputa o HMAC e compara com `$entry->hash`. |
| `verifyEntry(LedgerEntry $entry): bool` | Valida assinatura + elo (busca a entrada anterior `(wallet_id, seq-1)` e verifica que `prev_hash` bate). |

**HMAC-SHA256** é calculado sobre o JSON canônico de ordem fixa dos campos: `wallet_id, seq, type, direction, amount_cents, balance_after_cents, reference_type, reference_id, prev_hash` — **sem** `created_at` (criação fora do campo de hash).

Config: `config/ledger.php` → `hmac_key` => `env('LEDGER_HMAC_KEY')`. Variável obrigatória em `.env` e `phpunit.xml` (valor de teste).

### Pontos de gancho — onde o ledger é appendado

Append sempre **dentro da transação existente do caller**, com a wallet já em `lockForUpdate`:

| Serviço / método | Tipo de entrada | Observação |
|---|---|---|
| `WalletService::deposit` | `DepositCredit` | `deposit` passou a usar `DB::beginTransaction` + `lockForUpdate` (antes era `increment` lock-free) |
| `OrderService::purchase` | `EscrowDebit` | wallet do comprador já em lock |
| `OrderService::release` | `EscrowReleaseCredit` | wallet do vendedor em lock |
| `PixChargeService::confirmPaid` | `PixTopupCredit` | wallet e charge em lock |
| `BoostService::purchaseWithWallet` | `BoostDebit` | wallet em lock |

Idempotência: o append ocorre no caminho one-shot sob guard de status/lock — nunca duplica linha.

### Comando de verificação

`php artisan ledger:verify {--wallet=}` (`App\Console\Commands\VerifyLedgerCommand`): percorre cada cadeia (ou a wallet indicada), validando HMAC + elo de prev_hash + seq contíguo + cabeça da wallet. Sai com código ≠ 0 se detectar adulteração, truncamento ou lacuna de sequência.

### API do ledger

| Rota | Controller | Resource | Descrição |
|---|---|---|---|
| `GET /api/wallet/ledger` | `Wallet\WalletLedgerController` | `Marketplace\LedgerEntryResource` | Extrato paginado, escopado por `user_id`; expõe `code`=hash, **nunca** `prev_hash` |
| `GET /api/ledger/{hash}/verify` | `Marketplace\LedgerVerificationController` | `LedgerVerificationResource` | Escopo: dono da wallet **ou** contraparte da order referenciada; mismatch/inexistente → 404 anti-enumeração |

Ambas com `throttle:60,1`. Rota de verify com `whereAlphaNumeric('hash')`.

**Autorização — `LedgerPolicy`** (`app/Policies/LedgerPolicy.php`): `viewAny` (qualquer auth — o extrato escopa por `user_id`) e `view(User, LedgerEntry)` (dono da wallet **ou** contraparte da order referenciada). Registrada **explicitamente** via `Gate::policy(LedgerEntry::class, LedgerPolicy::class)` no `AppServiceProvider::boot()` — auto-discovery espera `LedgerEntryPolicy`, não casa com o nome `LedgerPolicy`. O verify chama a policy via `$request->user()->cannot('view', $entry)` + `abort(404)` (preserva 404 anti-enumeração da regra 4 — `authorize()` devolveria 403). O extrato usa `$this->authorize('viewAny', LedgerEntry::class)`.

**Ordenação por boost sem `DB::raw`** (regra 5): `ListingController::index`/`featured` usam `addSelect(['grid_boost_weight' => Boost::select('weight')->whereColumn('listing_id','listings.id')->active()->...->limit(1)])` + `->orderByDesc($alias)`. Eager load `activeBoost` (HasOne `->active()->latestOfMany()`) p/ o badge. `whereHas('boosts', fn($q)=>$q->active())` filtra o destaque.

## Pagamentos PIX (PushinPay) — integração externa

- **`PushinPayService`** (gateway): `createPix(centavos, webhookUrl)` → `POST {base}/api/pix/cashIn`; `getTransaction(id)` → `GET {base}/api/transactions/{id}`. `Http::withToken(config)->connectTimeout(5)->timeout(15)`; falha → `PushinPayException` (→ 503 no `bootstrap/app.php`). **Octane-safe**: lê `config('services.pushinpay.*')` a cada chamada, sem estado em propriedade. Loga só `status`/`id`, nunca o payload.
- **`PixChargeService`**: `createForTopUp` (cria cobrança + `PixCharge` `created`, `expires_at=+30min`); `refreshFromGateway` (re-consulta status — **fonte autoritativa**); `confirmPaid` (transação + lock no charge **e** na wallet, credita **uma vez** — idempotente via guard de status); `handleWebhookById` (usado pelo job).
- **Webhook sem HMAC** (a PushinPay não assina): defesa em camadas — (1) secret na URL (`/webhooks/pushinpay/{token}`, `hash_equals`, mismatch → 404); (2) **nunca confiar no corpo** — pega só o `id` e o job re-consulta o status pela API; (3) idempotência (`pushinpay_id` unique + guard de status). Ver [[ADR — webhook PushinPay sem assinatura]].
- **Jobs (fila `payments`, supervisor `supervisor-payments` no `config/horizon.php`)**:
  - `ProcessPushinPayWebhookJob` — `tries=5`, `backoff`, re-verifica + credita (idempotente).
  - `ReconcilePendingPixChargesJob` — agendado (`everyFiveMinutes` em `routes/console.php`), `WithoutOverlapping` (`releaseAfter`/`expireAfter`/`dontRelease`): expira vencidas + re-consulta pendentes (fallback caso o webhook não chegue).
  - `ExpireBoostsJob` — diário, marca boosts vencidos `expired`.
- **Confirmação local sem webhook público**: `PixChargeController::show` re-consulta o gateway (throttle 3s via `Cache::lock`) enquanto `created` — o frontend faz polling. Em prod o webhook + reconcile cobrem.
- ⚠️ Os jobs agendados exigem um runner de scheduler (`schedule:work`/cron) — hoje não há container dedicado; o `show` on-demand cobre o fluxo local.

Policies (auto-discovery Laravel): **`ListingPolicy`** (`create` qualquer auth, `delete` só dono) e **`OrderPolicy`** (`view` partes, `confirmDelivery` vendedor, `confirmReceipt` comprador). Controllers escopam a query por participante (`buyer_id`/`seller_id`) e `firstOrFail` → 404 para estranho; papel errado dentro do pedido → `$this->authorize(...)` → 403.

`DomainException` → 422 JSON (mapeado em `bootstrap/app.php`).

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
