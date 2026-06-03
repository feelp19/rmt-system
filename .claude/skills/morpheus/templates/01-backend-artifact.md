# Backend Artifact — contrato p/ Trinity (feature <slug>)

## Endpoints novos/alterados
| Método | Rota | FormRequest | Policy |
|---|---|---|---|
| <ex: POST> | <ex: /api/resources/{resource}/items> | <StoreItemRequest> | <ItemPolicy@create> |

## Resources de resposta (shape do JSON)
- `<XResource>`: { campo: tipo, ... }

## Eventos WebSocket emitidos
- `<EventName>` no canal `<canal>` com payload `{ ... }`

## Contrato de dados para o Nuxt (SSR fetch + client)
- SSR (`useFetch` / `useAsyncData`): rota, método, shape esperado do retorno.
- Client-side (composable `use<X>`): estado reativo, mutações disponíveis.
- Props de página (se a rota Nuxt recebe params): `<prop>`: <tipo/shape>

## Regras de negócio que ficam no BACK (Trinity NÃO replica)
- <ex: cálculo de X, validação de Y>

## RESULTADO
status: PASS | FAIL | BLOQUEADOR
score: 0-100
findings:
- <critério da rubrica que bateu, com -pontos>
