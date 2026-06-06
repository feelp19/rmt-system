---
date: 2026-06-06
type: adr
status: accepted
area: arquitetura
tags: [ledger, seguranca, hash, hmac, auditoria]
---

# ADR: Ledger de confiabilidade com HMAC-SHA256 encadeado por carteira

## Contexto

Todo movimento de dinheiro no sistema (depósito, escrow, liberação de escrow, carga via PIX,
boost) precisava de uma prova auditável de que executou, é legítimo e não foi adulterado.
A `wallets.balance_cents` é um inteiro mutável — qualquer acesso direto ao banco pode
alterar o saldo sem deixar rastro. Era preciso decidir como garantir rastreabilidade e
detectar adulteração (alteração, deleção ou reordenação de linhas) sem introduzir um
gargalo global num sistema Octane/worker altamente concorrente.

## Decisão

- **Append-only `ledger_entries`**: cada movimento de carteira registra uma entrada imutável
  com `wallet_id`, `user_id`, `type` (`LedgerEntryType` enum PHP), `direction`
  (`LedgerDirection` enum PHP), `amount_cents`, `balance_after_cents`,
  `reference_type`/`reference_id`, `seq` (sequencial por carteira), `prev_hash` e `hash`.
- **HMAC-SHA256** sobre JSON canônico de campos fixos (ordem determinística, sem `created_at`)
  com chave `config('ledger.hmac_key')` (`LEDGER_HMAC_KEY` no `.env`). O `hash` resultante
  é o "código de confiabilidade" exposto ao usuário e verificável a qualquer momento.
- **Cadeia por carteira** (não global): o `prev_hash` de cada entrada aponta para o `hash`
  da entrada anterior da mesma carteira (`seq - 1`). A cabeça da cadeia (`ledger_head_hash`
  + `ledger_seq`) fica ancorada na tabela `wallets`, atualizada sob o mesmo lock que o
  movimento adquire — sem lock global adicional.
- **Âncora na `wallets`**: `ledger_head_hash` e `ledger_seq` não estão no `$fillable` do
  model (`guarded`) para preservar integridade; detectam truncamento da última linha (a
  cabeça é saída de HMAC — não forjável sem o segredo).
- **`LedgerService::record`** é chamado dentro da transação do caller, após o
  `increment`/`decrement`, com a wallet já travada via `lockForUpdate` — garante
  idempotência (não duplica em retry da transação) e ordem correta de escrita.
- **Fail-closed**: se `LEDGER_HMAC_KEY` estiver ausente, `LedgerService` lança exceção e
  impede o movimento de completar — preferível a registrar entradas sem assinatura.
- **`created_at` fora do HMAC**: o campo é preenchido automaticamente pelo DB e pode variar
  em nano-segundos entre ambientes; incluí-lo tornaria a re-verificação impossível sem
  capturar o valor exato no momento do registro.
- **Taxa não vira linha de ledger**: a plataforma não tem conta de carteira; a taxa é
  derivável de `orders.fee_cents` (consistente com DT-02 do escrow MVP).

## Consequências

**Positivas:**
- Cada transação carrega um "código de confiabilidade" verificável pelo usuário e pela
  plataforma via API (`GET /api/ledger/{hash}/verify`) e CLI (`ledger:verify`).
- Adulteração de qualquer linha quebra o HMAC; deleção ou reordenação quebra a cadeia de
  `prev_hash`; truncamento da última linha é detectado pela âncora na `wallets`.
- Cadeia por carteira evita serialização global: cada wallet tem seu próprio lock,
  aproveitando o `lockForUpdate` já existente nos serviços — sem overhead adicional
  sob Octane.
- Enums PHP (`LedgerEntryType`, `LedgerDirection`) seguem a regra 7 — sem ENUM no SQL.

**Negativas / trade-offs:**
- Rotação da `LEDGER_HMAC_KEY` invalida a verificação de entradas históricas (HMAC
  usa a chave ativa). Mitigação futura: campo `key_id` na entrada para multi-key.
- A cadeia é por-carteira — não há ordenação global de eventos cross-wallet por hash.
  Para auditoria cross-wallet usa-se `created_at` + `id`, não a cadeia.
- `WalletService::deposit` precisou ser refatorado para abrir `DB::beginTransaction`
  explícito + `lockForUpdate` (antes era `increment` direto), alinhando com o padrão
  dos demais serviços.
- Chave ausente causa fail-closed: em ambientes de teste, o `phpunit.xml` deve declarar
  `LEDGER_HMAC_KEY` (valor de teste) para os testes passarem.

## Alternativas descartadas

| Alternativa | Motivo do descarte |
|---|---|
| Cadeia global única (um `prev_hash` para todo o sistema) | Serializa todo movimento num lock global — mata concorrência Octane com múltiplas carteiras ativas |
| Só SHA-256 sem chave secreta | Forjável por quem conhece os campos da entrada; não prova legitimidade, apenas integridade estrutural |
| Só HMAC sem cadeia (`prev_hash`) | Detecta adulteração de linha individual, mas não detecta deleção silenciosa ou reordenação de entradas |
| Âncora Merkle global com checkpoint diário | Over-engineering para o MVP; a âncora simples por carteira já cobre os requisitos de detecção de truncamento |
| `DB::transaction(fn)` para envolver o `record` | Proibido pela regra 8; caller já usa `beginTransaction` explícito |

## Features que aplicam esta decisão

- [[2026-06-06 ledger-confiabilidade-transacoes]]

## Atualizações no vault

- [x] Adicionei link no cluster "Decisões Arquiteturais" do `MOC.md`
- [ ] Se padrão recorrente: criei nota em `.vault/Conceitos/` apontando pra cá
- [x] Atualizei skill correspondente em `.claude/skills/` se a decisão muda regra ativa

## Links relacionados

- [[ADR — escrow dupla-confirmacao e carteira simulada]]
- [[2026-06-06 ledger-confiabilidade-transacoes]]
- [[Divida-Tecnica]]
