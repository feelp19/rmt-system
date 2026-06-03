# rmt-system — Segundo Cérebro

Vault Obsidian do projeto rmt-system. Contém o histórico de decisões, features implementadas, bugs, dívida técnica e sprints.

## Como abrir

No Obsidian: **Open folder as vault** → selecionar a pasta `.vault/` dentro do projeto.

## Estrutura

```
.vault/
├── Features/          ← Uma nota por feature implementada
├── Decisoes/          ← ADRs — decisões arquiteturais e técnicas
├── Bugs/              ← Post-mortems de bugs e auditorias
├── Sprints/           ← Retrospectivas de sprint
├── Skills/            ← Symlinks das skills do Claude (referência)
├── Divida-Tecnica.md  ← Documento vivo: itens adiados conscientemente
└── Templates/         ← Templates para novas notas
```

## Documento vivo: Dívida Técnica

`Divida-Tecnica.md` lista itens que foram **explicitamente adiados** com contexto de por quê e quando retomar. Atualizar ao final de cada sprint ou auditoria. Ao resolver um item, mover para a seção `Resolvido`.

## Templates disponíveis

| Template | Quando usar |
|---|---|
| `Templates/Feature.md` | Ao implementar uma nova feature |
| `Templates/ADR.md` | Ao tomar uma decisão arquitetural relevante |
| `Templates/Bug.md` | Ao corrigir um bug relevante ou fazer auditoria |
| `Templates/Sprint.md` | Ao encerrar uma sprint (retrospectiva) |

## Convenção de nomes

```
Features/YYYY-MM-DD nome-da-feature.md
Decisoes/ADR — titulo-da-decisao.md
Bugs/YYYY-MM-DD descricao-do-bug.md
Sprints/Sprint-XX YYYY-MM-DD.md
```

## Tags — clusters semânticos

Todas as notas têm `tags:` no frontmatter. **Sempre em português, snake-case curto.** Não usar variantes em inglês — quebra os clusters do Graph View e Grep por tag.

Tags padronizadas:

| Tag | Usado em |
|---|---|
| `#api` | Endpoints, rotas, controllers, resources |
| `#frontend` | Nuxt, componentes, páginas, composables |
| `#nuxt` | Nuxt-specific: SSR, plugins, layouts |
| `#primevue` | Componentes PrimeVue, temas, CSS |
| `#filas` | Jobs, workers, Horizon |
| `#horizon` | Horizon — painel de filas, supervisores, workers |
| `#observabilidade` | Métricas, logs, alertas, Pulse |
| `#pulse` | Laravel Pulse — painel de saúde da aplicação |
| `#infra` | Docker, deploy, VPS, Octane |
| `#octane` | FrankenPHP + Octane, worker mode |
| `#seguranca` | IDOR, autenticação, autorização, CSP |
| `#sanctum` | Laravel Sanctum, tokens, sessões |
| `#arquitetura` | Padrões estruturais, ADRs fundacionais |

## Graph View

Com tags e links bidirecionais (Features ↔ ADRs), o Graph View do Obsidian mostra os clusters:
- ADRs com muitas Features apontando → decisões de alto impacto
- Features com muitas tags → features transversais (tocar com cuidado)
- Tags com muitas notas → áreas de maior risco/atividade

## Git

Notas são versionadas junto com o código. Configurações pessoais do Obsidian ficam no `.gitignore`.
