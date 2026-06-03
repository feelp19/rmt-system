# AI Framework Port (task-tracker → rmt-system) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replicate the task-tracker AI dev framework (CLAUDE.md/AGENTS.md rules, `.claude/skills`, `.claude/agents`, the Matrix orchestration `.matrix`+morpheus, the `.vault` Obsidian KB, the commit-gate hook + versioned settings) inside rmt-system, adapted to its stack and stripped of task-tracker domain content.

**Architecture:** Port-by-transformation. Each task READS the task-tracker source file(s) under `/home/felipe/Desktop/task-tracker`, applies the documented rename + stack-swap transformations, and WRITES the adapted target under `/home/felipe/Desktop/rmt-system`. Domain content is recreated empty. The commit-gate hook + settings.json are created LAST so they don't gate the port's own commits.

**Tech Stack (target, rmt-system):** Laravel 13 API-only + Octane/FrankenPHP + Horizon/Pulse/log-viewer + Sanctum; separate Nuxt 4 SSR + PrimeVue (Aura) frontend in `frontend/`. PT-BR docs.

**Reference spec:** `docs/superpowers/specs/2026-06-03-ai-framework-port-design.md`

## Global transformation rules (apply in EVERY ported file)

- Skill rename: `taskero-context|schema|architecture|frontend|security|tests` → `rmt-…`; **drop** `taskero-mcp` entirely (no MCP row anywhere). Agent rename: `taskero-{code-locator,feature-finisher,pr-reviewer,security-audit}` → `rmt-…`. Matrix agents keep `neo/trinity/eliot/oracle`; skill `morpheus` keeps its name.
- Stack swap: Laravel 12 + Inertia + Vue3 + Vuetify + MCP + workspace/board/card + ULID/BigInt → Laravel 13 API-only + Nuxt 4 SSR + PrimeVue(Aura) + Octane/FrankenPHP + Horizon/Pulse/log-viewer + Sanctum.
- Frontend path: `resources/js/{Pages,Components}` → `frontend/app/{pages,components,composables}`.
- UI: Vuetify (`v-btn/v-card/v-dialog/v-data-table/v-text-field/useToastStore`) → PrimeVue (`Button/Card/Dialog/DataTable/InputText/useToast` + Toast). Drop "Liquid Glass".
- Branch base `master` → `main`. Container `task-tracker-app-1` → `rmt-system-app-1` (compose service `app`).
- Drop all MCP, multitenancy (workspace/board/list/card), Reverb/Echo, PushinPay/RagUp/Trello/PIX/LGPD-domain, ULID/BigInt id claims.
- IDOR rule: generalize `workspace_id` → a generic owner/tenant column (rmt has no domain models yet → rule is directional convention).
- "`npm run build` proibido (vite-in-docker)" → rmt reality: Nuxt build via `make front-build`/`nuxt build`, dev via `make front-dev`/`nuxt dev`; backend via FrankenPHP/Octane.
- Keep PT-BR. Keep `user-invocable: false` + `## Quando usar` skill convention.

## Per-file verification (grep gate)

After writing target files in a task, run (over ONLY the files that task produced):
```bash
grep -rIniE 'taskero|workspace_id|inertia|vuetify|app/Mcp|pushinpay|ragup|liquid glass|reverb|task-tracker-app-1|master\.\.\.HEAD' <target files>
```
Expected: NO matches (a deliberate generic example may remain only if clearly neutral — prefer zero). Fix any hit before commit.

---

## Task 0: Feature branch

- [ ] **Step 1:** `cd /home/felipe/Desktop/rmt-system && git checkout main && git checkout -b feat/ai-framework-port`
- [ ] **Step 2:** Verify: `git branch --show-current` → `feat/ai-framework-port`.

---

## Task 1: Rules — CLAUDE.md + AGENTS.md

**Files:** Create `CLAUDE.md`, `AGENTS.md` (repo root).

- [ ] **Step 1: Read sources** — `/home/felipe/Desktop/task-tracker/CLAUDE.md` and `AGENTS.md` in full.
- [ ] **Step 2: Write `CLAUDE.md`** preserving structure (skill-routing table, "regras imutáveis", skill-maintenance section, vault workflow section, "Estrutura rápida" dir map) with these concrete changes:
  - Header identity: "rmt-system — backend API Laravel 13 (Octane/FrankenPHP, worker mode) + frontend Nuxt 4 SSR (PrimeVue, tema Aura) desacoplado; filas Redis via Horizon; observabilidade Pulse + log-viewer; auth Sanctum."
  - Skill-routing table → rows: `app/Http/Controllers, routes/api.php → rmt-context`; `app/Models, database/migrations → rmt-schema`; `app/Services, app/Policies, app/Jobs, cache/filas → rmt-architecture`; `frontend/ → rmt-frontend`; `tests/ → rmt-tests`; `sempre → rmt-security`; `feature multi-agente → /morpheus`. NO MCP row.
  - Keep generic immutable rules verbatim in spirit: thin controller→Service; Policy authz; nunca `user_id` do client (usar `auth()->id()`); no-IDOR (escopar query por owner/tenant, 404 em mismatch); Eloquent-only (proibir `DB::table/raw/select`); legibilidade > esperteza; **NUNCA** `migrate:fresh`/`db:wipe` em qualquer ambiente; enums PHP backed (nunca SQL ENUM); `DB::beginTransaction` explícito (nunca closure); **toda** resposta JSON via API Resource; skill-maintenance obrigatório; consultar skill antes de codar.
  - UI rule: "PrimeVue primeiro (Button/Card/Dialog/DataTable/InputText/Tag/SelectButton/TabView); HTML cru só quando PrimeVue não cobre."
  - Gate rule: "Ao finalizar feature, invocar `Agent(subagent_type=\"rmt-feature-finisher\")`. Hook PreToolUse em `.claude/hooks/finish-feature-check.sh` bloqueia commit ≥30 linhas; bypass `SKIP_FINISH_CHECK=1`."
  - Octane rule: "Sob Octane/FrankenPHP worker mode: sem estado mutável em singletons; cuidado com Request em bindings."
  - "Estrutura rápida" dir map: `app/Http/Controllers/`, `app/Models/`, `app/Providers/`, `routes/api.php`, `config/{horizon,pulse,octane}.php`, `frontend/app/{pages,components,composables}/`, `.vault/` (segundo cérebro), `.matrix/` (pipeline Matrix). Drop `resources/js/Pages`, `app/Mcp/Tools/`, ULID/BigInt parenthetical.
- [ ] **Step 3: Write `AGENTS.md`** as the condensed vendor-neutral mirror (skill routing, 9-rule digest, 6-step workflow [localizar no MOC → ler ADRs/bugs → implementar → testes → atualizar skills → escrever nota no vault], conventions, pre-finish checklist). Keep ESPECIALLY the Octane no-mutable-singleton + stable-JSON-contract-via-Resources lines. Vault paths → `.vault/Features/`, `.vault/Bugs/`, `.vault/Decisoes/`, `.vault/MOC.md`. Keep strictly in sync with CLAUDE.md rule set.
- [ ] **Step 4: Verify** the grep gate over `CLAUDE.md AGENTS.md` → no forbidden tokens. Also `grep -c 'rmt-' CLAUDE.md` ≥ 6.
- [ ] **Step 5: Commit** — `git add CLAUDE.md AGENTS.md && git commit -m "feat: rmt CLAUDE.md + AGENTS.md (ported rules)"`

---

## Task 2: Skills — rmt-architecture, rmt-security, rmt-tests (high-reuse adapt)

**Files:** Create `.claude/skills/rmt-architecture/SKILL.md`, `.claude/skills/rmt-security/SKILL.md`, `.claude/skills/rmt-tests/SKILL.md`.

- [ ] **Step 1: Read sources** — task-tracker `.claude/skills/taskero-architecture/SKILL.md`, `taskero-security/SKILL.md`, `taskero-tests/SKILL.md`.
- [ ] **Step 2: Write `rmt-architecture/SKILL.md`** — frontmatter `name: rmt-architecture`, `user-invocable: false`; `## Quando usar` (Service/Policy/Job/cache/filas). Keep: thin-controller→FormRequest→Service→Resource flow; Policy authz; Octane no-singleton-state; Horizon supervisor-por-natureza-de-fila; `WithoutOverlapping` releaseAfter/expireAfter; cache-tags invalidation; HTTP externo timeout/retry/guard SSRF. Adapt: API Resource é o caminho padrão (sem ressalva Inertia); add Pulse (recorders, storage db); remover Company/Plan/Trello/Card/Reverb-específicos e reancorar exemplos em entidades genéricas (`Resource`/`Service`/`Job` neutros). Reference `config/horizon.php`, `config/pulse.php`, `config/octane.php`.
- [ ] **Step 3: Write `rmt-security/SKILL.md`** — frontmatter `name: rmt-security`, `user-invocable: false`; `## Quando usar` (sempre). Keep: "Backend é a autoridade"; thin controller; FormRequest `authorize()` real; identidade de `auth()` nunca do request; **404 anti-enumeração** (não 403); IDOR scope filtering (coluna owner/tenant); mass-assignment guards; defense-in-depth; upload/SSRF rules. Add: Sanctum tokens/abilities, CSRF stateless + CORS para SPA+API (Nuxt origin). Drop: MCP-gate section, WorkspaceAccessHelper/Company/Board naming; LGPD only a 1-line stub "se houver PII".
- [ ] **Step 4: Write `rmt-tests/SKILL.md`** — frontmatter `name: rmt-tests`, `user-invocable: false`; `## Quando usar` (tests/). Keep: PHPUnit class-style + RefreshDatabase; naming `test_<comportamento>_<condicao>`; Feature vs Unit; fakes (Storage/Queue/Mail); regressão IDOR/escopo; query-count constante. Adapt: assertions de API JSON (`assertJson`/`assertJsonStructure`/`assertOk`) — sem `assertInertia`; comando em container: `docker compose exec -T app php artisan test` (serviço `app`); derivar env de `phpunit.xml` real do rmt (mencionar que existe `tests/Feature/HealthTest.php`).
- [ ] **Step 5: Verify** grep gate over the 3 files → no forbidden tokens. Confirm each has `user-invocable: false` and `## Quando usar`.
- [ ] **Step 6: Commit** — `git add .claude/skills/rmt-architecture .claude/skills/rmt-security .claude/skills/rmt-tests && git commit -m "feat: rmt-architecture/security/tests skills"`

---

## Task 3: Skills — rmt-context, rmt-schema (thin, from rmt reality)

**Files:** Create `.claude/skills/rmt-context/SKILL.md`, `.claude/skills/rmt-schema/SKILL.md`.

- [ ] **Step 1: Gather rmt reality** — read rmt's `README.md`, `routes/api.php`, `routes/web.php`, `composer.json`, `frontend/nuxt.config.ts`; `ls app/Models database/migrations`. (Also peek at task-tracker `taskero-context`/`taskero-schema` ONLY for the section skeleton, not content.)
- [ ] **Step 2: Write `rmt-context/SKILL.md`** — frontmatter `name: rmt-context`, `user-invocable: false`; sections `## Quando usar` / `## Resumo do produto` / `## Stack` / `## Áreas do sistema` / `## Onde começar no código` / `## Regras transversais` / `## Referências rápidas`. Content from rmt reality: stack = Laravel 13 API + Nuxt 4 SSR + PrimeVue + Octane/FrankenPHP + Horizon/Pulse/log-viewer + Sanctum + MySQL/Redis; áreas = API (`routes/api.php`: `/health`,`/user`), frontend Nuxt (`frontend/`), filas (Horizon), observabilidade (Pulse/log-viewer), infra (Docker/Caddy single-origin). Note explicitly: "domínio ainda mínimo; convenções abaixo são direção."
- [ ] **Step 3: Write `rmt-schema/SKILL.md`** — frontmatter `name: rmt-schema`, `user-invocable: false`; sections `## Quando usar` / `## Mapa de domínio` / `## Hierarquia` / `## Modelos` / `## Enums`. Derive from real `app/Models` + `database/migrations`: currently `User` + framework tables (cache, jobs, sessions) + `personal_access_tokens` (Sanctum) + `pulse_*`. State "sem hierarquia de domínio ainda; atualizar este skill ao adicionar modelos."
- [ ] **Step 4: Verify** grep gate over both → no forbidden tokens; both have `user-invocable: false`.
- [ ] **Step 5: Commit** — `git add .claude/skills/rmt-context .claude/skills/rmt-schema && git commit -m "feat: rmt-context + rmt-schema skills (skeleton)"`

---

## Task 4: Skill — rmt-frontend (rewrite for Nuxt 4 + PrimeVue)

**Files:** Create `.claude/skills/rmt-frontend/SKILL.md`.

- [ ] **Step 1: Gather** — read rmt `frontend/nuxt.config.ts`, `frontend/app/app.vue`, `frontend/app/pages/index.vue`, `frontend/app/composables/useAPI.ts`, `frontend/package.json`. (Do NOT reuse the taskero-frontend body — it's Inertia/Vuetify, inapplicable.)
- [ ] **Step 2: Write `rmt-frontend/SKILL.md`** — frontmatter `name: rmt-frontend`, `user-invocable: false`; `## Quando usar` (frontend/). Content: Nuxt 4 layout (`frontend/app/{pages,components,composables}`, srcDir `app/`); SSR (`ssr: true`) caveats (dual-URL: server usa `apiBase` interno, client usa relativo via `useAPI`); data fetching `useFetch`/`$fetch` + o composable `useAPI` (server vs client base); PrimeVue component-first com tabela substituindo Vuetify (HTML→PrimeVue: botão→`Button`, card→`Card`, modal→`Dialog`, tabela→`DataTable`, input→`InputText`, select→`Select`, toast→`useToast`+`<Toast/>`, abas→`TabView`, chip→`Tag`); tema Aura + primeicons; auto-import on (usar `<Button>` sem import); princípios: component-first, página fina, toggle otimista. Strip everything Inertia/Vuetify/Liquid-Glass.
- [ ] **Step 3: Verify** grep gate → no `inertia|vuetify|liquid glass|resources/js`; has `user-invocable: false`.
- [ ] **Step 4: Commit** — `git add .claude/skills/rmt-frontend && git commit -m "feat: rmt-frontend skill (nuxt 4 + primevue)"`

---

## Task 5: Agents — rmt-code-locator, rmt-feature-finisher, rmt-pr-reviewer, rmt-security-audit

**Files:** Create `.claude/agents/rmt-code-locator.md`, `rmt-feature-finisher.md`, `rmt-pr-reviewer.md`, `rmt-security-audit.md`.

- [ ] **Step 1: Read sources** — the four `.claude/agents/taskero-*.md` in task-tracker.
- [ ] **Step 2: Write `rmt-code-locator.md`** — keep frontmatter (model haiku; tools Read/Grep/Glob/Bash) but `name: rmt-code-locator`; keep caveman `DEF/USES/REL` format + read-only refusal. Rewrite "Estrutura conhecida" to rmt tree: backend `app/Http/Controllers`, `app/Models`, `app/Providers`, `routes/api.php`; frontend Nuxt `frontend/app/{pages,components,composables}`; tests `tests/Feature` (class-style PHPUnit). Drop `app/Mcp`, Inertia, ULID/BigInt claims.
- [ ] **Step 3: Write `rmt-feature-finisher.md`** — `name: rmt-feature-finisher` (model sonnet; tools incl. Skill). Workflow: `git diff main...HEAD` → identify layers → load matching skills via the rmt matrix (Controller/route→rmt-context; Model/migration→rmt-schema; Service/Policy/Job→rmt-architecture; `frontend/`→rmt-frontend; `tests/`→rmt-tests; sempre→rmt-security; NO MCP row) → run each skill's checklist → verify a `.vault/` note exists → verify affected skills updated. PT-BR output.
- [ ] **Step 4: Write `rmt-pr-reviewer.md`** — `name: rmt-pr-reviewer` (sonnet). 1 line/finding, severity-tagged, no praise. Rules table synced to rmt CLAUDE.md (Service/Controller; `authorize()`; Nuxt build not `npm run build`-ban; `user_id` input; IDOR scope+404; security review; vault note; `DB::raw` ban; `response()->json` sem Resource; anti-macarrônico; component-first→Nuxt; UI=PrimeVue) + N+1/mass-assignment/migration checks. Base `main`.
- [ ] **Step 5: Write `rmt-security-audit.md`** — `name: rmt-security-audit` (sonnet), invoked by `/security-review`. Keep the full OWASP/CWE catalog (Injection, Mass Assignment, Access Control/IDOR/Path Traversal, SSRF/XXE, Auth/Session, Race/TOCTOU, Deserialization/Prototype Pollution, ReDoS/N+1, Headers/CORS/Cache Poisoning, Timing, File Upload/Zip Slip) over `git diff main...HEAD`. Step 1 loads `rmt-security`. Adapt tail "regras imutáveis" → rmt CLAUDE.md; XSS notes Blade/Vuetify → Nuxt/PrimeVue/Vue; add Sanctum token/abilities + stateless-CSRF emphasis. PT-BR + CWE + fix.
- [ ] **Step 6: Verify** grep gate over the 4 files → no forbidden tokens; each has a `name:` frontmatter; `grep -n 'main\.\.\.HEAD' rmt-feature-finisher.md rmt-pr-reviewer.md rmt-security-audit.md` present (branch base correct).
- [ ] **Step 7: Commit** — `git add .claude/agents/rmt-code-locator.md .claude/agents/rmt-feature-finisher.md .claude/agents/rmt-pr-reviewer.md .claude/agents/rmt-security-audit.md && git commit -m "feat: rmt-* workflow agents"`

---

## Task 6: Matrix agents — neo, trinity, eliot, oracle

**Files:** Create `.claude/agents/neo.md`, `trinity.md`, `eliot.md`, `oracle.md`.

- [ ] **Step 1: Read sources** — task-tracker `.claude/agents/{neo,trinity,eliot,oracle}.md`.
- [ ] **Step 2: Write `neo.md`** (backend; model sonnet; tools incl. Skill) — keep role/flow/re-run-loop. Load `rmt-context, rmt-schema, rmt-architecture`. Reads `00-plan.md`, implements Laravel under the rmt immutable rules, writes `01-backend-artifact.md` (contract: endpoints/Resources/eventos/contrato p/ Nuxt — NOT Inertia props), runs `php artisan test`, self-scores. IDOR rule → owner/tenant column.
- [ ] **Step 3: Write `trinity.md`** (frontend; sonnet; Skill) — HEAVIEST rewrite. Load `rmt-frontend` + `impeccable`. Reads `01-backend-artifact.md` as contract, implements **Nuxt 4 SSR + PrimeVue** UI under rmt rules (component-first under `frontend/app/components`; PrimeVue-first; toasts via PrimeVue `useToast`/`<Toast/>` not `window.alert`; página fina). Writes `02-frontend-notes.md`, self-scores. Drop Vuetify/Inertia/Liquid-Glass/`resources/js`/`npm run build`-ban; dev via `nuxt dev`.
- [ ] **Step 4: Write `eliot.md`** (security; sonnet) — keep two-pass Claude+Codex architecture + merge + severity + graceful Codex degradation (`codex exec --skip-git-repo-check -o <out> "<prompt>" 2>/dev/null`). Pass1 loads `rmt-security`, captures `git diff main...HEAD`, audits OWASP + rmt rules (IDOR/owner-scope, identity-from-auth, missing Policy, rate limit, sensitive-log, weak validation, N+1). Writes `03-security-eliot.md`.
- [ ] **Step 5: Write `oracle.md`** (QA; sonnet; tools Read/Grep/Bash/Skill) — keep Golden/Dirty methodology + FAIL→loop + scoring. Load `rmt-tests`, run `php artisan test` (container service `app` if needed). Writes `04-qa-oracle.md` ending with the RESULTADO block.
- [ ] **Step 6: Verify** grep gate over the 4 → no forbidden tokens; `grep -n 'main\.\.\.HEAD' eliot.md` present; trinity has no `vuetify|inertia|liquid glass`.
- [ ] **Step 7: Commit** — `git add .claude/agents/neo.md .claude/agents/trinity.md .claude/agents/eliot.md .claude/agents/oracle.md && git commit -m "feat: matrix subagents (neo/trinity/eliot/oracle)"`

---

## Task 7: Morpheus skill (orchestrator) + rubrics + templates

**Files:** Create `.claude/skills/morpheus/SKILL.md`, `rubrics.md`, `templates/result-block.md`, `templates/01-backend-artifact.md`.

- [ ] **Step 1: Read sources** — task-tracker `.claude/skills/morpheus/SKILL.md`, `rubrics.md`, `templates/result-block.md`, `templates/01-backend-artifact.md`.
- [ ] **Step 2: Write `morpheus/SKILL.md`** — frontmatter `name: morpheus`, `user-invocable: true`. Keep CP0–CP5 checkpoints, parallel Eliot+Oracle at CP4, Oracle→Neo loop (max 3), file-only state under `.matrix/runs/<slug>/`, score aggregation + hard gate, Codex contract + graceful degradation, RESULTADO parsing. Adapt: input source — replace MCP `get_card_details(card_id)` with "brief manual do usuário OU nota em `.vault/`" (rmt has no card MCP); CP0 reads the brief + greps `.vault/{Conceitos,Decisoes,Bugs,Divida-Tecnica}`; CP5 gate subagent `rmt-feature-finisher`; "atualiza skills rmt afetadas"; CP5 writes a `.vault/` note. PT-BR.
- [ ] **Step 3: Write `morpheus/rubrics.md`** — keep weights (Eliot30/Oracle30/adversarial20/Neo10/Trinity10), hard gate (BLOQUEADOR/FAIL→🔴), bands (🟢90-100/🟡70-89/🔴<70), and Eliot/Oracle/adversarial deduction sections verbatim. Neo section: Laravel-portable (DB::raw, response()->json sem Resource, user_id input, ENUM SQL). REWRITE Trinity section to rmt: "HTML cru onde cabia PrimeVue", "window.alert em vez de useToast", "página >200 linhas", "ignorou tema Aura/design system" (drop Liquid Glass).
- [ ] **Step 4: Write `templates/result-block.md`** — copy **verbatim** from source (it is generic; PT-BR labels).
- [ ] **Step 5: Write `templates/01-backend-artifact.md`** — keep the contract structure (tabela Método/Rota/FormRequest/Policy, Resources JSON, eventos, regras que ficam no back, RESULTADO block). Adapt the "Props recebidas via Inertia (se página)" section → "Contrato de dados para o Nuxt (SSR fetch + client)".
- [ ] **Step 6: Verify** grep gate over the 4 files → no forbidden tokens (result-block may be the only verbatim copy — confirm it had none). `grep -n 'user-invocable: true' .claude/skills/morpheus/SKILL.md`.
- [ ] **Step 7: Commit** — `git add .claude/skills/morpheus && git commit -m "feat: morpheus orchestrator skill + rubrics + templates"`

---

## Task 8: Matrix run dir + README

**Files:** Create `.matrix/README.md`, `.matrix/runs/.gitkeep`.

- [ ] **Step 1: Read source** — task-tracker `.matrix/README.md`.
- [ ] **Step 2: Write `.matrix/README.md`** — keep pipeline description (per-run dir `runs/<slug>/` with `00-plan..99-score` + `run.log`; artifacts versioned in a separate `chore: matrix run <slug>` commit; permanent memory in `.vault/`; agents Morpheus/Neo/Trinity/Eliot/Oracle). Adapt: input "card-id" → "feature-slug / brief"; keep `.vault/` reference (rmt has a vault). PT-BR.
- [ ] **Step 3: Create empty runs dir** — `mkdir -p .matrix/runs && touch .matrix/runs/.gitkeep`. Do NOT copy any task-tracker run.
- [ ] **Step 4: Verify** grep gate over `.matrix/README.md` → no forbidden tokens; `ls .matrix/runs` shows only `.gitkeep`.
- [ ] **Step 5: Commit** — `git add .matrix/README.md .matrix/runs/.gitkeep && git commit -m "feat: matrix run pipeline scaffold"`

---

## Task 9: Vault skeleton

**Files:** Create `.vault/` tree: `Templates/{Feature,ADR,Bug,Sprint}.md`, `.obsidian/{app,appearance,core-plugins,graph}.json`, `README.md`, `MOC.md`, `Glossario.md`, `Divida-Tecnica.md`, `Sprints/README.md`, `Compliance/ROPA.md`, `.gitkeep` files for empty content dirs, `Skills/` symlinks.

- [ ] **Step 1: Read sources** — task-tracker `.vault/Templates/*.md`, `.vault/.obsidian/{app,appearance,core-plugins,graph}.json`, `.vault/README.md`, `.vault/MOC.md`, `.vault/Glossario.md`, `.vault/Divida-Tecnica.md`, `.vault/Sprints/README.md`, `.vault/Compliance/ROPA.md`.
- [ ] **Step 2: Create dirs** — `mkdir -p .vault/{Features,Bugs,Decisoes,Conceitos,Sprints,Compliance,Templates,Skills,.obsidian}` and `touch .vault/{Features,Bugs,Decisoes,Conceitos}/.gitkeep`.
- [ ] **Step 3: Copy `.obsidian/{app,appearance,core-plugins,graph}.json` verbatim** (no domain coupling; keep `newFileFolderPath: Features`, `attachmentFolderPath: .attachments`). Do NOT copy `workspace.json`.
- [ ] **Step 4: Write `Templates/{Feature,ADR,Bug,Sprint}.md`** — copy structure verbatim, then two edits: (a) "Skills atualizadas" checklist items → `rmt-context/rmt-schema/rmt-architecture/rmt-frontend/rmt-security/rmt-tests` (NO mcp); (b) `area:` enum → rmt areas (`api | frontend | filas | observabilidade | infra | seguranca | arquitetura`). Keep `{{date}}/{{titulo}}` placeholders literal.
- [ ] **Step 5: Write `README.md`** — keep scaffolding (abrir como vault, diagrama de estrutura, convenção de nomes, tabela de templates, Graph View, Git). Title → "rmt-system — Segundo Cérebro". Replace tag taxonomy with rmt tags (`#api #frontend #nuxt #primevue #filas #horizon #observabilidade #pulse #infra #octane #seguranca #sanctum #arquitetura`); drop PIX/MCP/IA/planos tags.
- [ ] **Step 6: Write `MOC.md`** — keep "Rota rápida por tipo de tarefa" table shape + cluster headings (`## Conceitos`, `## Frontend`, `## Segurança`, `## Decisões Arquiteturais Fundacionais`, `## Documentos de referência`) but EMPTY of note links. Route-table skill refs → `rmt-*`.
- [ ] **Step 7: Write `Glossario.md`** (stub: frontmatter `type: glossario`, convention note, no terms), `Divida-Tecnica.md` (stub: frontmatter, intro, `## Pendente`/`## Resolvido` empty, "começar em DT-01"), `Sprints/README.md` (verbatim — generic), `Compliance/ROPA.md` (stub: frontmatter + table header + intro, no operations; drop Taskero/DPO identifiers).
- [ ] **Step 8: Create `Skills/` symlinks** — for each rmt skill: `ln -s ../../.claude/skills/rmt-context/SKILL.md .vault/Skills/rmt-context.md` (and rmt-schema, rmt-architecture, rmt-frontend, rmt-security, rmt-tests). Verify they resolve: `for f in .vault/Skills/*.md; do test -e "$f" && echo "OK $f" || echo "BROKEN $f"; done` → all OK.
- [ ] **Step 9: Verify** grep gate over all written vault files (exclude symlinks' targets) → no forbidden tokens; `.vault/Features/.gitkeep` etc. exist; obsidian `app.json` has `newFileFolderPath`.
- [ ] **Step 10: Commit** — `git add .vault && git commit -m "feat: vault skeleton (templates, obsidian, index stubs, skill symlinks)"`

---

## Task 10: Settings + commit-gate hook + .gitignore (LAST — activates the gate)

**Files:** Create `.claude/settings.json`, `.claude/hooks/finish-feature-check.sh`; Modify `.gitignore`.

- [ ] **Step 1: Read sources** — task-tracker `.claude/settings.json` and `.claude/hooks/finish-feature-check.sh`.
- [ ] **Step 2: Write `.claude/settings.json`** — `permissions.deny`: keep `Bash(php artisan migrate:fresh)`, `Bash(php artisan migrate:fresh:*)`, `Bash(php artisan db:wipe)`, `Bash(php artisan db:wipe:*)`, and `Bash(*docker compose exec * npm run build)`, `Bash(*docker compose exec * npm run build:*)`. DROP the bare `npm/yarn/pnpm run build` denies (rmt builds Nuxt legitimately). `permissions.allow`: the read-only list (git status/diff/log/show/branch/blame/stash list/remote -v; artisan route:list/migrate:status/list/about/config:show/model:show/event:list/queue:failed/schedule:list; vendor/bin/phpunit + pest; composer show/why/outdated/audit/--version; npm ls/outdated/audit/info; docker compose ps/logs, docker ps; wc/find/grep/rg/ls/file/stat/tree/head/tail). `hooks.PreToolUse`: matcher `Bash` → command `$CLAUDE_PROJECT_DIR/.claude/hooks/finish-feature-check.sh`. (Leave the existing `.claude/settings.local.json` untouched.)
- [ ] **Step 3: Write `.claude/hooks/finish-feature-check.sh`** — copy the source script verbatim, then: change the subagent name `taskero-feature-finisher` → `rmt-feature-finisher`; update the PT-BR `reason` text to rmt areas (arquitetura/segurança/frontend/vault/skills); keep the 30-line threshold, `SKIP_FINISH_CHECK=1` bypass, and the `--amend`/`--no-edit`/`merge` skips. `chmod +x .claude/hooks/finish-feature-check.sh`.
- [ ] **Step 4: Update `.gitignore`** — append:
  ```gitignore
  /.vault/.obsidian/workspace.json
  /.vault/.obsidian/workspace-mobile.json
  /.vault/.obsidian/cache
  /.vault/.trash/
  /.vault/.attachments/
  /.superpowers/
  /.claude/settings.local.json
  ```
- [ ] **Step 5: Verify** — `bash -n .claude/hooks/finish-feature-check.sh` (no syntax error); `test -x .claude/hooks/finish-feature-check.sh`; `grep -n rmt-feature-finisher .claude/hooks/finish-feature-check.sh`; `python3 -c "import json;json.load(open('.claude/settings.json'))"` (valid JSON); grep gate over settings.json + hook → no forbidden tokens (no `taskero`).
- [ ] **Step 6: Commit (bypass the gate it just installed)** — `SKIP_FINISH_CHECK=1 git add .claude/settings.json .claude/hooks/finish-feature-check.sh .gitignore && SKIP_FINISH_CHECK=1 git commit -m "feat: versioned settings + finish-feature commit gate"`

---

## Task 11: Verification

**Files:** none.

- [ ] **Step 1: Global grep for leftover coupling** —
  ```bash
  grep -rIniE 'taskero|workspace_id|inertia|vuetify|app/Mcp|pushinpay|ragup|liquid glass|task-tracker-app-1|master\.\.\.HEAD' \
    CLAUDE.md AGENTS.md .claude/skills/rmt-* .claude/agents .claude/skills/morpheus .matrix .vault/README.md .vault/MOC.md .vault/Templates .vault/Glossario.md .vault/Divida-Tecnica.md .vault/Compliance .claude/settings.json .claude/hooks 2>/dev/null
  ```
  Expected: NO matches.
- [ ] **Step 2: No stray taskero-mcp / mcp routing** — `grep -rIn 'rmt-mcp\|taskero-mcp' .claude CLAUDE.md AGENTS.md` → empty.
- [ ] **Step 3: Structure present** —
  ```bash
  ls .claude/skills | sort           # rmt-context rmt-schema rmt-architecture rmt-frontend rmt-security rmt-tests morpheus
  ls .claude/agents | sort           # rmt-* (4) + neo trinity eliot oracle
  ls .matrix/runs                    # only .gitkeep
  for f in .vault/Skills/*.md; do test -e "$f" && echo "OK $f" || echo "BROKEN $f"; done   # all OK
  ```
- [ ] **Step 4: Hook + JSON sanity** — `bash -n .claude/hooks/finish-feature-check.sh && python3 -c "import json;json.load(open('.claude/settings.json'))" && echo OK`.
- [ ] **Step 5: Backend sanity (nothing runtime broke)** — `php artisan test` → 3/3 pass (the AI framework files don't touch app runtime).
- [ ] **Step 6: Final commit** — `SKIP_FINISH_CHECK=1 git add -A && SKIP_FINISH_CHECK=1 git commit -m "chore: verified AI framework port" --allow-empty`

---

## Self-Review

**Spec coverage:** rules CLAUDE/AGENTS (T1), skills architecture/security/tests (T2), context/schema (T3), frontend (T4), workflow agents rmt-* (T5), Matrix agents (T6), morpheus+rubrics+templates (T7), .matrix README+runs (T8), vault skeleton+templates+obsidian+index stubs+symlinks (T9), settings+hook+gitignore (T10), verification (T11). All spec components mapped. Skips honored (taskero-mcp, design.json, vault content, settings.local.json, .superpowers, real ROPA).

**Ordering:** the commit-gate hook + settings.json are Task 10 (second-to-last) so they don't gate Tasks 0–9 commits; Task 10/11 commits use `SKIP_FINISH_CHECK=1`.

**Placeholder scan:** transformation rules are explicit (rename map, stack swap, per-file targets); no "TBD". For ported docs the "code" is the read-source→transform→write-target instruction set + the per-file grep gate.

**Name consistency:** skill names `rmt-{context,schema,architecture,frontend,security,tests}` consistent across CLAUDE.md routing (T1), skill files (T2-T4), agent skill-matrices (T5/T6), morpheus (T7), vault Skills symlinks + Templates checklist (T9), and verification (T11). Agent names `rmt-{code-locator,feature-finisher,pr-reviewer,security-audit}` + `neo/trinity/eliot/oracle` consistent across T5/T6, the hook subagent name (T10), and morpheus CP5 gate (T7). Branch base `main` consistent (T5/T6/T7 agents, hook). No MCP anywhere.
