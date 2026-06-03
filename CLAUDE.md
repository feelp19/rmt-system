# rmt-system — CLAUDE.md

Backend API Laravel 13 (Octane/FrankenPHP, worker mode) + frontend Nuxt 4 SSR (PrimeVue, tema Aura) desacoplado; filas Redis via Horizon; observabilidade Pulse + log-viewer; auth Sanctum.

## Skills — carregar conforme o contexto

| Tarefa | Skill |
|---|---|
| Visão geral, início de conversa, controllers, rotas | `rmt-context` |
| Models, migrations, Eloquent, banco de dados | `rmt-schema` |
| Services, Policies, Jobs, filas, cache | `rmt-architecture` |
| Qualquer arquivo em `frontend/` | `rmt-frontend` |
| Revisão de código, novos endpoints, PRs | `rmt-security` |
| Qualquer arquivo em `tests/` | `rmt-tests` |
| Implementar feature grande via pipeline multi-agente | `morpheus` (orchestrator: Neo/Trinity/Eliot/Oracle + adversarial-review) |

## Regras imutáveis

1. **Lógica sempre no Service** — Controller é thin wrapper, nunca tem lógica de negócio.
2. **Autorização via Policy** — `$this->authorize()` em todo endpoint; nunca `return true` em FormRequest.
3. **Nunca aceitar `user_id` como input** — sempre `auth()->id()`.
4. **IDOR**: buscar recursos sem filtrar pelo owner/tenant é proibido; recurso inexistente retorna 404 (não 403). Escopar toda query por dono ou tenant; em mismatch, retornar 404.
5. **Eloquent ORM sempre, sem exceção por conveniência** — toda leitura/escrita no banco passa por Eloquent. `DB::table()`, `DB::raw()` e `DB::select()` são **proibidos por padrão**. Só aceitos em expressão SQL que o query builder comprovadamente não expressa, ou bulk em tabela sem model — nesse caso exige comentário obrigatório no código explicando por que Eloquent não atende.
6. **Respostas JSON via API Resource** — toda resposta JSON retornada por controller usa uma classe em `app/Http/Resources/`. Proibido `response()->json($model)` ou `return $collection->map(...)` direto de controller/service. Criar a Resource no mesmo PR que cria o endpoint.
7. **Enums em PHP, NUNCA `ENUM` no SQL** — todo enum vive em `app/Enums/` como `enum X: string` (PHP 8.1+ backed). Migration usa `string` (tamanho adequado) ou `tinyInteger`. Model declara `$casts`. FormRequest valida via `Rule::enum()`.
8. **`DB::beginTransaction` explícito, NUNCA com closure** — usar somente quando há múltiplas escritas inter-dependentes em tabelas distintas. Padrão obrigatório: `DB::beginTransaction(); try { ... DB::commit(); } catch (\Throwable $e) { DB::rollBack(); throw $e; }`. `DB::transaction(fn () => ...)` está proibido.
9. **NUNCA `migrate:fresh` — em lugar nenhum, absolutamente nunca** — `php artisan migrate:fresh`, `migrate:refresh`, `db:wipe` ou qualquer comando que dropa tabelas está proibido em qualquer ambiente. Schema evolui só por `migrate` (forward) + migrations reversíveis. Bug em migration já aplicada localmente: criar nova migration que corrige. Toda migration precisa de `down()` funcional.
10. **Código sempre legível, nunca macarrônico** — o critério é "um colega entende em primeira leitura". Proibido: one-liner encadeado quando `foreach` resolve; ternário aninhado; nomes abreviados (`$pv`, `$aw`, `$tmp`); métodos gigantes com múltiplos níveis de `if`. Quebrar em variável nomeada, early return ou método privado.
11. **Frontend: componente-first** — toda feature frontend nasce como componente em `frontend/app/components/`. A página (`frontend/app/pages/`) apenas importa e monta componentes. Proibido concentrar template/lógica de feature nova direto na página.
12. **Frontend: PrimeVue primeiro, HTML cru por exceção** — todo componente novo usa componentes PrimeVue (`Button`, `Card`, `Dialog`, `DataTable`, `InputText`, `Tag`, `SelectButton`, `TabView`, etc.) como primeira escolha. HTML cru, classe CSS personalizada e animação JS só são aceitos quando o componente PrimeVue equivalente não existe ou sua API não suporta o comportamento exigido — nesse caso, comentário no código justificando.
13. **Octane/FrankenPHP worker mode** — sem estado mutável em singletons; cuidado com `Request` em bindings de container. Serviços ligados como singleton não podem armazenar estado por request.
14. **Análise obrigatória ao finalizar feature** — antes de commitar uma feature, bug ou refactor, **invocar `Agent(subagent_type="rmt-feature-finisher", ...)`**. O subagent roda checklist completo de arquitetura, segurança (`rmt-security` interno), frontend, vault e skills desatualizadas. Corrigir todos os bloqueadores na mesma sessão antes de commitar. Hook `PreToolUse` em `.claude/hooks/finish-feature-check.sh` bloqueia commits ≥ 30 linhas que não passaram pelo subagent — bypass legítimo apenas com `SKIP_FINISH_CHECK=1 git commit ...` (usar só após o subagent ter aprovado ou em commits triviais).
15. **Consultar o vault antes de começar** — antes de iniciar feature, debug ou refactor, rodar `Grep` em `.vault/` pelo tópico/área afetada. Começar por ADR(s) relacionados em `.vault/Decisoes/`, bugs em `.vault/Bugs/`, e abrir `.vault/MOC.md` se a área não for óbvia. Não reimplementar decisão já documentada sem revisitá-la.
16. **Build/dev do frontend** — o frontend é um app Nuxt separado em `frontend/`. Build: `make front-build` (ou `cd frontend && npm run build`). Dev: `make front-dev` (ou `cd frontend && npm run dev`). Backend roda via Octane/FrankenPHP (`make up`). Proibido `npm run build` na raiz do projeto ou dentro de `docker compose exec` do container backend.

## Manutenção das skills — obrigatório

Toda vez que uma feature for implementada ou alterada, as skills afetadas **devem ser atualizadas antes de encerrar a tarefa**. Isso inclui:

- Novo controller ou método → atualizar `rmt-context`
- Novo model, campo, enum ou relacionamento → atualizar `rmt-schema`
- Novo Service, Policy, Job, regra de cache ou fila → atualizar `rmt-architecture`
- Nova página, componente ou padrão de frontend → atualizar `rmt-frontend`
- Nova regra de segurança, rate limit ou vulnerabilidade corrigida → atualizar `rmt-security`
- Novo padrão de teste, factory ou fixture → atualizar `rmt-tests`

Não encerrar a tarefa com skills desatualizadas.

## Vault — segundo cérebro

O projeto mantém um vault em `.vault/` versionado junto com o código.

**Ao finalizar qualquer feature, bug relevante ou decisão arquitetural**, criar a nota correspondente antes de encerrar a tarefa:

| O que foi feito | Pasta | Template |
|---|---|---|
| Feature nova ou grande alteração | `.vault/Features/` | `Templates/Feature.md` |
| Decisão arquitetural relevante | `.vault/Decisoes/` | `Templates/ADR.md` |
| Bug relevante corrigido | `.vault/Bugs/` | `Templates/Bug.md` |
| Início ou encerramento de sprint | `.vault/Sprints/` | `Templates/Sprint.md` |

**Convenção de nomes:**
- Features, Bugs, Sprints: `YYYY-MM-DD nome-descritivo.md`
- ADRs: `ADR — titulo-da-decisao.md` (sem data — decisão é atemporal; data fica no frontmatter)

As notas são criadas a partir dos templates em `.vault/Templates/`. Preencher todos os campos — especialmente "Decisões técnicas" e "Como evitar no futuro".

## Pipeline Matrix

O diretório `.matrix/` contém a configuração do pipeline multi-agente (Neo/Trinity/Eliot/Oracle). Consultar `.matrix/` ao orquestrar features grandes via `morpheus`.

## Estrutura rápida

```
app/Http/Controllers/               ← thin wrappers
app/Models/                         ← Eloquent models
app/Providers/                      ← service providers
routes/api.php                      ← todas as rotas (API-only)
config/horizon.php                  ← filas Redis
config/pulse.php                    ← observabilidade
config/octane.php                   ← runtime Octane/FrankenPHP
frontend/app/pages/                 ← Nuxt 4 pages (SSR, PrimeVue)
frontend/app/components/            ← componentes reutilizáveis
frontend/app/composables/           ← composables Vue 3
.vault/                             ← segundo cérebro (vault Obsidian)
.matrix/                            ← pipeline Matrix (multi-agente)
```
