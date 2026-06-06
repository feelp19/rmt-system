---
name: rmt-frontend
user-invocable: false
---

# rmt-system — Frontend

## Quando usar

- Qualquer alteração em `frontend/` (pages, components, composables, nuxt.config.ts, estilos)
- Ajustes de UX, novos componentes ou integrações com a API REST

---

## Marketplace — o que já existe

**Composables** (`app/composables/`): `useApi()` (get/post/del com `Authorization: Bearer` lido do cookie `rmt_token`), `useAuth()` (token em cookie + `user` em `useState`, `login`/`register`/`logout`/`fetchMe`, `isAuthenticated`), `useWallet()` (saldo em `useState` + `refresh`/`deposit`). O scaffold `useAPI()` (useFetch) segue existindo para GET SSR simples.

**Utils** (`app/utils/`): `formatCents` (centavos → R$) e `reaisToCents`. Dinheiro trafega em **centavos inteiros**; a UI usa `InputNumber mode="currency"` em reais e converte na borda. `rarityFor(listing)` → `'gold'|'rare'|'epic'|'legend'` (boost tier manda: advanced→legend, intermediate→epic, basic→rare; senão gold se type=gold, rare se item). `RARITY_VAR` mapeia raridade → variável CSS (`--gold`, `--rare`, `--epic`, `--legend`).

**Auth**: token Bearer em cookie `rmt_token`. Plugin `auth.client.ts` hidrata o usuário no browser. Rotas privadas usam `definePageMeta({ middleware: 'auth' })` (`middleware/auth.ts` checa o cookie).

**Perfil / XP / imagens**: página `/perfil` (`ProfileSummary` = `AvatarUploader` + nível + barra `ProgressBar` + stats; grid de "meus anúncios" com editar/excluir) e `/ranking` (leaderboard). `LevelBadge` (badge "Nv X" ouro) aparece no header, nos cards (vendedor) e no perfil. **Upload de imagem**: `FileUpload mode="basic" :auto="false" custom-upload @select` pega o `File`; envia via **`FormData`** com `useApi().post` (NÃO setar Content-Type). Edição de anúncio multipart usa `_method=PUT` no FormData (spoofing). Foto/avatar exibidos por `<img>`/`<Avatar :image>` apontando pro endpoint id-based; placeholder quando `null`. Excluir usa `useConfirm()` (`<ConfirmDialog>` no layout).

**Carga de saldo (PIX/PushinPay)**: `DepositDialog` tem 2 caminhos — "Gerar PIX" (`POST /wallet/pix` → mostra QR via `<Image :src="qr_code_base64">` + copia-e-cola com `navigator.clipboard` guardado por `import.meta.client`, e faz **polling** `GET /wallet/pix/{id}` a cada 3s até `status=paid` → `refreshWallet`; limpa o `setInterval` em `watch(visible)` e `onUnmounted`) e "Crédito demo (instantâneo)" (`POST /wallet/deposit`, p/ testar sem pagar no sandbox).

**Components** (`app/components/`): `AppHeader`, `AuthCard` (Tabs login/registro), `ListingCard` (comprar; borda/glow/preço por raridade via `--rar = rarityFor(listing)`; badge "Turbinado" + botão "Turbinar" no anúncio próprio), `ListingFormDialog`, `WalletPanel` + `DepositDialog`, `OrderCard` (dupla confirmação por papel), `ListingGrid` (vitrine + dialog de criar), `FeaturedListings` (faixa "Loot em alta" = boostados), `BoostDialog` (escolhe tier via `SelectButton` + paga c/ carteira; `refreshNuxtData(['listings','featured'])` no sucesso), e a homepage `HomeHero` / `HomeActivity` / `HomeHowItWorks` / `HomeStats`. **Pages** (`app/pages/`): `index` (homepage combinada = Hero + Activity + ComoFunciona + FeaturedListings + Stats + ListingGrid), `login`, `wallet`, `orders` — finas, só montam componentes.

**`HomeActivity.vue`** (novo): ticker de atividade recente + contador "X em escrow agora". Usa `useAsyncData('activity', () => $fetch('/api/activity'), { server: false })` — **sem top-level await** (componente, não página). Auto-refresh a cada 18s via `setInterval` limpo em `onUnmounted`; guarda o timer sob `import.meta.client`.

**Homepage (`/`)**: vibe "gaming-bold loot/dark". **Tema global dark** forçado via `app.head.htmlAttrs.class: 'app-dark'` + `darkModeSelector: '.app-dark'` no preset PrimeVue. Cor primária **ouro** (`#F5C542`), definida via `definePreset` em `nuxt.config.ts` (escala 50–950, `contrastColor` escuro para contraste AA). Tipografia display **Bricolage Grotesque** (Google Fonts via `app.head`; aplicada a `h1/h2/h3`). **Tokens em `app/assets/css/main.css`**: `--bg` (fundo escuro), `--surf` (superfície), `--ink`/`--ink-soft` (texto), `--gold` (acento primário), `--rare` (azul), `--epic` (roxo), `--legend` (laranja). Classes `.reveal-init/.reveal-in` para animação de entrada. `HomeHero` = full-bleed (`width:100vw; margin-inline:calc(50% - 50vw)`; layout tem `overflow-x:clip`), assimétrico, type em `clamp()`, **showcase de cards de produto** flutuando; spotlight no mouse (rAF), aurora animada (`.hero::after`), sheen nos CTAs — tudo sob `@media (prefers-reduced-motion: no-preference)`. `HomeHowItWorks`/`HomeStats` recoloridos: kicker/trilho ouro, banda Stats com glow épico+ouro. `HomeStats` lê o cache da vitrine via `useNuxtData('listings')`. **Diretiva `v-reveal`**: plugin `app/plugins/reveal.client.ts` (IntersectionObserver, client-only, respeita `prefers-reduced-motion`); as 5 seções pós-hero entram com `v-reveal`. Botão "Anunciar" do `HomeHero`/`ListingGrid` compartilham `useState('show_listing_form')`. "Ver vitrine" = `scrollIntoView('#vitrine')` (`import.meta.client`).

> **Bans de design (skill `impeccable`)** respeitados na home: nada de **gradient text** (`background-clip:text`), nada de hero-metric template, nada de grade de cards idênticos. Realce = cor sólida + sublinhado `box-shadow inset`, nunca gradiente no texto.

**Padrão de fetch**: o grid usa `useAsyncData('listings', () => api.get(...), { server: false })` (client-side, sem top-level await em componente). Componentes que só precisam do mesmo dado leem `useNuxtData('listings')`. Erros do backend → `useToast()` (nunca `alert`). `<Toast />` fica no `layouts/default.vue`.

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
| Tema | Preset **Aura** customizado via `definePreset` (primário ouro `#F5C542`), dark global forçado (`.app-dark`), `darkModeSelector: '.app-dark'` |
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

- Preset **Aura** customizado via `definePreset` em `nuxt.config.ts`: cor primária **ouro** (`#F5C542`, escala 50–950, `contrastColor` escuro para AA).
- `darkModeSelector: '.app-dark'` — dark **forçado** no app inteiro via `app.head.htmlAttrs.class: 'app-dark'` (não depende de `prefers-color-scheme`).
- `cssLayer: false` — tokens PrimeVue não ficam em `@layer`, portanto têm especificidade normal.
- `ripple: true` — efeito ripple global nos componentes interativos.
- **Tokens de marca** em `app/assets/css/main.css`: `--bg`, `--surf`, `--ink`, `--ink-soft`, `--gold`, `--rare` (azul), `--epic` (roxo), `--legend` (laranja). Raridade de listing mapeada via `rarityFor(listing)` + `RARITY_VAR` (`app/utils/rarity.ts`).
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
