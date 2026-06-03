---
name: rmt-frontend
user-invocable: false
---

# rmt-system — Frontend

## Quando usar

- Qualquer alteração em `frontend/` (pages, components, composables, nuxt.config.ts, estilos)
- Ajustes de UX, novos componentes ou integrações com a API REST

---

## Stack & layout

| Item | Detalhe |
|---|---|
| Framework | Nuxt **4.4.x** (`nuxt: ^4.4.6`) |
| Modo | SSR ligado (`ssr: true`) |
| Raiz do código | `frontend/app/` (`srcDir` padrão do Nuxt 4) |
| Roteamento | `frontend/app/pages/` (file-based) |
| Componentes | `frontend/app/components/` (auto-import) |
| Composables | `frontend/app/composables/` (auto-import) |
| Config Nuxt | `frontend/nuxt.config.ts` |
| UI | **PrimeVue 4** via `@primevue/nuxt-module` — auto-import ON |
| Tema | Preset **Aura** (`@primeuix/themes/aura`), `darkModeSelector: 'system'` |
| Ícones | `primeicons` — classe `pi pi-<nome>` (css global: `primeicons/primeicons.css`) |
| Vue | Vue 3 com `<script setup>` (sem Options API) |

`@primevue/nuxt-module` faz auto-import de todos os componentes PrimeVue.
**Nunca** adicione `import { Button } from 'primevue/button'` no `.vue` — é desnecessário e quebra a tree-shaking do módulo.

Estrutura mínima de um arquivo de página:

```vue
<script setup lang="ts">
const { data: items, refresh } = await useAPI<Item[]>('/items')
</script>

<template>
  <DataTable :value="items">
    <Column field="name" header="Nome" />
  </DataTable>
</template>
```

---

## Data fetching / API — o composable `useAPI`

Localização: `frontend/app/composables/useAPI.ts`

```ts
export function useAPI<T>(path: string, opts: Parameters<typeof useFetch>[1] = {}) {
  const config = useRuntimeConfig()
  const base = import.meta.server ? config.apiBase : config.public.apiBase
  return useFetch<T>(`${base}${path}`, opts)
}
```

### Por que duas URLs?

| Contexto | Base usada | Valor padrão | Override |
|---|---|---|---|
| SSR (servidor Nuxt) | `config.apiBase` | `http://app/api` | `NUXT_API_BASE` |
| Browser (client) | `config.public.apiBase` | `/api` (relativo) | `NUXT_PUBLIC_API_BASE` |

- **Prod**: Nuxt e o backend ficam na mesma rede Docker. O servidor Nuxt acessa o backend via `http://app/api` (nome do serviço Caddy/backend). O browser usa `/api` (same-origin via Caddy).
- **Dev**: `nitro.devProxy` redireciona `/api` → `http://localhost:8000/api`, então o browser nunca lida com CORS.

### Padrão de uso

```vue
<script setup lang="ts">
// top-level await funciona em <script setup> com SSR
const { data: health, refresh } = await useAPI<{ status: string }>('/health')
</script>

<template>
  <p>API: {{ health?.status ?? 'down' }}</p>
  <Button label="Recarregar" icon="pi pi-refresh" @click="refresh()" />
</template>
```

`useAPI` retorna o mesmo objeto que `useFetch` (`.data`, `.pending`, `.error`, `.refresh()`).
Para mutation (POST/PUT/DELETE) use `$fetch` diretamente ou passe `{ method: 'POST', body: payload }` como segundo argumento de `useAPI`.

---

## Caveats SSR

- `await useAPI(...)` no top-level de `<script setup>` é correto e recomendado — Nuxt 4 suporta.
- Evite estado mutável em escopo de módulo; use `useState()` ou `ref` dentro de `<script setup>`.
- Para código exclusivo do browser: `import.meta.server` / `import.meta.client`, ou bloco `<ClientOnly>`.
- Para re-fetch após ação do usuário: chame `refresh()` (retorno de `useAPI`/`useFetch`) ou `refreshNuxtData(key)`.
- Cookies de autenticação/CSRF devem ser repassados ao SSR com `useFetch` headers ou middleware — o browser não os envia automaticamente no servidor.

---

## PrimeVue — mapeamento necessidade → componente

Usar **sempre** o componente PrimeVue adequado. HTML cru só quando PrimeVue não cobre o caso (adicionar comentário explicando).

| Necessidade | Componente PrimeVue |
|---|---|
| Botão de ação | `<Button label="..." icon="pi pi-*" />` |
| Cartão/painel | `<Card>` com slots `#title`, `#subtitle`, `#content`, `#footer` |
| Modal/diálogo | `<Dialog v-model:visible="show" header="..." modal>` |
| Tabela de dados | `<DataTable :value="rows"> <Column field="..." header="..." /> </DataTable>` |
| Campo de texto | `<InputText v-model="val" />` |
| Textarea | `<Textarea v-model="val" rows="4" />` |
| Select simples | `<Select v-model="val" :options="opts" optionLabel="label" />` |
| Select múltiplo | `<MultiSelect v-model="vals" :options="opts" optionLabel="label" />` |
| Toggle on/off | `<ToggleSwitch v-model="active" />` |
| Seleção exclusiva | `<SelectButton v-model="val" :options="opts" />` |
| Abas | `<Tabs v-model:value="tab"> <TabList> <Tab value="a">...</Tab> </TabList> <TabPanels>...</TabPanels> </Tabs>` |
| Chip / label | `<Tag :value="texto" severity="info" />` |
| Badge numérico | `<Badge :value="count" />` |
| Notificação toast | `useToast()` + `<Toast />` no layout |
| Confirmação de ação | `useConfirm()` + `<ConfirmDialog />` no layout |
| Barra de progresso | `<ProgressBar :value="pct" />` |
| Spinner | `<ProgressSpinner />` |
| Mensagem inline | `<Message severity="warn">...</Message>` |

**Proibido**: APIs nativas de diálogo do browser (`alert/confirm/prompt`). Use sempre `useToast()` e `useConfirm()` do PrimeVue.

---

## Tema e estilo

- Preset **Aura** importado em `nuxt.config.ts` via `@primeuix/themes/aura`.
- `darkModeSelector: 'system'` — tema dark ativa automaticamente pelo `prefers-color-scheme` do OS.
- `cssLayer: false` — tokens PrimeVue não ficam em `@layer`, portanto têm especificidade normal.
- `ripple: true` — efeito ripple global nos componentes interativos.
- Prefira as props de estilo e classes de severidade do PrimeVue (`severity`, `outlined`, `text`, `size`) ao invés de CSS custom.
- Só adicione `<style scoped>` quando os tokens do PrimeVue forem insuficientes; evite sobrescrever variáveis CSS internas do tema.
- Ícones: `pi pi-<nome>` (ver lista em https://primevue.org/icons). Não instale outras libs de ícones.

---

## Princípios de desenvolvimento

1. **Componente-first**: páginas em `app/pages/` devem ser finas — montam componentes, não contêm lógica. Extraia em `app/components/` quando a página ultrapassar ~150 linhas ou quando o bloco for reutilizável.
2. **Toggle otimista**: para toggles de estado (ex.: ativo/inativo), atualize o UI imediatamente e reverta + mostre toast em caso de erro do backend.
3. **Backend é autoridade**: nunca replique regras de negócio no frontend. Valide só UX básica (campos vazios). Deixe o backend rejeitar com mensagem de erro e exiba via toast.
4. **Sem diálogos nativos do browser**: use `useToast()` para notificações e `useConfirm()` para ações destrutivas — nunca `alert/confirm/prompt`.
5. **`<script setup>` sempre**: sem Options API, sem `defineComponent` verboso.
6. **TypeScript**: tipar retornos de `useAPI<T>` e props de componentes.

---

## Build e dev

### Desenvolvimento local

```bash
# pela raiz do projeto (recomendado)
make front-dev

# ou diretamente
cd frontend && npm run dev
# Nuxt sobe em http://localhost:3000
# devProxy: /api → http://localhost:8000/api (backend Laravel Octane)
```

### Build de produção

```bash
make front-build
# ou
cd frontend && npm run build
# Gera frontend/.output/
```

### Container Docker (SSR)

O `Dockerfile` faz multi-stage build:
1. `node:22-alpine` (build): `npm ci && nuxt build`
2. `node:22-alpine` (runtime): copia `.output/` e executa:

```
node .output/server/index.mjs
```

Porta exposta: `3000`. Variáveis de ambiente injetadas em runtime:
- `NUXT_API_BASE` — URL interna do backend (servidor Nuxt → backend)
- `NUXT_PUBLIC_API_BASE` — URL pública da API (browser; padrão `/api`)
