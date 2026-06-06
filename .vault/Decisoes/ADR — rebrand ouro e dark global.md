---
date: 2026-06-06
type: adr
status: accepted
area: frontend
tags: [frontend, dark-mode, tema, primevue, aura, acessibilidade, identidade-visual]
---

# ADR: Rebrand `primary` verde→ouro + dark mode forçado no app inteiro

## Contexto

A homepage foi considerada simples demais e sem identidade visual forte para um marketplace
de gold/itens de jogos. O pedido era um visual gaming/loot-RPG. A iteração anterior usava
accent verde (padrão Aura) com light/dark opcional — funcional, mas genérico. Dois problemas
se tornaram evidentes ao desenhar o redesign:

1. **Coerência de identidade**: uma home com ouro e dark sobre fundo escuro, mas o restante
   do app em verde sobre fundo claro, cria uma experiência fragmentada. O usuário que navega
   para perfil ou wallet sai do contexto visual do marketplace de gold.
2. **Paleta verde não carrega semântica de "valor/riqueza"**: ouro é a metáfora universal
   de valor em jogos (moeda, loot, raridade). Verde funcionaria para um marketplace genérico,
   não para um de gold/itens premium.

## Decisão

- **Rebrand global `primary`→ouro** (`#F5C542`) via `definePreset` do PrimeVue Aura, com
  `contrastColor` escuro declarado explicitamente para garantir WCAG AA em todos os contextos
  (botão dourado com texto escuro, preço dourado, badge de raridade).
- **Dark mode forçado em todo o app** via `darkModeSelector: '.app-dark'` no preset Aura +
  `htmlAttrs: { class: 'app-dark' }` no `nuxt.config.ts`. A classe é aplicada no `<html>`
  em SSR e permanece — sem toggle, sem `prefers-color-scheme`.
- **Tokens CSS de raridade globais** em `main.css`: `--gold`, `--rare`, `--epic`, `--legend`.
  Derivados do boost tier + tipo de item; reutilizáveis em qualquer componente futuro sem
  nova decisão de cor.

## Consequências

**Positivas:**
- Identidade visual coesa: qualquer tela do app (perfil, wallet, pedidos, vitrine) compartilha
  a mesma paleta ouro-dark, reforçando o posicionamento gaming/loot.
- `contrastColor` explícito elimina a classe de bugs de contraste que surgem ao personalizar
  paletas fora do padrão PrimeVue — botão dourado sempre tem texto legível.
- Tokens de raridade reutilizáveis: `ListingCard`, `FeaturedListings` e futuros componentes
  de produto usam `var(--epic)` etc. sem duplicar valores.
- Dark forçado simplifica testes visuais: só um modo a auditar, não dois.

**Negativas / trade-offs:**
- **Auditoria de contraste obrigatória** em todas as telas ao aplicar dark global: cores claras
  hardcoded em componentes que assumiam fundo claro quebram silenciosamente. Exige grep +
  revisão visual em cada área do app. O custo foi pago nesta feature (1 fix real encontrado:
  `.num` em `HomeHowItWorks` usava `#fff` como fallback desnecessário).
- **Sem dark/light toggle**: usuários que preferem light mode não têm opção. Decisão consciente
  — identidade sobre preferência individual nesta fase. Pode ser revisada se houver demanda
  expressiva.
- **`contrastColor` não é automático no Aura para paletas personalizadas**: qualquer nova
  paleta custom exige teste manual de contraste + declaração explícita de `contrastColor`.
  Documentado como padrão obrigatório para próximas customizações de tema.

## Alternativas descartadas

| Alternativa | Motivo do descarte |
|---|---|
| Mudar só a home (home dourada, resto verde) | Incoerente — usuário sai do contexto visual ao navegar; reforça percepção de "landing separada" em vez de produto unificado |
| Dark via `prefers-color-scheme: dark` | Identidade inconsistente entre usuários com preferência `light`; impossível garantir a experiência loot-RPG para todos |
| Dark via toggle opt-in | Maioria dos usuários veria a versão "errada" por padrão; aumenta superfície de testes (dois modos); adiaria a identidade definitiva para "quando o usuário escolher" |
| Manter verde, só adicionar ouro como accent | Verde + ouro colidem semanticamente (verde = dinheiro fiat; ouro = loot/valor gaming); a paleta dupla sobrecarregaria o visual sem ganho de identidade |

## Features que aplicam esta decisão

- [[2026-06-06 homepage-redesign-loot]]

## Atualizações no vault

- [x] Adicionei link no cluster "Decisões Arquiteturais" do `MOC.md`
- [ ] Se padrão recorrente: criei nota em `.vault/Conceitos/` apontando pra cá
- [ ] Atualizei skill correspondente em `.claude/skills/` se a decisão muda regra ativa

## Links relacionados

- [[2026-06-06 homepage-redesign-loot]]
- [[2026-06-05 homepage]]
