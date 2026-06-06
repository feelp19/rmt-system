---
name: rmt-security
description: Regras de segurança do rmt-system — padrões obrigatórios, anti-IDOR, autenticação Sanctum, CORS/CSRF, upload hardening, SSRF, e checklist antes de PR.
user-invocable: false
---

# rmt-system — Segurança

## Quando usar

- Sempre — toda adição ou mudança de código tem implicação de segurança
- Criação de endpoint, refactor de lógica de negócio, autenticação/autorização
- Qualquer mudança em webhook, integração externa, cache e filas
- Antes de abrir PR (checklist final obrigatório)

## Regra central

**Backend é a autoridade. Frontend só melhora UX.**

## Dinheiro e escrow (marketplace)

- **Valores sempre em centavos inteiros** (`bigInteger`) — nunca float. Taxa via `intdiv`, determinística.
- **Saldo/escrow são fluxos críticos**: `purchase` e `release` usam `DB::beginTransaction` + `lockForUpdate` (na wallet e na order/listing) para evitar venda dupla, saldo negativo e liberação dupla sob concorrência Octane.
- **Idempotência**: confirmar entrega/recebimento duas vezes é no-op; `release` só roda uma vez (guard `status === awaiting_confirmation` dentro do lock).
- **Nunca aceitar `buyer_id`/`seller_id`/valores do request** — comprador vem de `auth()`, vendedor e preço vêm do `Listing` carregado no servidor (cliente não forja preço).
- **Order escopada por participante**: `where(buyer_id = me OR seller_id = me)->firstOrFail()` → 404 para estranho; papel errado (comprador tentando confirmar entrega) → Policy → 403.
- Tokens Sanctum emitidos com `expires_at` (30 dias) — lifecycle obrigatório.

## Ledger de confiabilidade — modelo de confiança

- **Chave HMAC apenas em `.env`** (`LEDGER_HMAC_KEY`) — nunca hardcoded, nunca em resposta ou log. `LedgerService` lê `config('ledger.hmac_key')` a cada chamada e lança exceção se vazia (**fail-closed**).
- **`code` = hash é seguro de exibir**: HMAC-SHA256 com chave secreta não é reversível nem forjável sem a chave; o cliente pode guardar o código para verificação posterior.
- **`prev_hash` nunca exposto**: a `LedgerEntryResource` expõe `code`=hash mas omite `prev_hash` (campo interno da cadeia).
- **Escopo do endpoint verify**: dono da wallet **ou** contraparte da order referenciada. Qualquer mismatch ou hash inexistente retorna **404** (anti-enumeração — nunca 403).
- **Append sempre sob lock**: o gancho do ledger deve estar dentro da transação do caller com a wallet em `lockForUpdate`. Nunca appendar fora de transação.
- **A cadeia detecta adulteração, deleção e truncamento**: HMAC garante integridade de cada linha; elo `prev_hash` detecta remoção ou reordenação; `ledger_head_hash` + `ledger_seq` na wallet detectam truncamento da cauda.

## Webhook PushinPay (PIX) — gateway SEM assinatura

A PushinPay **não assina** o webhook (sem HMAC). Modelo de confiança adotado:

1. **Secret na URL**: rota `/api/webhooks/pushinpay/{token}`; `hash_equals($token, config('services.pushinpay.webhook_secret'))`, mismatch → **404** (anti-enumeração). Secret só em `.env` (`PUSHINPAY_WEBHOOK_SECRET`).
2. **Nunca confiar no corpo** (rmt-security: "re-buscar do servidor usando o identificador"): o controller pega só o `id` e despacha job; o job chama `getTransaction(id)` (fonte autoritativa) antes de creditar.
3. **Idempotência**: `pix_charges.pushinpay_id` **unique** + `confirmPaid` com guard de status sob `lockForUpdate` → credita a carteira **uma vez** mesmo com webhook + reconcile + polling competindo.
4. **Sempre 200** ao webhook (mesmo desconhecido/já processado) — não induzir re-tentativa por erro de parse nosso. Rota com `throttle`.
5. Token PushinPay e webhook secret **só em `.env`** (gitignored), lidos via `config('services.pushinpay.*')` — nunca hardcoded, nunca em resposta/log. `Http::` loga só `status`/`id`.

## Regras obrigatórias — endpoint e autorização

- Controller é thin wrapper: `FormRequest` + `authorize()` + Service
- Toda mutação usa `FormRequest` dedicada (nunca `$request->validate()` inline)
- `FormRequest::authorize()` faz checagem real (nunca `return true`)
- **Nunca aceitar `user_id`/`owner_id`/qualquer identity do request** — derivar de `auth()->id()` e binding de rota
- Recursos inacessíveis retornam **404** (anti-enumeração), **nunca 403**
- Toda Policy de recurso filho filtra pela coluna de owner/tenant antes de qualquer operação

## Escopo e anti-IDOR

- Toda query de recurso filtra pelo owner/tenant correto
- Em controllers de sub-recurso, validar cada elo da hierarquia:
  1. `recurso_filho.owner_id === owner.id`
  2. `sub_recurso.parent_id === recurso_filho.id`
- Retornar **404**, nunca 403, em mismatch de hierarquia (anti-enumeração)
- **Cross-owner IDOR guard em pivot** (CWE-639): quando um endpoint aceita array de FKs filhos de um owner, filtrar pelo `owner_id` antes do `sync()`:
  ```php
  $accepted = ChildModel::where('owner_id', $owner->id)
      ->whereIn('id', $input)
      ->pluck('id');
  $parent->children()->sync($accepted);
  ```
  Sem o filtro, atacante posta IDs de outro contexto e a pivot aceita silenciosamente.
- **Double IDOR guard em recurso aninhado**: em endpoint que recebe dois níveis de hierarquia, validar cada elo com `abort_if(...!== ..., 404)`:
  ```php
  abort_if($child->parent_id !== $parent->id, 404);
  abort_if($grandchild->child_id !== $child->id, 404);
  ```
  Sempre 404, nunca 403.

## Sanctum — tokens e abilities

- rmt usa Sanctum (`personal_access_tokens`) para autenticação API
- Tokens têm abilities; verificar a ability correta no middleware/gate antes de processar
- Ciclo de vida obrigatório: `created_at`, `last_used_at`, `expires_at` em qualquer token gerenciado
- Token expirado é rejeitado com audit — não apenas ignorado
- `last_used_at` atualizado com throttle (cache 60s) para não spammar o banco
- Regeneração limpa hash + lifecycle fields juntos
- Credenciais (tokens, API keys) **nunca voltam ao frontend** depois de salvas — expor apenas `{configured: true}`

## CSRF e CORS — SPA + API em single-origin

rmt usa **single-origin** via Caddy: `/api/*` é roteado para o worker Laravel; todo o resto vai para o Nuxt (port 3000). O browser vê um único origin — portanto:

- Requests do Nuxt SSR para `/api` são **same-origin** no browser — sem problema de CORS para o cliente
- CORS só é necessário para clientes externos (apps mobile, integrações de terceiros, ferramentas CLI)
- Para SPA Nuxt em produção (same-origin): Sanctum em modo `stateful` não se aplica — usar token no header `Authorization: Bearer`
- **CSRF stateless**: API pura com Bearer token não precisa de CSRF cookie; garantir que rotas de API estão fora do grupo `web` (que usa `VerifyCsrfToken`)
- Se adicionar cliente de origin diferente: configurar `config/cors.php` com origin explícito — nunca `'*'` em produção com Sanctum

## Concorrência e idempotência

- Fluxos críticos usam `DB::transaction()` + `lockForUpdate()`
- Webhooks com deduplicação: `Cache::lock(...)` + dedupe-check via tabela de log + `try { LogModel::create } catch (UniqueConstraintViolationException)` para fechar race entre dedupe e INSERT
- Jobs de despacho periódico: lock distribuído (`Cache::lock` ou `WithoutOverlapping`)
- Eventos de estado (mudança de status, persistência concluída) somente em `DB::afterCommit(...)`

## Input, saída e dados sensíveis

- Texto livre web: `htmlspecialchars(strip_tags(...), ENT_QUOTES | ENT_HTML5, 'UTF-8')`
- **Nunca confiar em "blob de dados verificados externamente"** vindo do cliente — re-buscar do servidor usando o identificador (cache barato); cliente não pode forjar dados de fonte oficial
- Credenciais em colunas de banco: cast `'encrypted'` no model + `$hidden` (defesa contra DB dump e leak via `toArray()`)
- Para checar "está configurado?" sem decryptar: `$model->getRawOriginal('coluna') !== null`
- Logs: apenas `status` + `request_id`, **nunca** payload financeiro/sensível completo
- Shared props globais devem minimizar PII — `auth.user` sem campos desnecessários
- Se houver PII, registrar retenção e prune no vault/Compliance do projeto

## Anti-SSRF em URLs de input

- Toda URL fornecida pelo usuário que será consumida via `Http::` precisa validação anti-SSRF
- Resolver DNS (A + AAAA) e rejeitar IP privado/loopback/link-local/reservado (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`)
- Bloquear explicitamente: `localhost`, `169.254.169.254` (AWS metadata), `metadata.google.internal`
- `->withOptions(['allow_redirects' => false])` em chamadas a URL de usuário — impede redirect para IP interno após validação
- Validação de string (`starts_with:https://`, `parse_url`) checa apenas a string, não o IP resolvido — nunca suficiente sozinha

## Upload hardening — quatro camadas obrigatórias

**Regra central**: todo upload é hostil até prova em contrário.

> **Implementado**: `App\Services\ImageUploadService` faz as 4 camadas (FormRequest `image|mimes|max` → `finfo` magic bytes → reprocessa via **GD** pra WebP → disco **privado** `local` + nome `Str::random(40).webp`). Servido por endpoint **id-based** (`/api/listings/{id}/photo`, `/api/users/{id}/avatar`) com `X-Content-Type-Options: nosniff` — **nunca** via `/storage` (no single-origin do Caddy, `/storage` cai no Nuxt). `ext-gd` está no Dockerfile.

**Camada 1 — FormRequest na borda:**
- `mimes:` (Laravel checa MIME guess + extensão) + `max:N` — bloqueia ~95% do tráfego abusivo antes de tocar PHP/disco
- Não suficiente sozinho — `mimes:` aceita `Content-Type` declarado pelo cliente

**Camada 2 — MIME real via `finfo` no Service:**
- `(new \finfo(FILEINFO_MIME_TYPE))->file($realPath)` — lê magic bytes, ignora declarações do cliente
- Whitelist explícita de MIMEs permitidos — **nunca blacklist**
- Não usar `$file->getMimeType()` sem complemento — vem do header HTTP, falsificável
- SVG fora da whitelist por padrão — pode conter `<script>` e `<foreignObject>`

**Camada 3 — reprocessamento (anula polyglot):**
- Imagens passam por Intervention (`scaleDown → toWebp`) — a saída é um arquivo novo, byte por byte
- Salvar com extensão controlada pelo backend (`.webp`), nunca a extensão original
- Nome no disco: random (`uniqid`), nome original do usuário nunca vira parte do path

**Camada 4 — disposition + storage:**
- Storage privado (S3 `visibility => 'private'`)
- `Content-Disposition: attachment` para arquivos baixáveis; `inline` apenas para imagens que precisam renderizar
- Servir via endpoint que valida permissão — não expor URL S3 direta na resposta
- Filename original sanitizado (remove null bytes, força basename, colapsa whitespace, limita 255 chars)

## Webhooks

- Validar **antes** de qualquer processamento
- HMAC real: usar body raw (`$request->getContent()`), nunca `$request->all()`
- Token estático: `hash_equals` — compensar com deduplicação tripla + índice único + re-verificação no job
- Resposta 200 para webhook já processado (`already_processed`) — não re-processar

## Integrações externas — regras de segurança

- Toda chamada `Http::` com `timeout()` e `connectTimeout()`
- Capturar `ConnectionException` e retornar erro controlado (503) sem stack trace para o cliente
- **Nunca devolver `$e->getMessage()` cru** de exceção Guzzle/HTTP ao usuário — logar completo, expor mensagem genérica
- Rate limit em rotas de auth/integração

## Render de HTML/markdown de usuário (XSS)

- `v-html` exige escape + DOMPurify com allowlist mínima explícita
- `<a>` permitido: `ALLOWED_TAGS: ['a']`, `ALLOWED_ATTR: ['href','target','rel']`, `ALLOWED_URI_REGEXP: /^https?:\/\//i`, **`ADD_URI_SAFE_ATTR: ['target','rel']`** (obrigatório — sem ele, DOMPurify silenciosamente remove `target/_blank` e `rel/noopener`)
- Esquemas aceitos: `http(s)://` apenas; link externo: `target="_blank" rel="noopener noreferrer"`
- Tiptap com `html: false` + `validate` no LinkExtension para filtrar `javascript:` e `data:`

## Security headers

- `SecurityHeaders` middleware cobre CSP+nonce, HSTS, XFO, Referrer-Policy, Permissions-Policy, COOP, CORP
- `X-Powered-By` desligado via `expose_php = Off` em ini de hardening
- `X-Robots-Tag: noindex, nofollow, noarchive` em rotas autenticadas (`auth`, `auth:*`)
- `/horizon`, `/pulse`, `/log-viewer` sempre com header noindex

## Indexação de rotas privadas

- `public/robots.txt` com `Disallow:` para todos os prefixos autenticados
- Atualizar quando criar nova superfície privada (`/api/`, `/webhooks/`, `/horizon`, `/pulse`, `/log-viewer`)

## Defense-in-depth — princípio geral

- Cada camada (FormRequest → Policy → Service → Job) valida independentemente
- Falha numa camada não cria gap de segurança — as demais seguram
- "Lock de is_default" em três camadas: Policy retorna `false` + Service lança `DomainException` + UI desabilita
- Jobs que entregam conteúdo sensível re-validam autorização no `handle()` (ver rmt-architecture)

## O que nunca fazer

- `return true` em `authorize()` de FormRequest
- Query de recurso sem escopo de owner/tenant
- Retornar 403 para recurso que o usuário não deveria saber que existe (usar 404)
- Processar webhook sem validar assinatura
- Operação crítica sem lock transacional
- Credencial em props ou logs
- `Cache::remember()` simples para dados que dependem de flush por owner/tenant
- Logar payload financeiro/sensível completo
- Manter credencial mutável em propriedade de service sob Octane
- Chamar `Http::` em URL de usuário sem validação anti-SSRF
- Criar token sem `created_at`/`last_used_at`/`expires_at`
- Aceitar upload sem as 4 camadas (FormRequest + `finfo` + reprocessamento + storage privado)
- Salvar arquivo com extensão fornecida pelo usuário
- Aceitar `image/svg+xml` sem sanitização específica
- Aceitar `user_id`/`owner_id` do request — derivar de `auth()->id()`

## Checklist antes de PR

- [ ] Endpoint novo/mudado tem `FormRequest` e `authorize()` real
- [ ] Controller delega regra de negócio ao Service — sem lógica no controller
- [ ] Queries têm escopo correto de owner/tenant e retornam 404 em mismatch
- [ ] Mutações sensíveis têm `throttle` configurado
- [ ] Fluxos concorrentes têm `transaction + lockForUpdate` ou lock distribuído
- [ ] Webhooks validam assinatura com body raw
- [ ] Dados cacheados usam tags corretas e são invalidados ao mutar
- [ ] Nenhum token/chave/campo sensível em props, shared ou logs
- [ ] Upload tem as 4 camadas: FormRequest + `finfo` (whitelist) + reprocessamento + storage privado + nome random
- [ ] URL de usuário passa por validação anti-SSRF antes de `Http::`
- [ ] Tokens Sanctum têm `created_at`/`last_used_at`/`expires_at`
- [ ] CORS configurado com origin explícito se cliente externo precisar acessar a API
- [ ] Rota nova privada/autenticada adicionada ao `robots.txt` e cobre `X-Robots-Tag`
- [ ] Gancho de ledger está dentro da transação do caller com wallet em `lockForUpdate`
- [ ] `LEDGER_HMAC_KEY` definida em `.env` (não vazia — LedgerService é fail-closed)
- [ ] Endpoint de ledger/verify retorna 404 (nunca 403) em mismatch de escopo ou hash inexistente
