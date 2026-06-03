# .matrix/ — Runs do Matrix Pipeline

Diretório de handoff de artefatos entre os agentes do pipeline
(Morpheus / Neo / Trinity / Eliot / Oracle).
Cada run é versionada no git para rastreabilidade completa do ciclo de vida
da feature.

## Invocação

```
/morpheus <feature-slug>
```

O Morpheus recebe um **feature-slug** (ex.: `autenticacao-jwt`) ou um
**brief** textual descrevendo o que deve ser construído, e orquestra os
demais agentes até a conclusão.

## Agentes

| Agente       | Papel                                      |
|--------------|--------------------------------------------|
| **Morpheus** | Orquestrador — plano, checkpoints, score   |
| **Neo**      | Backend — contratos, lógica, APIs          |
| **Trinity**  | Frontend — componentes, UX, integração     |
| **Eliot**    | Segurança — laudo de vulnerabilidades      |
| **Oracle**   | QA — casos Golden/Dirty, pass/fail         |

A comunicação entre agentes ocorre **exclusivamente** via arquivos de
artefato listados abaixo. Nenhum estado é mantido em variáveis de sessão
ou mecanismos externos.

## Estrutura de diretórios

    .matrix/runs/<slug>/
      00-plan.md              Morpheus (CP1): plano aprovado
      01-backend-artifact.md  Neo (CP2): contrato p/ Trinity
      02-frontend-notes.md    Trinity (CP3): notas de integração
      03-security-eliot.md    Eliot: laudo de segurança
      04-qa-oracle.md         Oracle: casos Golden/Dirty, pass/fail
      05-adversarial.md       (opcional) revisão adversarial cross-model
      99-score.md             Morpheus: score agregado final
      run.log                 Timeline — quem rodou, quando, qual modelo

## Versionamento

Os artefatos de cada run são commitados em um commit **separado** do código
da feature, e.g.:

    chore: matrix run <slug>

Isso mantém o histórico de decisões desacoplado das mudanças de código.

## Memória institucional

A memória permanente do projeto vive em `.vault/`.
Ao final do pipeline (CP5), o Morpheus cria uma nota Feature, Bug ou ADR
no vault com as decisões arquiteturais e lições aprendidas da run.
