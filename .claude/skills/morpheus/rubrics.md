# Rubricas de Score — Matrix Pipeline

Cada estágio devolve `score: 0-100` + a CONTA (qual critério bateu quanto), nunca só o número.

## Pesos na média final
- Eliot (segurança): 30%
- Oracle (QA): 30%
- adversarial-review: 20%
- Neo (backend): 10%
- Trinity (frontend): 10%

## Gate duro (independe da média)
- Eliot retornou BLOQUEADOR → score final 🔴, não commita.
- Oracle retornou FAIL → score final 🔴, não commita.

## Bandas
- 🟢 90-100: pode commitar
- 🟡 70-89: commita revisando os findings
- 🔴 <70: não commita, volta pro loop

## Eliot (começa em 100, subtrai)
- BLOQUEADOR (IDOR, user_id input, auth bypass, SQLi): −100 → 🔴
- Alto (sem rate limit, dado sensível em log, Policy faltando): −25 cada
- Médio (validação fraca, N+1 exposto): −10 cada
- Baixo (nit de hardening): −3 cada

## Oracle (objetivo)
- Base = % de testes passando.
- Golden path quebrado → FAIL → 🔴 direto.
- Dirty path (input inválido, sem permissão, recurso inexistente→404) coberto e passando: mantém nota.
- Faltou teste pra caminho novo: −15.

## adversarial-review
- 100 − (bloqueadores×40) − (findings substantivos aceitos×10) − (nits×3).
- Falso-positivo rejeitado pelo lead judgment não conta.

## Neo (auto-avaliação contra regras imutáveis — Laravel-portable, cada falha −15)
- lógica fora de Service
- DB::raw / DB::table sem comentário justificando
- response()->json sem Resource
- aceitou user_id como input
- usou ENUM no SQL (devia ser PHP App\Enums)
- método ilegível (one-liner encadeado, ternário aninhado, nome abreviado)

## Trinity (auto-avaliação — rmt stack: Nuxt 4 + PrimeVue Aura, cada falha −15)
- feature não virou componente (concentrou na página)
- HTML cru onde cabia PrimeVue (sem comentário justificando)
- window/native dialog em vez de useToast/useConfirm do PrimeVue
- página >150 linhas sem extrair componente
- ignorou tema Aura / design system do rmt
