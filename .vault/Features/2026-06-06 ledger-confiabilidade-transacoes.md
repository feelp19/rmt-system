---
date: 2026-06-06
type: feature
status: implemented
area: arquitetura
tags: [ledger, seguranca, hash, hmac, auditoria, wallet, rastreabilidade]
---

# Feature: Ledger de confiabilidade — hash de rastreabilidade por transação

## O que foi implementado

Todo movimento de carteira agora gera uma entrada imutável na tabela `ledger_entries`,
assinada com **HMAC-SHA256 encadeado** (cadeia por carteira). O resultado é um
**"código de confiabilidade"** por transação: o usuário pode verificar que o movimento
executou, é legítimo (não forjado) e está íntegro (não foi alterado, deletado nem
reordenado). A verificação é exposta via API REST e comando artisan.

Cobertos: depósito, escrow (compra), liberação de escrow, carga PIX e boost — todos os
caminhos que movem `balance_cents`.

## Decisões técnicas

- **Cadeia por carteira, não global** — cada wallet tem seu próprio `seq` + `prev_hash`.
  O lock já adquirido pelos serviços (`lockForUpdate`) é suficiente para garantir
  sequencialidade dentro da cadeia, sem introduzir lock global. Ver
  [[ADR — ledger de confiabilidade HMAC encadeado]].
- **HMAC + cadeia combinados** — HMAC (segredo) prova legitimidade (infalsificável sem a
  chave); `prev_hash` (cadeia) detecta alteração, deleção ou reordenação de linhas.
  Apenas um dos dois não cobre o conjunto completo de ameaças.
- **Âncora `ledger_head_hash` + `ledger_seq` na `wallets`** — detecta truncamento da
  última linha. Não estão no `$fillable` do model (`guarded`) para evitar sobrescrita
  acidental via `fill()`/`update()` massivo.
- **`created_at` fora do payload do HMAC** — o DB preenche o campo automaticamente; seu
  valor não é reproduzível com precisão de nanosegundos em ambientes distintos, o que
  tornaria a re-verificação frágil. Campos do payload são de ordem fixa e determinística.
- **Fail-closed na chave** — `LedgerService` lança exceção se `LEDGER_HMAC_KEY` estiver
  ausente ou vazia. O movimento inteiro é abortado (rollback). Preferível a registrar
  entradas sem assinatura e descobrir depois que o ledger não é verificável.
- **Taxa da plataforma não vira linha de ledger** — a plataforma não tem `Wallet`; a taxa
  é derivável de `orders.fee_cents`. Consistente com a decisão do escrow MVP (DT-02).
- **`LedgerService::record` chamado pelo caller, não disparado por Observer** — Observer
  seria assíncrono ou fora da transação; `record` precisa executar dentro do bloco
  `beginTransaction` + `lockForUpdate` do caller para garantir atomicidade e evitar
  duplicação em retry.
- **Enums PHP, nunca ENUM SQL** (regra 7) — `LedgerEntryType` e `LedgerDirection` em
  `app/Enums/`; migration usa `string`; model declara `$casts`.

## Arquivos criados / modificados

| Arquivo | O que mudou |
|---|---|
| `database/migrations/*_create_ledger_entries_table.php` | tabela append-only: wallet_id, user_id, type, direction, amount_cents, balance_after_cents, reference_type, reference_id, seq, prev_hash, hash; unique(wallet_id,seq), unique(hash) |
| `database/migrations/*_add_ledger_head_to_wallets.php` | colunas `ledger_head_hash` (char 64, nullable) e `ledger_seq` (bigint, default 0) na `wallets` |
| `app/Enums/LedgerEntryType.php` | enum: EscrowDebit, EscrowReleaseCredit, DepositCredit, PixTopupCredit, BoostDebit |
| `app/Enums/LedgerDirection.php` | enum: Credit, Debit |
| `app/Models/LedgerEntry.php` | model Eloquent, `$guarded = ['id']`, casts para os enums |
| `app/Models/Wallet.php` | relação `ledgerEntries()`, `ledger_head_hash`/`ledger_seq` em `$guarded` |
| `app/Services/LedgerService.php` | `record(wallet, type, direction, amount, balanceAfter, refType, refId)`, `signatureValid(entry)`, `verifyEntry(entry)` |
| `app/Services/WalletService.php` | `deposit` refatorado: `beginTransaction` + `lockForUpdate` + `LedgerService::record` |
| `app/Services/OrderService.php` | `purchase`: append EscrowDebit; `release`: append EscrowReleaseCredit |
| `app/Services/PixChargeService.php` | `confirmPaid`: append PixTopupCredit |
| `app/Services/BoostService.php` | `purchaseWithWallet`: append BoostDebit |
| `app/Http/Controllers/Wallet/WalletLedgerController.php` | `GET /api/wallet/ledger` (extrato paginado) |
| `app/Http/Controllers/Marketplace/LedgerVerificationController.php` | `GET /api/ledger/{hash}/verify` (verificação pública/scopada) |
| `app/Http/Resources/Marketplace/LedgerEntryResource.php` | contrato JSON do extrato |
| `app/Http/Resources/Marketplace/LedgerVerificationResource.php` | contrato JSON da verificação (hash, valid, chain_ok, entry) |
| `app/Console/Commands/LedgerVerifyCommand.php` | `php artisan ledger:verify {--wallet=}` — exit ≠ 0 em quebra |
| `app/Policies/LedgerPolicy.php` | `verify`: dono da wallet OU contraparte da order referenciada → 404 anti-IDOR |
| `config/ledger.php` | `hmac_key => env('LEDGER_HMAC_KEY')` |
| `.env.example` | `LEDGER_HMAC_KEY=` |
| `phpunit.xml` | `LEDGER_HMAC_KEY` com valor de teste |
| `routes/api.php` | rotas `wallet/ledger` e `ledger/{hash}/verify` |
| `frontend/app/components/LedgerReceipt.vue` | exibe código de confiabilidade + botão Verificar |
| `frontend/app/components/WalletLedgerTable.vue` | extrato paginado em DataTable PrimeVue |
| `frontend/app/pages/wallet.vue` | monta `WalletLedgerTable` + `LedgerReceipt` |

## Pitfalls / o que me surpreendeu

- **`WalletService::deposit` não tinha lock** — antes era um `increment` direto sem
  transação explícita. Para apendar o ledger dentro da mesma transação e sob lock
  (necessário para ler a cabeça da cadeia de forma segura), foi preciso refatorar
  `deposit` para o padrão `beginTransaction` + `lockForUpdate` + `commit`/`rollback`.
  Se você adicionar outro ponto de entrada de dinheiro, ele precisa do mesmo padrão.
- **Nunca apendar fora do lock da wallet** — `LedgerService::record` lê `ledger_seq` e
  `ledger_head_hash` da wallet para construir a entrada seguinte. Se chamado sem
  `lockForUpdate` ativo, duas escritas concorrentes podem gerar o mesmo `seq` (viola
  unique) ou cadeia divergente.
- **`created_at` fora do HMAC é não-óbvio** — a tentação é incluir o timestamp para
  "provar quando aconteceu". O problema: o DB gera o valor após o INSERT e não é
  reproduzível na re-verificação sem armazená-lo separadamente. A rastreabilidade de
  *quando* fica em `created_at` (campo auditável, mas fora do HMAC).
- **Chave fail-closed quebra testes sem configuração** — `phpunit.xml` precisa ter
  `LEDGER_HMAC_KEY` declarado (qualquer string não-vazia). Esquecer faz todos os testes
  que exercitam movimento de carteira falharem com exceção antes de chegar na asserção.
- **A cadeia por-wallet não dá ordenação global** — para cruzar eventos de carteiras
  distintas (ex.: comprador vs. vendedor numa mesma Order) a coluna `created_at` + `id`
  é a referência correta; a cadeia só ordena dentro de uma carteira.
- **Scope de autorização no `verify`** — o endpoint aceita qualquer `hash`; a Policy
  verifica se o caller é dono da wallet da entrada OU contraparte da order referenciada.
  Não encontrar = 404 (anti-IDOR), não 403.

## Dívida técnica gerada

- DT-05: rotação de `LEDGER_HMAC_KEY` invalida verificação histórica. Mitigação futura:
  campo `key_id` na `ledger_entries` + suporte a múltiplas chaves ativas no
  `LedgerService`.
- DT-06: cadeia por-carteira não oferece ordenação global cross-wallet auditável por
  hash — timestamp é suficiente para o MVP, mas uma âncora global periódica (ex.:
  checkpoint diário em tabela separada) seria mais robusto para auditoria externa.

Ver [[Divida-Tecnica]].

## Skills atualizadas

- [x] `rmt-context`
- [x] `rmt-schema`
- [x] `rmt-architecture`
- [x] `rmt-frontend`
- [x] `rmt-security`
- [x] `rmt-tests`

## Atualizações no vault

- [x] Link no `MOC.md`
- [x] ADR criado: [[ADR — ledger de confiabilidade HMAC encadeado]]

## Links relacionados

- [[ADR — ledger de confiabilidade HMAC encadeado]]
- [[ADR — escrow dupla-confirmacao e carteira simulada]]
- [[2026-06-05 marketplace-escrow-mvp]]
- [[Divida-Tecnica]]
