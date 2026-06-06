---
type: divida-tecnica
updated: 2026-06-05
---

# Dívida Técnica

Itens **explicitamente adiados** com contexto de por que e quando retomar.
Ao retomar um item, mover para a seção `Resolvido` com a data.

> Atualizar esta nota ao final de cada sprint ou auditoria. Começar em DT-01.

---

## Pendente

| ID | Item | Por que adiado | Quando retomar |
|---|---|---|---|
| DT-01 | Marketplace sem cancelamento/reembolso de pedido — fundos podem ficar presos no escrow | MVP focou no caminho feliz da dupla confirmação | Antes de qualquer uso real; ver [[2026-06-05 marketplace-escrow-mvp]] |
| DT-02 | Taxa (5%) é retida mas não creditada a uma conta de plataforma | Sem entidade de plataforma no MVP | Ao introduzir contabilidade/relatório de receita |
| DT-03 | Sem disputas nem auto-liberação por timeout | Fora do escopo do MVP | Ao endurecer o fluxo de escrow |
| DT-04 | Fetch do frontend é client-side (`useAsyncData server:false`) — sem SSR dos dados | Evitar atrito SSR/hostname Docker em dev | Quando o SSR dos dados virar requisito (SEO/perf) |
| DT-05 | Token Bearer no cookie `rmt_token` **não** é `httpOnly` (lido por JS p/ montar o header `Authorization`) → roubável via XSS | httpOnly quebraria a leitura client-side do token nesta arquitetura Bearer | Migrar p/ Sanctum SPA cookie de sessão httpOnly (single-origin via Caddy já está pronto) ou token só em memória (`useState`) + refresh |
| DT-06 | Jobs agendados (`ReconcilePendingPixChargesJob`, `ExpireBoostsJob`) não rodam — falta um runner de scheduler (`schedule:work`/cron); só o `horizon` (worker) está no compose | MVP: o `PixChargeController::show` re-consulta on-demand e cobre o fluxo local | Antes de prod: adicionar processo `php artisan schedule:work` (container ou supervisord no horizon) |
| DT-07 | Foto/avatar com cache 300s e URL sem versão (`/api/.../photo`) → trocar a imagem reflete em outros lugares em até 5min | Versionar a URL (`?v=hash(path)`) quebraria asserts de teste; cache curto resolve no MVP | Versionar a URL pela random-name do arquivo quando virar problema |
| DT-08 | XP/perk sem anti-fraude de wash-trading: duas contas comprando/vendendo entre si farmam XP e desconto de taxa | Comprar do próprio anúncio já é bloqueado; conluio entre contas distintas não | Limitar XP por contraparte/tempo, ou exigir reputação verificada antes do perk de taxa |

## Resolvido

| Item | Resolvido em | Como |
|---|---|---|
