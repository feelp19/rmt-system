# Design — Portar framework de IA (task-tracker → rmt-system)

**Date:** 2026-06-03
**Status:** Approved (design), pending implementation plan
**Project:** rmt-system
**Fonte:** `/home/felipe/Desktop/task-tracker`

## Goal

Replicar em `rmt-system` o framework de desenvolvimento assistido por IA do `task-tracker`
(regras CLAUDE.md/AGENTS.md, skills `.claude/skills`, agents `.claude/agents`, orquestração
Matrix `.matrix` + morpheus, vault Obsidian `.vault`, hook de gate + settings versionado),
**adaptado** à stack do rmt-system e **sem** o conteúdo de domínio do task-tracker.

## Decisões (locked)
- **Idioma:** PT-BR (igual à fonte).
- **Conteúdo do vault:** só esqueleto + templates + índices stub (Features/Bugs/Decisoes/Conceitos vazios).
- **Prefixo:** `rmt-*` p/ skills/agents do projeto; agents Matrix mantêm `neo/trinity/eliot/oracle`; skill `morpheus` mantém nome.

## Mapa rename / skip

| Fonte (task-tracker) | Destino (rmt-system) |
|---|---|
| `.claude/skills/taskero-context` | `.claude/skills/rmt-context` |
| `.claude/skills/taskero-schema` | `.claude/skills/rmt-schema` |
| `.claude/skills/taskero-architecture` | `.claude/skills/rmt-architecture` |
| `.claude/skills/taskero-frontend` | `.claude/skills/rmt-frontend` (reescrito) |
| `.claude/skills/taskero-security` | `.claude/skills/rmt-security` |
| `.claude/skills/taskero-tests` | `.claude/skills/rmt-tests` |
| `.claude/skills/taskero-mcp` | **SKIP** (rmt sem MCP) |
| `.claude/skills/morpheus/**` | `.claude/skills/morpheus/**` (adaptado) |
| `.claude/agents/taskero-code-locator` | `.claude/agents/rmt-code-locator` |
| `.claude/agents/taskero-feature-finisher` | `.claude/agents/rmt-feature-finisher` |
| `.claude/agents/taskero-pr-reviewer` | `.claude/agents/rmt-pr-reviewer` |
| `.claude/agents/taskero-security-audit` | `.claude/agents/rmt-security-audit` |
| `.claude/agents/{neo,trinity,eliot,oracle}.md` | mesmo nome (adaptado) |
| `.matrix/**`, `.vault/**` | mesmo layout (conteúdo recriado) |

## Regras de adaptação (aplicar em TODO arquivo portado)

1. **Stack:** `Laravel 12 + Inertia + Vue3 + Vuetify + MCP + workspace/board/card + ULID/BigInt`
   → `Laravel 13 API-only + Nuxt 4 SSR + PrimeVue (Aura) + Octane/FrankenPHP + Horizon/Pulse/log-viewer + Sanctum`.
2. **Frontend:** `resources/js/{Pages,Components}` (Inertia) → `frontend/app/{pages,components,composables}` (Nuxt 4, app separado).
3. **UI:** Vuetify (`v-btn/v-card/v-dialog/v-data-table/v-text-field`) → PrimeVue (`Button/Card/Dialog/DataTable/InputText/...`), preset Aura já em `frontend/nuxt.config.ts`.
4. **Branch base:** `master` → `main`. **Container:** `task-tracker-app-1` → `rmt-system-app-1` (serviço `app`).
5. **Drop:** todas refs a MCP (`app/Mcp`, `taskero-mcp`), domínio multitenancy (workspace/board/list/card), Reverb/Echo específicos, PushinPay/RagUp/Trello, Liquid Glass.
6. **IDOR/escopo:** generalizar de `workspace_id` p/ coluna owner/tenant genérica (rmt ainda sem modelos de domínio → regra fica como convenção/direção).
7. **`npm run build` proibido** (vite-in-docker) → realidade Nuxt: build via `make front-build` / `nuxt build`, dev via `make front-dev` / `nuxt dev`; FrankenPHP/Octane no backend.
8. **Octane:** manter regra "sem estado mutável em singletons sob worker mode" (rmt roda Octane/FrankenPHP — aplica direto).
9. **API-first:** "resposta JSON via API Resource" vira o caminho padrão (rmt É API pura; sem ressalva de páginas Inertia).
10. **Skills context/schema:** rmt quase não tem domínio (`app/` só Controllers/Models/Providers; `routes/api.php` = `/health`,`/user`). Estes skills ficam finos/aspiracionais — descrevem stack + convenções, sem inventar modelos/dirs inexistentes.

## Componentes

### 1. Regras (raiz)
- `CLAUDE.md` (novo) — manter regras imutáveis genéricas (thin controller/Service, Policy authz,
  `auth()->id()` nunca `user_id` do client, no-IDOR scope+404, Eloquent-only ban `DB::table/raw`,
  legibilidade, **nunca** `migrate:fresh`/`db:wipe`, enums PHP backed nunca SQL ENUM,
  `DB::beginTransaction` explícito, API Resources, skill-maintenance, consultar-antes-de-codar);
  reescrever identidade/stack; tabela skill-routing → `rmt-*` (sem MCP); regra UI PrimeVue;
  mapa de dirs rmt (`app/Http/Controllers`, `app/Models`, `app/Providers`, `routes/api.php`,
  `frontend/app/{pages,components,composables}`); seção vault → `.vault/`; regra do gate → `rmt-feature-finisher` + hook.
- `AGENTS.md` (novo) — espelho condensado, vendor-neutral, **em sincronia** com CLAUDE.md.

### 2. Skills `.claude/skills/rmt-*/SKILL.md`
Frontmatter `user-invocable: false`, 1ª seção `## Quando usar`. Matriz de carregamento:
Controller/route→`rmt-context`; Model/migration→`rmt-schema`; Service/Policy/Job/Cache/realtime→`rmt-architecture`;
`frontend/`→`rmt-frontend`; `tests/`→`rmt-tests`; sempre→`rmt-security`.
- `rmt-architecture` — adaptar (alto reuso): thin-controller→FormRequest→Service→Resource, Policy, Octane no-singleton-state, Horizon supervisor-por-fila, `WithoutOverlapping`, cache-tags, HTTP externo timeout/retry/SSRF. Reancorar exemplos em rmt; add Pulse; remover Company/Plan/Trello/Card.
- `rmt-security` — adaptar (joia): thin controller, FormRequest `authorize()` real, identidade de `auth()` nunca do request, **404 anti-enumeração** não 403, IDOR scope filtering, mass-assignment, defense-in-depth, upload/SSRF; add Sanctum token/abilities + CSRF-stateless/CORS (SPA+API); dropar MCP-gate; LGPD só stub se aplicável.
- `rmt-tests` — adaptar: PHPUnit class-style + RefreshDatabase, naming, Feature/Unit, fakes, testes de regressão IDOR/escopo, query-count; trocar `assertInertia`→assertJson/assertJsonStructure; container `rmt-system-app-1`; env de `phpunit.xml` real do rmt.
- `rmt-context` — esqueleto + conteúdo novo do rmt (stack, áreas, onde começar, regras transversais, refs). Fino (sem domínio).
- `rmt-schema` — esqueleto (formato Mapa de domínio/Hierarquia/campos+PK/enums) derivado de `database/migrations` + `app/Models` reais (hoje: User + tabelas default + `personal_access_tokens` + `pulse_*`).
- `rmt-frontend` — **reescrever do zero**: Nuxt 4 (`frontend/`), pages/components/composables, `useFetch`/`$fetch` à API, SSR caveats, PrimeVue component-first (mapa substituindo a tabela Vuetify), tema Aura; princípios reusados (component-first, página fina, toggle otimista).

### 3. Agents `.claude/agents/`
- `rmt-code-locator.md` (haiku; Read/Grep/Glob/Bash) — formato caveman `DEF/USES/REL`, read-only refuse; "Estrutura conhecida" reescrita p/ árvore rmt (frontend/ Nuxt; sem `app/Mcp`; tests class-style PHPUnit).
- `rmt-feature-finisher.md` (sonnet; Skill) — git diff → camadas → carrega skills via matriz rmt (sem MCP) → checklist → verifica nota vault + skills atualizadas. Branch base `main`.
- `rmt-pr-reviewer.md` (sonnet) — tabela de regras re-sincronizada ao CLAUDE.md rmt (regra UI = PrimeVue; component-first→Nuxt; build→Nuxt) + N+1/mass-assignment/migration; 1 linha/finding.
- `rmt-security-audit.md` (sonnet) — catálogo OWASP/CWE ~as-is (mais portável do conjunto); step1 carrega `rmt-security`; cauda "regras imutáveis" → numeração rmt; XSS de Blade/Vuetify→Nuxt/PrimeVue/Vue; add Sanctum token/abilities; branch `main`.
- Matrix `neo` (backend Laravel — adaptar skills→rmt, contrato sem Inertia props→API/Nuxt), `trinity` (frontend — **reescrita mais pesada**: PrimeVue/Nuxt SSR, Toast PrimeVue, sem Liquid Glass), `eliot` (security 2-pass Claude+Codex — adaptar só skill name + checklist + branch), `oracle` (QA Golden/Dirty `php artisan test` — adaptar skill name + container).

### 4. Matrix
- `.claude/skills/morpheus/SKILL.md` — manter arquitetura CP0-CP5, dispatch paralelo Eliot+Oracle (CP4), loop Oracle→Neo (máx 3), estado só-por-arquivo, score agregado, hard gate, contrato Codex. Adaptar: input MCP `get_card_details` → **brief manual / nota vault** (rmt sem card MCP); CP5 gate `taskero-feature-finisher`→`rmt-feature-finisher`; grep `.vault/`; "skills Taskero afetadas"→rmt. PT-BR.
- `.claude/skills/morpheus/rubrics.md` — pesos/gate/bands as-is; seção Neo Laravel-portável; **reescrever seção Trinity** (Vuetify/Liquid Glass→PrimeVue/Nuxt).
- `.claude/skills/morpheus/templates/result-block.md` — **as-is**.
- `.claude/skills/morpheus/templates/01-backend-artifact.md` — adaptar "Props via Inertia"→contrato Nuxt/API SSR.
- `.matrix/README.md` — adaptar (pipeline genérico mantido; `.vault/` repointado; "card-id"→brief/feature-slug).
- `.matrix/runs/.gitkeep` — dir vazio (nada de runs do task-tracker).

### 5. Vault `.vault/`
- Dirs vazios: `Features/ Bugs/ Decisoes/ Conceitos/ Sprints/ Compliance/`.
- `Templates/{Feature,ADR,Bug,Sprint}.md` — as-is + 2 ajustes (checklist "Skills atualizadas"→`rmt-*`; enum `area:` p/ áreas rmt).
- `.obsidian/{app,appearance,core-plugins,graph}.json` — as-is (newFileFolderPath `Features`, attachments `.attachments`).
- Índices stub: `README.md` (título rmt, scaffolding mantido, tag taxonomy rmt — dropar PIX/MCP/IA), `MOC.md` (tabela "rota rápida" + clusters vazios; skills→rmt), `Glossario.md` (stub vazio, frontmatter `type: glossario`), `Divida-Tecnica.md` (stub, Pendente/Resolvido vazios, DT-01 em diante).
- `Sprints/README.md` — as-is (genérico).
- `Compliance/ROPA.md` — stub vazio (frontmatter + header da tabela + intro), sem operações taskero.
- `Skills/` — symlinks → `../../.claude/skills/rmt-*/SKILL.md`.

### 6. Config / hook / gitignore
- `.claude/settings.json` (versionado, novo) — `permissions.deny`: `php artisan migrate:fresh*`, `php artisan db:wipe*` (manter); **adaptar** bans de build (rmt buildar Nuxt é legítimo → remover bans `npm/yarn/pnpm run build`; manter ban de build dentro de `docker compose exec`). `permissions.allow`: lista read-only (git status/diff/log/show/branch, artisan route:list/migrate:status/list/about/config:show/event:list/queue:failed/schedule:list, phpunit/pest, composer show/why/outdated/audit, npm ls/outdated/audit/info, docker compose ps/logs, find/grep/rg/ls/wc/head/tail/stat/tree). `hooks.PreToolUse` Bash → `$CLAUDE_PROJECT_DIR/.claude/hooks/finish-feature-check.sh`. (Não tocar `settings.local.json` existente.)
- `.claude/hooks/finish-feature-check.sh` — copiar verbatim; trocar subagent `taskero-feature-finisher`→`rmt-feature-finisher`; texto pt-br p/ áreas rmt; manter threshold 30 linhas, bypass `SKIP_FINISH_CHECK=1`, skip `--amend/--no-edit/merge`. `chmod +x`.
- `.gitignore` — adicionar: `.vault/.obsidian/workspace.json`, `.vault/.obsidian/workspace-mobile.json`, `.vault/.obsidian/cache`, `.vault/.trash/`, `.vault/.attachments/`, `.superpowers/`, `.claude/settings.local.json`.

## Comportamento novo (atenção)
- **Hook PreToolUse** passa a **bloquear `git commit`** quando o diff staged ≥30 linhas, exigindo
  rodar `rmt-feature-finisher` antes. Bypass: `SKIP_FINISH_CHECK=1 git commit ...`. (Regra fiel ao task-tracker.)
- Matrix (`/morpheus`) + `rmt-security-audit` pass-2 usam `codex` CLI se presente; degradam se ausente.

## Fora de escopo
`taskero-mcp` · `.impeccable/design.json` (gerar depois via impeccable) · conteúdo do vault
(Features/Bugs/ADRs/Conceitos/Glossario) · `settings.local.json` · `.superpowers/` · ROPA real.

## Pronto quando (aceite)
1. Árvore criada: `CLAUDE.md`, `AGENTS.md`, `.claude/skills/rmt-*` (6), `.claude/agents/rmt-*` (4) + `neo/trinity/eliot/oracle`, `.claude/skills/morpheus/**`, `.matrix/{README.md,runs/.gitkeep}`, `.vault/**`, `.claude/settings.json`, `.claude/hooks/finish-feature-check.sh`.
2. `grep -rIl 'taskero\|workspace_id\|Inertia\|Vuetify\|app/Mcp\|PushinPay\|RagUp\|Liquid Glass' CLAUDE.md AGENTS.md .claude .matrix .vault` → vazio (exceto, se houver, exemplos genéricos claramente neutros). Sem `taskero-mcp`.
3. Hook referencia `rmt-feature-finisher`; `bash -n finish-feature-check.sh` ok; executável.
4. `.matrix/runs/` vazio; `.vault/Skills/` symlinks resolvem p/ skills `rmt-*`.
5. `php artisan test` segue passando (sanity — nada de runtime quebrado).
6. CLAUDE.md/AGENTS.md descrevem stack rmt (L13 API + Nuxt4 SSR + PrimeVue + Octane/FrankenPHP + Horizon/Pulse).
