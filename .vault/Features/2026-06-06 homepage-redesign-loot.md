---
date: 2026-06-06
type: feature
status: implemented
area: frontend
tags: [frontend, homepage, redesign, dark-mode, gaming, loot, primevue, aura, backend, api]
---

# Feature: Redesign "loot RPG" da homepage + tema global + endpoint de atividade

## O que foi implementado

A homepage foi inteiramente repaginada com identidade visual gaming/loot-RPG e o tema global
do app foi rebranded. O que antes era um visual gaming-bold com accent verde virou um sistema
coeso de ouro + dark forçado + acentos de raridade.

**Tema global:**
- `primary` rebranded de verde para ouro (`#F5C542`) via `definePreset` Aura com
  `contrastColor` escuro para garantir WCAG AA em botão/preço dourado.
- Dark mode forçado em todo o app (`darkModeSelector: '.app-dark'` + `htmlAttrs` no
  `nuxt.config.ts`). Sem toggle — identidade gaming coesa é o requisito.
- Tokens CSS de raridade globais em `main.css`: `--gold`, `--rare`, `--epic`, `--legend`.

**Nova ordem das seções na home:**
Hero → Activity → Como funciona → Loot em alta → Stats → Vitrine.

**Componentes novos/alterados:**
- `HomeActivity.vue` — feed ao vivo de transações recém-concluídas, sem await top-level
  (pitfall Suspense/async component documentado). Consome `GET /api/activity`.
- `HomeHero.vue` — efeitos máximo: spotlight no mouse (rAF), aurora, sheen, glow com raridade.
- `HomeHowItWorks.vue` — auditoria de contraste: o `.num` usava fallback `#fff`; corrigido
  para `var(--ink)` sobre superfície escura.
- `HomeStats.vue` — banda de confiança rebranded.
- `ListingCard.vue` — raridade derivada de boost tier + tipo (metáfora loot).
- `FeaturedListings.vue` — "Loot em alta" com glow por raridade.

**Efeitos visuais:** spotlight, aurora, sheen, glow/raridade, reveal on scroll — todos
implementados via CSS/JS mínimo (rAF no spotlight, zero lib de partícula). Todos
subordinados a `prefers-reduced-motion: reduce`.

**Backend novo — `GET /api/activity` (público, sem auth):**
- `ActivityController` → delega para `ActivityFeedService`.
- `ActivityFeedService` — busca as N transações concluídas mais recentes, cacheia 15 s no
  Redis (leve atraso aceitável). Retorna só dados públicos: tipo, preço, jogo, categoria,
  seller name — **sem dado de comprador** (`completed`; anonimizado).
- `ActivityItemResource` — serializa cada item; expõe raridade derivada.
- Rota registrada em `routes/api.php`.
- 5 testes em `tests/Feature/Marketplace/ActivityTest.php` travando: status 200, estrutura
  JSON, chaves expostas (não expõe buyer), cache hit, anonimização.

## Decisões técnicas

- **Dark forçado em vez de toggle/`system`** — identidade gaming exige consistência visual
  entre sessões e usuários. Um toggle ou `prefers-color-scheme: dark` deixa a home dourada
  para metade dos usuários sobre fundo claro, quebrando a coerência. Custo: auditoria de
  contraste de todas as telas (encontrou 1 fix real: `.num` no HowItWorks).
- **`primary` ouro com `contrastColor` escuro** — ouro é cor clara; sem `contrastColor`
  explícito, o Aura usaria texto branco sobre botão dourado, quebrando WCAG AA. O preset
  `definePreset` do PrimeVue aceita `contrastColor` por paleta.
- **Feed `/api/activity` cacheado 15 s** — atividade ao vivo não precisa de sub-segundo;
  15 s reduz N queries por segundo na vitrine principal sem tornar o feed obsoleto.
  Escolha deliberada de leve atraso vs. custo de query.
- **Sem dado de comprador no feed** — o buyer não consentiu com exposição pública do histórico
  de compra. Só `completed` (sem nome), seller name e preço (já públicos no anúncio) são
  expostos. Teste específico trava essa invariante.
- **Raridade derivada de boost tier + tipo** — não é campo no banco; é lógica de apresentação
  em `utils/rarity.ts`. Tier 3 + item raro = `legendary`. Extensível sem migration.
- **`HomeActivity` sem top-level `await useAsyncData`** — componente filho com top-level
  await vira async component e exige `<Suspense>` no pai. Padrão: `useAsyncData` sem await
  + reactive `pending`. Pitfall já documentado na nota anterior da homepage.
- **Efeitos via CSS/JS mínimo** — zero lib de partículas. Spotlight usa `mousemove` +
  `requestAnimationFrame`; aurora e sheen são `@keyframes` CSS. Escolha por: bundle menor,
  controle total sobre `prefers-reduced-motion`, sem colisão de dependências.

## Arquivos criados / modificados

| Arquivo | O que mudou |
|---|---|
| `frontend/nuxt.config.ts` | `definePreset` ouro, `darkModeSelector`, `htmlAttrs`, `htmlAttrs.class` |
| `frontend/app/assets/css/main.css` | tokens `--gold/--rare/--epic/--legend`, `overflow-x:clip`, fix `.num` |
| `frontend/app/utils/rarity.ts` | utilitário de raridade derivada (boost tier + tipo) |
| `frontend/app/plugins/reveal.ts` | plugin **universal** da diretiva `v-reveal` (reveal on scroll) |
| `frontend/app/components/HomeActivity.vue` | componente novo — feed ao vivo |
| `frontend/app/components/HomeHero.vue` | spotlight, aurora, sheen, efeitos máximo |
| `frontend/app/components/HomeHowItWorks.vue` | fix contraste `.num`, rebranding ouro |
| `frontend/app/components/HomeStats.vue` | banda de confiança rebranded |
| `frontend/app/components/ListingCard.vue` | glow/raridade por tier |
| `frontend/app/components/FeaturedListings.vue` | "Loot em alta" + glow por raridade |
| `frontend/app/pages/index.vue` | nova ordem de seções: Hero→Activity→HowItWorks→Featured→Stats→Grid |
| `app/Http/Controllers/ActivityController.php` | controller novo, thin wrapper |
| `app/Services/ActivityFeedService.php` | service novo, lógica de feed + cache 15 s |
| `app/Http/Resources/ActivityItemResource.php` | resource novo, serializa + anonimiza |
| `routes/api.php` | rota `GET /api/activity` (pública) |
| `tests/Feature/Marketplace/ActivityTest.php` | 5 testes: status, estrutura, chaves, cache, anonimização |

## Pitfalls / o que me surpreendeu

- **Auditoria de contraste obrigatória ao forçar dark global**: ao mudar `darkModeSelector`
  para forçar dark no app inteiro, qualquer cor clara hardcoded em componente que assumia
  fundo claro quebra. Grep por cores claras hardcoded (`#fff`, `#ffffff`, `white`) em todos
  os componentes antes de commitar. O `.num` do `HomeHowItWorks` foi o único caso real.
- **Distinguir texto claro intencional de cor quebrada**: hero e stats têm texto claro
  propositalmente sobre superfícies escuras — não é bug. Só é bug quando o fundo também é
  claro (ou neutro). Contexto importa; não bastou grep cego.
- **`HomeActivity` sem top-level await**: a regra é: página pode `await useAsyncData`;
  componente filho não pode (vira async component, exige Suspense no pai). Padrão correto:
  chamar `useAsyncData` sem `await`, ler `{ data, pending, error }` de forma reativa.
- **`contrastColor` no preset Aura não é automático para cores personalizadas**: ao usar
  uma paleta completamente fora do padrão PrimeVue (ouro ≠ paleta muted), o cálculo
  automático de contraste pode errar. Declarar `contrastColor` explicitamente no `definePreset`.
- **Diretiva custom em plugin `.client` quebra o SSR (500)**: `v-reveal` foi registrada em
  `plugins/reveal.client.ts`. A home é SSR; no server a diretiva não existe →
  `resolveDirective` retorna `undefined` → `ssrGetDirectiveProps` lança
  *"Cannot read properties of undefined (reading 'getSSRProps')"* → **500 na home inteira**.
  **`npm run build` passou** — só explodiu no SSR runtime do container. Fix: plugin **universal**
  (`reveal.ts`, sem `.client`) + `getSSRProps() { return {} }`; `mounted`/`unmounted` continuam
  client-only. **Lição**: validar diretivas/efeitos com a app rodando (`curl localhost` no
  container), nunca confiar só no build. Pegou no usuário, não no finisher.

## Dívida técnica gerada

- Feed de atividade não tem paginação/cursor — N fixo de itens recentes. Se o volume crescer,
  adicionar cursor + `GET /api/activity?cursor=`. Registrar como DT-XX.
- Raridade não está exposta na listagem geral de anúncios (só no card da home/featured).
  Unificar lógica em server-side se necessário no futuro.
- Auditoria de contraste das demais telas (perfil, wallet, pedidos) em dark forçado foi feita
  visualmente mas não há testes automatizados de contraste. Considerar ferramenta de a11y CI.

## Skills atualizadas

- [ ] `rmt-context`
- [ ] `rmt-schema`
- [x] `rmt-architecture`
- [x] `rmt-frontend`
- [ ] `rmt-security`
- [x] `rmt-tests`

## Atualizações no vault

- [x] Adicionei link no cluster correto do `MOC.md`
- [ ] Se padrão recorrente: criei/atualizei nota em `.vault/Conceitos/`
- [x] Se decisão arquitetural: criei ADR em `.vault/Decisoes/` — `[[ADR — rebrand ouro e dark global]]`

## Links relacionados

- [[2026-06-05 homepage]]
- [[ADR — rebrand ouro e dark global]]
