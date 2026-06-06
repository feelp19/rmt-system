# Design: Ledger de confiabilidade — hash de rastreabilidade por transação

- **Data:** 2026-06-06
- **Status:** aprovado (brainstorming)
- **Área:** marketplace / pagamentos / segurança
- **Autor:** rmt-dev

## Objetivo

Cada transação financeira do marketplace deve carregar um **hash de confiabilidade** que
prove que ela foi executada com sucesso e é legítima, garantindo **rastreabilidade**
(audit trail à prova de adulteração). O hash é um **HMAC-SHA256** encadeado em um
**ledger append-only por carteira**.

## Decisões fechadas (brainstorming)

1. **Abrangência:** ledger append-only — toda movimentação de saldo vira uma linha imutável.
2. **Força do hash:** HMAC-SHA256 (segredo do servidor) **+ cadeia** (cada linha referencia o
   hash da anterior). Infalsificável (não dá pra forjar sem o segredo) e tamper-evident
   (detecta alteração/deleção/reordenação).
3. **Superfície de verificação:** comando de auditoria interno **+** código no recibo (UI)
   **+** endpoint de verificação.
4. **Granularidade da cadeia:** **cadeia por carteira** — cada wallet tem sua própria cadeia;
   `prev_hash` = última linha daquela wallet. Encaixa no lock por-wallet existente, sem
   gargalo global (rejeitada a cadeia global única por serializar todo movimento sob Octane).

## Modelo de dados

### Tabela `ledger_entries` (append-only — nunca UPDATE/DELETE)

| coluna | tipo | nota |
|---|---|---|
| `id` | bigint PK | |
| `wallet_id` | FK `wallets` | conta cujo saldo mexeu; a cadeia é por aqui |
| `user_id` | FK `users` | dono da carteira (denormalizado p/ query/escopo) |
| `type` | string(32) | enum `App\Enums\LedgerEntryType` |
| `direction` | string(6) | enum `App\Enums\LedgerDirection`: `credit`/`debit` |
| `amount_cents` | bigInteger | sempre positivo; o sinal vem do `direction` |
| `balance_after_cents` | bigInteger | snapshot do saldo da carteira após o movimento |
| `reference_type` | string(32) nullable | `order` / `pix_charge` / `boost` / `deposit` |
| `reference_id` | bigInteger nullable | id da linha de origem |
| `seq` | unsignedBigInteger | sequência por carteira (1,2,3…); detecta gap |
| `prev_hash` | char(64) nullable | hash da linha anterior **desta** carteira (null = genesis) |
| `hash` | char(64) | HMAC-SHA256 hex do payload canônico — o "código de confiabilidade" |
| `created_at` / `updated_at` | timestamps | metadados (não entram no HMAC) |

**Índices:** `wallet_id`; unique `(wallet_id, seq)`; `(reference_type, reference_id)`;
unique `hash` (lookup por código no endpoint de verificação).

### Âncora anti-truncamento — colunas novas em `wallets`

- `ledger_head_hash` char(64) nullable — hash da última linha da cadeia da carteira.
- `ledger_seq` unsignedBigInteger default 0 — seq da última linha.

**Por quê:** apagar a **última** linha da cadeia não deixa gap em `seq` e passaria
despercebido só com `prev_hash`. A head guarda o hash/seq esperado; como `ledger_head_hash`
é saída de HMAC, um atacante não consegue forjar uma head válida para uma cadeia truncada
sem o segredo. A verificação compara a cabeça calculada com a head persistida.

## Enums (regra 7 — PHP backed, nunca `ENUM` no SQL)

```php
// app/Enums/LedgerEntryType.php
enum LedgerEntryType: string {
    case EscrowDebit = 'escrow_debit';
    case EscrowReleaseCredit = 'escrow_release_credit';
    case DepositCredit = 'deposit_credit';
    case PixTopupCredit = 'pix_topup_credit';
    case BoostDebit = 'boost_debit';
}

// app/Enums/LedgerDirection.php
enum LedgerDirection: string {
    case Credit = 'credit';
    case Debit = 'debit';
}
```

Migration usa `string`; model declara `$casts`; qualquer FormRequest valida via `Rule::enum()`.

## LedgerService (coração) — stateless, Octane-safe

`app/Services/LedgerService.php`. Sem estado mutável em propriedade; lê
`config('ledger.hmac_key')` a cada chamada (regra 13).

### Assinatura

```php
public function record(
    Wallet $lockedWallet,        // PRÉ-CONDIÇÃO: já travada (lockForUpdate) pelo caller
    LedgerEntryType $type,
    LedgerDirection $direction,
    int $amountCents,            // sempre positivo
    int $balanceAfterCents,      // saldo após o movimento já aplicado
    ?string $referenceType = null,
    ?int $referenceId = null,
): LedgerEntry
```

### Lógica (chamada DENTRO da transação aberta do caller, com a wallet travada)

1. `$prev = $lockedWallet->ledger_head_hash;` `$seq = $lockedWallet->ledger_seq + 1;`
2. Monta o **payload canônico** (ordem fixa, só campos determinísticos — `created_at`
   fica de fora porque é DB-auto e não é reproduzível na re-verificação):

   ```php
   $canonical = json_encode([
       'wallet_id' => $lockedWallet->id,
       'seq' => $seq,
       'type' => $type->value,
       'direction' => $direction->value,
       'amount_cents' => $amountCents,
       'balance_after_cents' => $balanceAfterCents,
       'reference_type' => $referenceType,
       'reference_id' => $referenceId,
       'prev_hash' => $prev,
   ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
   ```

3. `$hash = hash_hmac('sha256', $canonical, $this->key());`
4. Cria `LedgerEntry` com todos os campos (incl. `prev_hash`, `hash`, `seq`).
5. Atualiza `$lockedWallet->ledger_head_hash = $hash; $lockedWallet->ledger_seq = $seq;`
   e salva (mesmo lock / mesma transação).
6. Retorna a entry.

### Gestão da chave

- `config/ledger.php` → `'hmac_key' => env('LEDGER_HMAC_KEY')`.
- **Fail-closed:** `key()` lança `RuntimeException` se a chave estiver vazia — nunca grava
  ledger sem HMAC válido.
- `.env.example` recebe `LEDGER_HMAC_KEY=` (gerar valor aleatório forte, ex. `base64:`+32 bytes).
- **Limitação documentada:** rotação da chave invalida a verificação das linhas antigas
  (versionamento por key-id é trabalho futuro — YAGNI no MVP).

## Pontos de gancho (append dentro das transações existentes)

`LedgerService` é injetado em cada serviço. O append acontece **após** o `increment`/`decrement`
(o model em memória já reflete o saldo pós-movimento) e **dentro** do mesmo bloco que só roda
uma vez, com a wallet já travada:

| Serviço/método | Tipo | Direção | Reference | Observação |
|---|---|---|---|---|
| `OrderService::purchase` | `EscrowDebit` | debit | `order` | wallet do comprador já travada; append após criar a Order (p/ ter `order->id`) |
| `OrderService::release` | `EscrowReleaseCredit` | credit | `order` | wallet do vendedor já travada; roda 1× sob guard de status |
| `WalletService::deposit` | `DepositCredit` | credit | `deposit` | **passa a usar** `DB::beginTransaction` + `lockForUpdate` (hoje é só `increment` lock-free) |
| `PixChargeService::confirmPaid` | `PixTopupCredit` | credit | `pix_charge` | wallet já travada; idempotente via guard de status |
| `BoostService::purchaseWithWallet` | `BoostDebit` | debit | `boost` | wallet já travada |

**Taxa da plataforma** não vira linha de ledger (o MVP não tem conta de plataforma; o valor
é derivável de `orders.fee_cents`).

### Garantia de idempotência

`release` e `confirmPaid` executam o crédito exatamente uma vez (guard de status sob lock).
O append fica dentro desse mesmo caminho de uma-vez-só → nunca duplica linha. Confirmar
entrega/recebimento duas vezes continua no-op.

## Superfície de API (Resources — regra 6)

### `LedgerEntryResource` (`app/Http/Resources/Marketplace/`)

Expõe: `id, type, direction, amount_cents, balance_after_cents, reference_type,
reference_id, code (= hash), seq, created_at`.
**Nunca** expõe `prev_hash` nem o segredo. `hash` é HMAC → seguro de exibir (não reversível,
não forjável sem a chave).

### Rotas

- `GET /api/wallet/ledger` (`auth:sanctum`) — extrato paginado escopado em `wallet_id` do dono
  (envelope paginado Laravel, padrão das listagens). Controller thin `WalletLedgerController`.
- `GET /api/ledger/{hash}/verify` (`auth:sanctum`, `throttle`) — recomputa o HMAC da linha pelo
  `hash` informado e confere o elo `prev_hash` com a linha anterior.
  - **Escopo (anti-IDOR):** a entry é acessível se o dono da carteira é `auth()->id()` **ou**
    (quando `reference_type=order`) o usuário é comprador/vendedor da Order referenciada.
    Mismatch → **404** (anti-enumeração, regra 4), nunca 403.
  - Resposta: `{ valid: bool, type, amount_cents, direction, created_at, reference_type,
    reference_id }`. Controller thin `LedgerVerificationController`.

## Comando de auditoria

`php artisan ledger:verify {--wallet=}` — `app/Console/Commands/VerifyLedgerCommand.php`.
Para cada carteira (ou só a `--wallet`), percorre a cadeia em ordem de `seq` e checa:

1. HMAC recomputado de cada linha == `hash` armazenado (`hash_equals`).
2. `prev_hash` == `hash` da linha anterior (genesis: `prev_hash` null).
3. `seq` contíguo (1..N, sem gap).
4. Cabeça final calculada (hash + seq) == `wallet.ledger_head_hash` + `ledger_seq`
   (detecta truncamento da última linha).

Reporta a **primeira** quebra por carteira (`wallet_id`, `seq`, motivo) e sai com código ≠ 0
se houver qualquer quebra; cadeia limpa → exit 0. Roda em CI, cron ou manual.

## Frontend (regras 11 e 12 — componente-first, PrimeVue)

- Componente novo `LedgerReceipt.vue` em `frontend/app/components/`: mostra o código de
  confiabilidade (`Tag`/botão copiável) + ação "Verificar" que chama o endpoint e renderiza o
  resultado (`Message`/`Tag`).
- Extrato da carteira via `DataTable` (lista de `ledger_entries`), com o código por linha.
- Detalhe da Order mostra o(s) código(s) das linhas da transação.
- As páginas (`frontend/app/pages/`) apenas montam os componentes.

## Migrations (regra 9 — só forward, `down()` reversível)

1. `create_ledger_entries_table` — cria a tabela + índices; `down()` faz `dropIfExists`.
2. `add_ledger_chain_head_to_wallets_table` — adiciona `ledger_head_hash` + `ledger_seq`;
   `down()` dropa as colunas.

Nada de `migrate:fresh`/`refresh`/`wipe` (regra 9).

## Testes (rmt-tests — PHPUnit class-style, `RefreshDatabase`)

- **LedgerService:** `record` gera HMAC determinístico; `prev_hash`/`seq` encadeiam; head da
  wallet atualiza.
- **Cada fluxo de dinheiro** gera a linha esperada (purchase, release, deposit, pix confirm, boost):
  tipo, direção, `amount_cents`, `balance_after_cents`, reference corretos.
- **Idempotência:** double `confirm-receipt`/`confirm-delivery` → exatamente 1 linha de crédito.
- **Endpoint verify:** dono → `valid=true`; após adulterar `amount_cents` direto no banco →
  `valid=false` e o comando acusa; estranho → **404** (regressão IDOR).
- **Comando `ledger:verify`:** cadeia limpa passa (exit 0); linha corrompida → exit ≠ 0 +
  reporta a quebra; deletar a última linha sem ajustar a head → detecta truncamento.
- **Contagem de queries / locks:** segue o padrão de lock existente (a serialização por-wallet
  já é coberta pelos testes de escrow).

## Vault + skills (encerramento — regras 14, manutenção de skills, vault)

- **ADR** `.vault/Decisoes/ADR — ledger de confiabilidade HMAC encadeado.md`.
- **Feature** `.vault/Features/2026-06-06 ledger-confiabilidade-transacoes.md`.
- Atualizar skills: `rmt-schema` (tabela/enums/colunas), `rmt-architecture` (LedgerService +
  ganchos + comando), `rmt-security` (modelo de confiança do HMAC, escopo do verify),
  `rmt-tests` (padrões novos), `rmt-context` (rotas novas).
- Rodar `Agent(subagent_type="rmt-feature-finisher")` antes do commit; adicionar rotas privadas
  novas ao `robots.txt`/`X-Robots-Tag`.

## Limitações conhecidas / fora de escopo

- Chave HMAC única e ativa; rotação invalida verificação do passado (key-id = futuro).
- Cadeia por carteira → sem garantia de ordenação global; correlação entre contas via
  `reference_id` (aceito na decisão 4).
- Taxa da plataforma não é linha de ledger (sem conta de plataforma — DT existente).
- Âncora global (Merkle root diário das cabeças) descartada como over-engineering no MVP.
