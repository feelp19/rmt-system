# Homepage redesign — "Loot RPG" gaming/gold (dark + ouro + raridade)

- **Data:** 2026-06-06
- **Tipo:** Redesign frontend (global) + endpoint backend novo
- **Status:** Design aprovado → aguardando plano
- **Áreas:** `frontend/` (tema global + home), `app/` (activity feed)

## Problema

A home atual ("gaming-bold" verde + ouro) está, na visão do dono, **simples demais e
pouco chamativa**. Pedido: deixar mais flashy, **mudar as cores** e cravar uma identidade
de **venda de itens/gold de games**.

## Decisões já tomadas (brainstorming)

| Eixo | Escolha |
|---|---|
| Direção de cor | **Loot RPG (raridade)** — obsidiana + ouro + acentos de raridade (épico roxo / lendário laranja) |
| Escopo | **App inteiro dark + ouro** — força dark em todas as páginas + rebrand do `primary` para ouro |
| Intensidade | **Máximo** — fundo animado, spotlight no mouse, sheen, glow/parallax (sob `prefers-reduced-motion`) |
| Conteúdo | **Repaginar + atividade ao vivo** — reestiliza seções atuais + feed "vendido agora / em escrow" |
| Feed ao vivo | **Endpoint real agora** — `GET /api/activity` público, cacheado, anonimizado |

## Objetivos / critérios de sucesso

1. Identidade visual "loot/gaming gold" reconhecível em 1 olhada (ouro central + raridade).
2. App inteiro em dark mode coeso, sem regressão de contraste (WCAG AA em texto/CTA).
3. Home claramente mais "viva" e chamativa que a atual, com motion respeitando `prefers-reduced-motion`.
4. Feed de atividade real, dinâmico e **sem vazar dado de comprador**.
5. Zero violação das regras imutáveis (Service/Resource/Eloquent/Policy/Octane).

---

## 1. Sistema de cor (rebrand global)

`nuxt.config.ts` passa a usar `definePreset(Aura, {...})`:

- **`primary` verde → ouro.** Escala 50–950 derivada de base `#F5C542`.
  - `colorScheme.dark.primary.color` = ouro; `contrastColor` = ink escuro (`#0B0E14`)
    para garantir AA em botão/preço dourado (texto escuro sobre ouro).
- **Tokens de raridade** (CSS vars em `app/assets/css/main.css`):
  - `--rare: #3B82F6` (azul · item comum)
  - `--epic: #A855F7` (roxo)
  - `--legend: #FB923C` (laranja · lendário)
  - `--gold: #F5C542`
- **Superfícies dark:** `--bg: #0B0E14`, `--surf: #141A24`, `--ink: #0A100D`,
  `--ink-soft: #121B16` (mantém), bordas metálicas (`color-mix` com ouro/branco baixo alpha).

**Metáfora loot (mapeamento tipo → cor):**
- anúncio `gold` → realce **ouro**
- anúncio `item` → realce **azul (rare)**
- anúncio **boostado** → glow **épico/lendário** conforme o tier do boost (reaproveita
  `boost.tier_label` já existente; mapear tier → `--epic`/`--legend`).

## 2. Dark mode forçado (app inteiro)

- `theme.options.darkModeSelector: '.app-dark'`.
- Classe `app-dark` fixa no `<html>` via `app.head.htmlAttrs.class` no `nuxt.config.ts`
  (sem toggle, sempre dark).
- **Auditoria de contraste** obrigatória nas páginas e componentes:
  - Páginas: `index`, `login`, `wallet`, `orders`, `perfil`, `ranking`.
  - Componentes com CSS custom: `HomeHowItWorks` (`.num` usa fallback `#fff` →
    trocar por token dark, ex.: `var(--surf)`), `HomeHero`, `HomeStats`,
    `ListingCard`, `AppHeader`, `OrderCard`, `WalletPanel`, `ProfileSummary`,
    `WalletLedgerTable`, `LedgerReceipt`.
  - Componentes que só usam tokens Aura adaptam sozinhos — verificar mesmo assim.

## 3. Home — seções & layout

Ordem nova de `frontend/app/pages/index.vue` (continua fina, só monta):

1. **`HomeHero`** — repaginado: full-bleed dark, type display gigante (Bricolage),
   showcase de cards com cor de raridade, CTAs com sheen.
2. **`HomeActivity`** (novo) — ticker "ao vivo" (ver §5).
3. **`HomeHowItWorks`** — repaginado dark, numerais ouro.
4. **`FeaturedListings` → "Loot em alta"** — boostados com glow de raridade por tier.
5. **`HomeStats`** — banda de confiança + chips de jogos (mantém estrutura, recolore).
6. **`ListingGrid`** — vitrine; cards com borda/glow de raridade por tipo.

**Regra 11/12 mantidas:** página fina; PrimeVue primeiro nos elementos reais
(`Button`, `Card`, `Tag`); CSS custom só onde Aura não cobre (hero/efeitos), com
comentário justificando.

## 4. Efeitos "máximo" (todos sob `@media (prefers-reduced-motion: no-preference)`)

| Efeito | Implementação | Perf |
|---|---|---|
| Spotlight no mouse (hero) | CSS var `--mx/--my` atualizada em `pointermove`, throttle via `requestAnimationFrame`, `import.meta.client` | só `background-position`/radial, sem reflow |
| Fundo aurora / poeira de ouro | pseudo-elements (`::before/::after`) com `@keyframes` em `transform`/`opacity` | GPU, zero JS, sem lib de partícula |
| Sheen dourado | gradiente varrendo CTAs e cards de "loot em alta" via keyframe | composite-only |
| Glow + tilt parallax (hover) | `transform` por raridade no hover do card | composite-only |
| Reveal on scroll | `IntersectionObserver` adiciona classe ao entrar na viewport | barato |

- **Reduced-motion:** tudo estático — mantém só cor/contraste/realce; nenhum keyframe,
  nenhum listener de `pointermove`.
- **Sem libs novas** (sem three.js/particles.js). Pure CSS + JS mínimo.

## 5. Live activity feed

### Backend — `GET /api/activity` (público)

Rota em `routes/api.php` (bloco público, junto de `/listings`):
`Route::get('/activity', [ActivityController::class, 'index'])->middleware('throttle:60,1');`

- **`ActivityController`** (thin wrapper, regra 1) → **`ActivityFeedService`** (lógica, regra 1)
  → **`ActivityItemResource`** + envelope (regra 6).
- **`ActivityFeedService`** (regras imutáveis):
  - Cache Redis ~15s (`Cache::remember('activity.feed', 15, fn () => ...)`), pois a home
    bate nisso com frequência (regra de cache do `rmt-architecture`).
  - **Eloquent only** (regra 5): últimos ~8 pedidos `OrderStatus::Completed`
    (`Order::where('status', OrderStatus::Completed)->latest('completed_at')->take(8)->with('listing','seller')->get()`).
  - Agregado `in_escrow_count` = `Order::where('status', OrderStatus::AwaitingConfirmation)->count()`.
  - Usa o enum `OrderStatus` (regra 7) — nunca string crua.
- **`ActivityItemResource`** expõe **apenas dado público**:
  `{ type, game, title, amount_cents, completed_at, seller_name }`.
  - **Privacidade (regra anti-IDOR/segurança):** **zero dado do comprador**;
    `seller_name` e `amount` já são públicos na vitrine. Só `completed` aparece.
  - `seller_name` = nome do vendedor (já público no `ListingCard`).
- **Sem Policy** (leitura pública agregada, sem owner/tenant) — documentar no PR que é
  público intencional; garantir que nenhuma query escapa do filtro `completed`.
- **Octane-safe (regra 13):** Service sem estado mutável; cache via facade, não em propriedade.

### Frontend — `HomeActivity.vue`

- Componente novo em `frontend/app/components/`.
- Fetch: `useAsyncData('activity', () => api.get('/activity'), { server: false })`
  (client-side, sem top-level await em componente — pitfall já documentado).
- **Auto-refresh ~15–20s:** `setInterval` chamando `refresh()`, **limpo em `onUnmounted`**
  e guardado por `import.meta.client` (mesmo padrão do `DepositDialog` polling).
- UI: feed rolando "**{seller} vendeu {title} ({game})**" + stat pulsante
  "**{in_escrow_count} em escrow agora**". PrimeVue (`Tag`/`Badge`) onde couber; ticker
  é CSS custom (Aura não tem ticker) com comentário.
- Erros → `useToast()`, nunca `alert`. Estado vazio: esconder seção ou placeholder discreto.

## 6. Arquivos (estimativa)

**Backend (novo):**
- `app/Http/Controllers/Marketplace/ActivityController.php`
- `app/Services/ActivityFeedService.php` (`app/Services/` já existe — padrão `*Service.php`)
- `app/Http/Resources/Marketplace/ActivityItemResource.php` (Resources são namespaced por área)
- `routes/api.php` (+1 rota pública)
- `tests/` — feature test do endpoint (público, só completed, sem comprador, cache)

**Frontend (tema/global):**
- `frontend/nuxt.config.ts` (definePreset ouro + darkModeSelector + htmlAttrs)
- `frontend/app/assets/css/main.css` (tokens de raridade + superfícies)
- Auditoria dark em páginas/componentes listados em §2

**Frontend (home):**
- `HomeHero.vue`, `HomeHowItWorks.vue`, `HomeStats.vue`, `FeaturedListings.vue`,
  `ListingCard.vue`, `ListingGrid.vue` (recolor + efeitos)
- `HomeActivity.vue` (novo)
- `frontend/app/pages/index.vue` (nova ordem das seções)
- `frontend/app/types` (tipo `ActivityItem`)

## 7. Sequenciamento

Três frentes, candidatas ao pipeline **morpheus** (decidir no plano):
- **A — Tema + dark global** (Trinity): preset ouro, dark forçado, auditoria de contraste.
- **B — Home + efeitos** (Trinity): seções repaginadas, efeitos máximo, HomeActivity (UI).
- **C — Activity feed backend** (Neo + Eliot segurança + Oracle QA): endpoint, cache, testes.

Dependência: B (HomeActivity) consome C (endpoint). A pode ir em paralelo.

## 8. Riscos / pontos de atenção

- **Contraste no dark global** — risco de regressão em telas não-home; auditoria obrigatória.
- **Ouro como `primary`** — botões/preços dourados precisam de texto escuro (contrastColor);
  validar AA em todos os botões.
- **Performance dos efeitos "máximo"** — manter composite-only (transform/opacity), throttle
  rAF no spotlight, nada de layout thrash; testar em mobile (showcase já some < 860px).
- **Privacidade do feed** — nunca incluir comprador; só `completed`; revisar com `rmt-security`.
- **Cache do feed** — 15s evita flood; aceitar leve atraso na "atualidade".
- **Octane** — Service sem estado por request.

## 9. Fora de escopo (YAGNI)

- Toggle claro/escuro (decidido: dark fixo).
- Endpoint `/api/stats` de métricas históricas (transações totais, volume) — só feed recente.
- Libs de partícula/3D.
- Sons/áudio.
- i18n (app é pt-BR).

## 10. Critérios de aceite

- [ ] App inteiro em dark; nenhuma tela com texto ilegível (auditoria AA).
- [ ] `primary` é ouro; CTAs/preços com contraste AA.
- [ ] Home com cor de raridade por tipo/boost e efeitos máximo funcionando.
- [ ] Efeitos desligam com `prefers-reduced-motion: reduce`.
- [ ] `GET /api/activity` retorna só `completed`, sem comprador, cacheado e throttled.
- [ ] `HomeActivity` faz auto-refresh e limpa o interval em `onUnmounted`.
- [ ] Testes do endpoint passam (host: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan test`).
- [ ] Skills atualizadas (`rmt-frontend`, `rmt-context`, `rmt-architecture`, `rmt-schema` se preciso, `rmt-security`, `rmt-tests`).
- [ ] Vault: nota de feature + ADR (rebrand/dark global) criadas.
