# AGENTS.md — Diretrizes para Todos os Agentes de IA

Este arquivo vale para qualquer agente (Claude, Codex, etc.) atuando neste repositório.
Stack: Laravel 13 API-only (Octane/FrankenPHP) + Nuxt 4 SSR (PrimeVue, tema Aura) + Horizon + Pulse + Sanctum.

## 1) Roteamento por contexto (skills)

Use a skill conforme a área alterada:

- Visão geral, início de conversa, controllers, rotas (`app/Http/Controllers`, `routes/api.php`): `rmt-context`
- Models, migrations, Eloquent, banco (`app/Models`, `database/migrations`): `rmt-schema`
- Services, Policies, Jobs, cache, filas (`app/Services`, `app/Policies`, `app/Jobs`): `rmt-architecture`
- Qualquer arquivo em `frontend/`: `rmt-frontend`
- Revisão de código, endpoints e PRs: `rmt-security`
- Qualquer arquivo em `tests/`: `rmt-tests`
- Feature grande via pipeline multi-agente: `morpheus`

## 2) Regras imutáveis do projeto (digest)

1. Lógica de negócio fica em `app/Services` — controller é thin wrapper.
2. Autorização sempre via Policy (`$this->authorize()`).
3. Nunca aceitar `user_id` como input do cliente — usar `auth()->id()`.
4. Evitar IDOR: escopar toda query por owner/tenant; retornar `404` em mismatch (nunca `403`).
5. Banco via Eloquent por padrão; proibido `DB::table/raw/select` sem justificativa técnica com comentário no código.
6. Respostas JSON de controllers usam `app/Http/Resources/` — proibido `response()->json($model)` direto.
7. Enums em PHP (`app/Enums/`, backed enum `string`/`int`); nunca `ENUM` no SQL.
8. `DB::beginTransaction()` explícito (try/catch/rollBack); proibido `DB::transaction(fn () => ...)`.
9. Nunca `migrate:fresh`, `migrate:refresh` ou `db:wipe` em qualquer ambiente — schema evolui só por migrations forward.

## 3) Fluxo obrigatório de trabalho

1. **Localizar** a área no `.vault/MOC.md`.
2. **Ler** ao menos os ADRs relevantes em `.vault/Decisoes/` e bugs históricos em `.vault/Bugs/`.
3. **Implementar** mantendo os padrões existentes do módulo.
4. **Testar** — adicionar/ajustar testes proporcionais ao risco da mudança.
5. **Atualizar skills** afetadas em `.claude/skills/*/SKILL.md`.
6. **Registrar nota no vault**:
   - Feature: `.vault/Features/YYYY-MM-DD nome.md`
   - Bug: `.vault/Bugs/YYYY-MM-DD nome.md`
   - Decisão: `.vault/Decisoes/ADR — titulo.md`

## 4) Convenções de implementação

- Não mover regra de negócio para o frontend.
- Toda nova página Nuxt importa componentes de `frontend/app/components/`; nunca concentrar lógica direto na página.
- PrimeVue primeiro (`Button`, `Card`, `Dialog`, `DataTable`, `InputText`, `Tag`, `SelectButton`, `TabView`); HTML cru apenas quando PrimeVue não cobre — justificar com comentário.
- **Octane/FrankenPHP worker mode**: não manter estado mutável em singletons de container entre requests.
- **Contrato JSON estável**: toda resposta de endpoint passa por uma `app/Http/Resources/` — nunca alterar shape de Resource sem versionamento.
- Build do frontend via `make front-build` ou `nuxt build`; dev via `make front-dev` ou `nuxt dev`. Proibido `npm run build` direto (sem Docker).
- Branch base: `main`.

## 5) Checklist antes de encerrar

- [ ] Policy + escopo owner/tenant aplicados em todas as queries
- [ ] Sem regressão de segurança (IDOR, credenciais expostas, validações ausentes)
- [ ] Enums PHP (nunca SQL ENUM); `DB::beginTransaction` explícito se transação necessária
- [ ] Testes da mudança executados e passando
- [ ] Skills afetadas (`rmt-context`, `rmt-schema`, `rmt-architecture`, `rmt-frontend`, `rmt-security`, `rmt-tests`) atualizadas
- [ ] Nota no vault criada/atualizada
- [ ] `Agent(subagent_type="rmt-feature-finisher")` invocado (ou `SKIP_FINISH_CHECK=1` com justificativa)
