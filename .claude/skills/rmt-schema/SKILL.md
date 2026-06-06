---
name: rmt-schema
description: Schema do banco de dados do rmt-system — models, tabelas, PKs, relações, enums e casts. Use ao trabalhar com migrations, Eloquent ou lógica envolvendo dados.
user-invocable: false
---

# rmt-system — Schema do Banco de Dados

## Quando usar

- Criar ou alterar migrations, models, casts ou enums
- Trabalhar com queries Eloquent ou relações entre models
- Revisar impacto de schema antes de criar uma feature
- Entender quais tabelas existem e quais são do framework vs. domínio

## Mapa de domínio

Domínio **marketplace de itens/gold de jogos** (MVP). Usuário anuncia (`Listing`),
outro compra gerando `Order` com **escrow de dupla confirmação**: o valor sai da
`Wallet` do comprador e fica retido na Order até vendedor (entregou) **e** comprador
(recebeu) confirmarem; aí o vendedor recebe `amount - taxa(5%)`. Carteira é simulada
(sem gateway), valores sempre em **centavos inteiros**.

Atualizar este skill ao adicionar models/migrations: registrar PK, relações, enums e casts de cada novo model aqui.

## Hierarquia

```
User ──< Listing (seller_id)
User ──< Order   (buyer_id / seller_id)
User ──1 Wallet  (user_id, único)
User ──< Boost   (user_id = anunciante)
Listing ──< Order (listing_id)
Listing ──< Boost (listing_id) ; Listing ─1 activeBoost (hasOne ativo)
```

## Modelos

### User

| Campo | Tipo |
|---|---|
| `id` | BigInt (auto-increment) |
| `name` | string |
| `email` | string unique |
| `email_verified_at` | timestamp nullable |
| `password` | string (hash) |
| `remember_token` | string nullable |
| `created_at` / `updated_at` | timestamps |

- **Tabela**: `users`
- **PK**: `id` (BigInt auto-increment)
- **Relações**: `HasApiTokens` (Sanctum — `personal_access_tokens`), `Notifiable`
- **Enums**: nenhum
- **Casts**: `email_verified_at → datetime`, `password → hashed`
- **Fillable**: `name`, `email`, `password`
- **Hidden**: `password`, `remember_token`
- **Traits**: `HasApiTokens`, `HasFactory`, `Notifiable`
- **Relações de domínio**: `wallet` (HasOne), `listings` (HasMany seller_id), `purchases` (HasMany buyer_id), `sales` (HasMany seller_id), `boosts` (HasMany)
- **Campos novos**: `avatar_path` (nullable, disco privado, servido via `/api/users/{id}/avatar`), `xp` (unsignedBigInteger default 0, indexado p/ ranking). Nível/perk derivam de `xp` via `XpService` (não há coluna de nível). `avatar_path`/`name`/`email`/`password` fillable; **`xp` NÃO é fillable** (só `XpService::award` incrementa).

### Wallet

- **Tabela**: `wallets`
- **PK**: `id` (BigInt auto-inc)
- **Relações**: `user` (BelongsTo, `user_id` único)
- **Enums**: nenhum
- **Casts**: `balance_cents → integer`
- **Fillable**: `user_id`, `balance_cents`
- **Campos de integridade do ledger** (NÃO estão no `$fillable` — gerenciados exclusivamente pelo `LedgerService`):
  - `ledger_head_hash` (char(64) nullable) — hash da última entrada da cadeia (âncora anti-truncamento)
  - `ledger_seq` (uBigInt, default 0) — sequência da última entrada gravada
- Relação: `ledgerEntries` (HasMany `LedgerEntry`)
- Saldo disponível em **centavos inteiros** (`bigInteger`, default 0). Uma carteira por usuário (índice unique em `user_id`). Criada no registro (`WalletService::walletFor`).

### Listing (anúncio)

- **Tabela**: `listings`
- **PK**: `id` (BigInt auto-inc)
- **Relações**: `seller` (BelongsTo User, `seller_id`), `orders` (HasMany)
- **Enums**: `type → ListingType` (coluna `type`), `status → ListingStatus` (coluna `status`)
- **Casts**: `type → ListingType`, `status → ListingStatus`, `quantity → integer`, `price_cents → integer`
- **Fillable**: `seller_id`, `game`, `type`, `title`, `description`, `quantity`, `price_cents`, `status`
- `game` é texto livre (qualquer jogo). `price_cents` = preço total do anúncio. `photo_path` (nullable, disco privado, servido via `/api/listings/{id}/photo`) — **obrigatório na criação** (antigos = null → placeholder). Índices: `(status, game)`, `seller_id`.

### Order (transação / escrow)

- **Tabela**: `orders`
- **PK**: `id` (BigInt auto-inc)
- **Relações**: `listing` (BelongsTo), `buyer` (BelongsTo User `buyer_id`), `seller` (BelongsTo User `seller_id`)
- **Enums**: `status → OrderStatus`
- **Casts**: `status → OrderStatus`, `amount_cents`/`fee_cents`/`seller_payout_cents → integer`, `seller_confirmed_at`/`buyer_confirmed_at`/`completed_at → datetime`
- **Fillable**: `listing_id`, `buyer_id`, `seller_id`, `amount_cents`, `fee_cents`, `seller_payout_cents`, `status`, `seller_confirmed_at`, `buyer_confirmed_at`, `completed_at`
- `seller_id` é denormalizado do listing (escopo de query/IDOR). Valores travados no momento da compra. Dupla confirmação = ambos `*_confirmed_at` preenchidos → `release` credita o vendedor. Índices: `buyer_id`, `seller_id`, `status`.

### Boost (destaque pago)

- **Tabela**: `boosts`
- **PK**: `id` (BigInt auto-inc)
- **Relações**: `listing` (BelongsTo), `user` (BelongsTo = anunciante). Em `Listing`: `boosts` (HasMany) + `activeBoost` (HasOne `->active()->latestOfMany()`).
- **Enums**: `tier → BoostTier`, `status → BoostStatus`, `payment_method → BoostPaymentMethod`
- **Casts**: enums acima, `weight`/`price_cents → integer`, `starts_at`/`expires_at`/`paid_at → datetime`
- **Fillable**: `listing_id`, `user_id`, `tier`, `weight`, `price_cents`, `payment_method`, `status`, `starts_at`, `expires_at`, `paid_at`
- `weight` é denormalizado do tier (`BoostTier::weight()`) p/ ordenação por subquery sem `DB::raw`. Scope `active()` = status `active` E `expires_at > now`. Dura `config('marketplace.boost_days', 7)` dias. Índices: `(status, expires_at)`, `listing_id`.

### LedgerEntry (ledger de confiabilidade — append-only)

- **Tabela**: `ledger_entries` (append-only; nunca atualizar ou deletar linhas)
- **PK**: `id` (BigInt auto-inc)
- **Relações**: `wallet` (BelongsTo Wallet, `wallet_id`), `user` (BelongsTo User, `user_id` — denormalizado p/ escopo de query)
- **Enums**: `type → LedgerEntryType`, `direction → LedgerDirection`
- **Casts**: `type → LedgerEntryType`, `direction → LedgerDirection`, `amount_cents → integer`, `balance_after_cents → integer`, `reference_id → integer`, `seq → integer`, `user_id → integer`
- **Fillable**: nenhum relevante — criação centralizada via `LedgerService::record`
- **Campos**:

| Campo | Tipo | Descrição |
|---|---|---|
| `wallet_id` | bigInt FK | carteira dona da linha |
| `user_id` | bigInt FK | denormalizado do dono da wallet (escopo de query) |
| `type` | string | `LedgerEntryType` (ver Enums) |
| `direction` | string | `LedgerDirection` (Credit / Debit) |
| `amount_cents` | bigInt | sempre positivo |
| `balance_after_cents` | bigInt | saldo após o movimento |
| `reference_type` | string(32) nullable | `deposit` \| `order` \| `pix_charge` \| `boost` |
| `reference_id` | bigInt nullable | PK do objeto referenciado |
| `seq` | uBigInt | sequência por carteira (começa em 1) |
| `prev_hash` | char(64) nullable | hash da entrada anterior (null na 1ª linha) |
| `hash` | char(64) | HMAC-SHA256 desta linha (código de confiabilidade) |

- **Índices**: `unique(wallet_id, seq)`, `unique(hash)`, `index(reference_type, reference_id)`

### PixCharge (carga de saldo via PushinPay)

- **Tabela**: `pix_charges`
- **PK**: `id` (BigInt auto-inc)
- **Relações**: `user` (BelongsTo = quem está carregando)
- **Enums**: `status → PixChargeStatus`
- **Casts**: `status → PixChargeStatus`, `amount_cents → integer`, `paid_at`/`expires_at → datetime`
- **Fillable**: `user_id`, `pushinpay_id`, `amount_cents`, `status`, `qr_code`, `qr_code_base64`, `end_to_end_id`, `paid_at`, `expires_at`
- `pushinpay_id` **unique** (idempotência; normalizado lowercase — a PushinPay devolve UPPERCASE na consulta). `qr_code` = copia-e-cola; `qr_code_base64` = `data:image/png;base64,...`. `expires_at` = `now()+30min`. Crédito na carteira só em `confirmPaid` (uma vez). Índices: `(user_id, status)`, `status`.

---

**Formato a seguir ao registrar novos models:**

```
### NomeDoModel

- Tabela: `nome_da_tabela`
- PK: tipo + estratégia (BigInt auto-inc | ULID | UUID)
- Relações: listar hasMany/belongsTo/belongsToMany com model destino
- Enums: listar enum PHP + coluna + valores
- Casts: listar cast → tipo
- Fillable / Hidden: listar campos
```

## Tabelas de infraestrutura (sem model de domínio)

| Tabela | Origem |
|---|---|
| `users` | Laravel scaffold |
| `password_reset_tokens` | Laravel scaffold |
| `sessions` | Laravel scaffold (session driver DB) |
| `cache` / `cache_locks` | Laravel cache driver DB |
| `jobs` / `job_batches` / `failed_jobs` | Laravel queue driver DB |
| `personal_access_tokens` | Laravel Sanctum |
| `pulse_entries` / `pulse_values` / `pulse_aggregates` | Laravel Pulse |

## Enums

Todos string-backed em `app/Enums/`. Coluna no SQL é `string` (nunca `ENUM`), model declara cast.

| Enum | Coluna / Model | Valores |
|---|---|---|
| `ListingType` | `listings.type` | `item`, `gold` |
| `ListingStatus` | `listings.status` | `active`, `sold`, `cancelled` |
| `OrderStatus` | `orders.status` | `awaiting_confirmation`, `completed`, `cancelled` |
| `BoostTier` | `boosts.tier` | `basic` (R$5), `intermediate` (R$15), `advanced` (R$25) — c/ métodos `priceCents()`, `weight()`, `floatsInGrid()` |
| `BoostStatus` | `boosts.status` | `pending_payment`, `active`, `expired`, `cancelled` |
| `BoostPaymentMethod` | `boosts.payment_method` | `wallet`, `pix` |
| `PixChargeStatus` | `pix_charges.status` | `created`, `paid`, `expired`, `canceled` |
| `LedgerEntryType` | `ledger_entries.type` | `EscrowDebit`, `EscrowReleaseCredit`, `DepositCredit`, `PixTopupCredit`, `BoostDebit` |
| `LedgerDirection` | `ledger_entries.direction` | `Credit`, `Debit` |

## Convenções de PK (direção para o domínio)

- **ULID**: entidades expostas em URL ou com escopo colaborativo (ex.: futuros resources de tenant)
- **BigInt auto-increment**: tabelas de alto volume (ex.: logs, eventos, pivots)
- Decidir por entidade ao criar — não há padrão global obrigatório ainda

## Regras de consulta

- Sempre Eloquent como padrão; query builder cru apenas quando Eloquent não atende
- Evitar N+1 com `with()` / `withCount()` desde o primeiro model com relações
- Coleções grandes precisam de paginação ou `limit`

## Onde confirmar detalhe exato de coluna

- Models: `app/Models/`
- Migrations: `database/migrations/`
- Enums (quando existirem): `app/Enums/`
