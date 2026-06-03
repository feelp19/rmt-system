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

**Sem hierarquia de domínio ainda.** Só existe `User` como model de negócio. Todas as outras tabelas são de infraestrutura (framework, auth, filas, observabilidade).

Atualizar este skill ao adicionar models/migrations: registrar PK, relações, enums e casts de cada novo model aqui.

## Hierarquia

Nenhuma hierarquia de domínio definida. Estrutura atual:

```
User  (único model de domínio)
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

Nenhum enum de domínio definido ainda. Ao criar o primeiro enum, adicionar em `app/Enums/` e registrar aqui com: nome da classe, string-backed ou int-backed, valores possíveis, e qual coluna/model usa.

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
