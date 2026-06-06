---
type: moc
updated: 2026-06-06
---

# MOC — Map of Content

Ponto de entrada do vault. Use isto antes de mergulhar em pastas. Cada cluster lista as notas mais importantes da área com one-liner. Para detalhes, abrir a nota.

> **Antes de começar uma feature/bug**: localizar a área aqui, ler ADRs e bugs históricos do cluster, então prosseguir.

---

## Rota rápida por tipo de tarefa

| Tarefa | Começar por |
|---|---|
| Feature nova | `Conceitos/` (padrões) + `Features/` (histórico similar) + skill `rmt-context` |
| Bug ou incidente | `Bugs/` (grep por sintoma) + skill `rmt-security` |
| Refactor ou decisão técnica | `Decisoes/` (ADRs) + `Divida-Tecnica.md` |
| Frontend | skill `rmt-frontend` + `Conceitos/` relacionados |
| Schema/migration | skill `rmt-schema` + `Glossario.md` |
| Segurança (revisão) | skill `rmt-security` + `Conceitos/` relacionados |

## Conceitos

## Features

- [[2026-06-06 homepage-redesign-loot]] — redesign loot/RPG da home: tema ouro global, dark forçado, tokens de raridade, feed ao vivo HomeActivity, efeitos máximo (spotlight/aurora/sheen), backend GET /api/activity
- [[2026-06-06 ledger-confiabilidade-transacoes]] — ledger append-only com HMAC-SHA256 encadeado por carteira: "código de confiabilidade" por transação, verificável via API e CLI
- [[2026-06-05 marketplace-escrow-mvp]] — marketplace de itens/gold com escrow de dupla confirmação + taxa 5% (MVP)
- [[2026-06-05 homepage]] — homepage combinada (hero + como funciona + stats + vitrine), gaming-bold
- [[2026-06-05 boost-destaque-inc1]] — boost pago (3 pacotes) + área "Em destaque", pago por carteira (Inc 1; PIX no Inc 2)
- [[2026-06-05 pix-topup-pushinpay-inc2]] — carga de saldo via PIX (PushinPay): cobrança + webhook seguro + jobs (Boost Inc 2)
- [[2026-06-05 perfil-xp-imagens]] — foto obrigatória + editar/excluir anúncio, perfil+avatar, XP/níveis/perks/ranking

## Frontend

## Segurança

## Decisões Arquiteturais Fundacionais

- [[ADR — rebrand ouro e dark global]] — primary verde→ouro + dark mode forçado em todo o app: identidade gaming coesa, contrastColor explícito, tokens de raridade
- [[ADR — ledger de confiabilidade HMAC encadeado]] — HMAC-SHA256 + cadeia prev_hash por carteira: prova legitimidade, detecta adulteração/deleção, sem lock global
- [[ADR — escrow dupla-confirmacao e carteira simulada]] — carteira interna em centavos + escrow retido na Order, liberado só com dupla confirmação
- [[ADR — webhook PushinPay sem assinatura]] — confiar no PIX sem HMAC: secret na URL + re-verificação por id + idempotência

## Documentos de referência

- [[README]] — Como abrir o vault, convenções de nome, taxonomia de tags
- [[Glossario]] — Vocabulário do domínio
- [[Divida-Tecnica]] — Itens conscientemente adiados, com motivo e quando retomar
- `Skills/` — Symlinks para `.claude/skills/*/SKILL.md` (referência rápida)
