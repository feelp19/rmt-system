# Homepage redesign — Loot/gaming (dark + ouro + raridade) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Repaginar a home (e o app inteiro) com identidade "loot RPG" — dark forçado + `primary` ouro + acentos de raridade + efeitos máximo — e adicionar um feed de atividade real (`GET /api/activity`).

**Architecture:** Três frentes. (C) Backend: endpoint público cacheado que lista vendas concluídas anonimizadas + contagem em escrow, via Controller fino → Service → Resource (Eloquent only). (A) Tema global: `definePreset` do Aura com escala ouro + `darkModeSelector` fixo. (B) Home: seções repaginadas + componente `HomeActivity` + efeitos CSS/JS sob `prefers-reduced-motion`.

**Tech Stack:** Laravel 13 (Octane), PHPUnit, Redis cache. Nuxt 4 SSR, PrimeVue 4 (Aura preset), `@primeuix/themes`, CSS custom + JS mínimo.

**Spec:** `docs/superpowers/specs/2026-06-06-homepage-redesign-loot-gaming-design.md`

**Comando de teste (host, ver memória):**
`DB_HOST=127.0.0.1 DB_PORT=3307 php artisan test`
**Build frontend:** `cd frontend && npm run build` · **Dev:** `cd frontend && npm run dev` (localhost:3000)

---

## File Structure

**Backend (novo):**
- `app/Http/Resources/Marketplace/ActivityItemResource.php` — shape público de 1 venda (sem comprador).
- `app/Services/ActivityFeedService.php` — query Eloquent + cache Redis 15s.
- `app/Http/Controllers/Marketplace/ActivityController.php` — thin wrapper.
- `routes/api.php` — +1 rota pública `/activity`.
- `tests/Feature/Marketplace/ActivityTest.php` — feature tests.

**Frontend (tema global):**
- `frontend/nuxt.config.ts` — `definePreset` ouro + `darkModeSelector: '.app-dark'` + `htmlAttrs.class`.
- `frontend/app/assets/css/main.css` — tokens de raridade/superfície + `body` bg.

**Frontend (home):**
- `frontend/app/types/index.ts` — `ActivityItem` + `ActivityResponse`.
- `frontend/app/utils/rarity.ts` — mapeia listing → raridade/cor.
- `frontend/app/components/HomeActivity.vue` — novo ticker ao vivo.
- `frontend/app/components/HomeHero.vue` — repaginado + efeitos.
- `frontend/app/components/ListingCard.vue` — glow/borda por raridade.
- `frontend/app/components/FeaturedListings.vue` — "Loot em alta" + raridade.
- `frontend/app/components/HomeHowItWorks.vue` — recolor dark.
- `frontend/app/components/HomeStats.vue` — recolor.
- `frontend/app/pages/index.vue` — nova ordem das seções.

---

# Fase C — Backend: feed de atividade (TDD)

> Faça esta fase **primeiro**: a `HomeActivity` (Fase B) consome este endpoint.

### Task C1: Endpoint `GET /api/activity`

**Files:**
- Test: `tests/Feature/Marketplace/ActivityTest.php`
- Create: `app/Http/Resources/Marketplace/ActivityItemResource.php`
- Create: `app/Services/ActivityFeedService.php`
- Create: `app/Http/Controllers/Marketplace/ActivityController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Marketplace/ActivityTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Endpoint é cacheado; cache do array store persiste entre métodos no
        // mesmo processo. Flush garante teste determinístico.
        Cache::flush();
    }

    private function completedSale(array $listing = [], ?User $seller = null): Order
    {
        $seller ??= User::factory()->create();
        $model = Listing::factory()->for($seller, 'seller')->create($listing);

        return Order::factory()->create([
            'listing_id' => $model->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function test_activity_feed_is_public_and_lists_completed_sales(): void
    {
        $this->completedSale(
            ['type' => 'gold', 'game' => 'WoW Retail', 'title' => '500k Gold'],
            User::factory()->create(['name' => 'Vendedor X']),
        );

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['type', 'game', 'title', 'amount_cents', 'completed_at', 'seller_name']],
                'in_escrow_count',
            ])
            ->assertJsonPath('data.0.title', '500k Gold')
            ->assertJsonPath('data.0.seller_name', 'Vendedor X');
    }

    public function test_activity_feed_excludes_non_completed_orders(): void
    {
        // awaiting_confirmation (default da factory) e cancelled não aparecem no feed.
        Order::factory()->create(['status' => OrderStatus::AwaitingConfirmation]);
        Order::factory()->create(['status' => OrderStatus::Cancelled]);

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_activity_feed_never_exposes_buyer_data(): void
    {
        $this->completedSale();

        $item = $this->getJson('/api/activity')->assertOk()->json('data.0');

        // Chaves exatas — qualquer dado de comprador faria isto falhar.
        $this->assertEqualsCanonicalizing(
            ['type', 'game', 'title', 'amount_cents', 'completed_at', 'seller_name'],
            array_keys($item),
        );
    }

    public function test_activity_feed_counts_orders_in_escrow(): void
    {
        Order::factory()->count(3)->create(['status' => OrderStatus::AwaitingConfirmation]);
        $this->completedSale();

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonPath('in_escrow_count', 3);
    }

    public function test_activity_feed_limits_to_eight_most_recent(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->completedSale();
        }

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan test --filter=ActivityTest`
Expected: FAIL — rota `/api/activity` retorna 404 (`assertOk` falha) / classes não existem.

- [ ] **Step 3: Create the Resource**

Create `app/Http/Resources/Marketplace/ActivityItemResource.php`:

```php
<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma venda concluída para o feed público da home.
 * NUNCA expõe dado do comprador (privacidade — spec §5). Vendedor e preço
 * já são públicos na vitrine.
 *
 * @mixin \App\Models\Order
 */
class ActivityItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->listing->type->value,
            'game' => $this->listing->game,
            'title' => $this->listing->title,
            'amount_cents' => $this->amount_cents,
            'completed_at' => $this->completed_at,
            'seller_name' => $this->seller->name,
        ];
    }
}
```

- [ ] **Step 4: Create the Service**

Create `app/Services/ActivityFeedService.php`:

```php
<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Monta o feed público da home: últimas vendas concluídas + nº em escrow.
 * Cacheado 15s — a home bate nisto com frequência. Octane-safe: sem estado
 * mutável de instância (cache via facade).
 */
class ActivityFeedService
{
    private const FEED_LIMIT = 8;

    private const CACHE_KEY = 'activity.feed';

    private const CACHE_TTL = 15;

    /** @return array{sales: Collection<int, Order>, in_escrow_count: int} */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $sales = Order::query()
                ->where('status', OrderStatus::Completed)
                ->with(['listing', 'seller'])
                ->latest('completed_at')
                ->limit(self::FEED_LIMIT)
                ->get();

            $inEscrowCount = Order::query()
                ->where('status', OrderStatus::AwaitingConfirmation)
                ->count();

            return [
                'sales' => $sales,
                'in_escrow_count' => $inEscrowCount,
            ];
        });
    }
}
```

- [ ] **Step 5: Create the Controller**

Create `app/Http/Controllers/Marketplace/ActivityController.php`:

```php
<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\ActivityItemResource;
use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityFeedService $activity) {}

    /** Feed público da home: vendas concluídas recentes + nº em escrow. */
    public function index(Request $request): JsonResponse
    {
        $snapshot = $this->activity->snapshot();

        return response()->json([
            'data' => ActivityItemResource::collection($snapshot['sales'])->resolve($request),
            'in_escrow_count' => $snapshot['in_escrow_count'],
        ]);
    }
}
```

- [ ] **Step 6: Register the route**

In `routes/api.php`, add the import near the other Marketplace controller imports (top of file):

```php
use App\Http\Controllers\Marketplace\ActivityController;
```

And add the route in the **public** block, next to `/listings` (≈ line 26):

```php
Route::get('/activity', [ActivityController::class, 'index'])->middleware('throttle:60,1'); // feed público da home (cacheado 15s)
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan test --filter=ActivityTest`
Expected: PASS (5 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Resources/Marketplace/ActivityItemResource.php \
        app/Services/ActivityFeedService.php \
        app/Http/Controllers/Marketplace/ActivityController.php \
        routes/api.php tests/Feature/Marketplace/ActivityTest.php
SKIP_FINISH_CHECK=1 git commit -m "feat(activity): endpoint público GET /api/activity (feed da home)"
```

> `SKIP_FINISH_CHECK=1` aqui é só pra commits parciais durante o plano; o `rmt-feature-finisher` roda na Fase D antes do commit final.

---

# Fase A — Tema global: ouro + dark forçado

### Task A1: Preset ouro + dark forçado + tokens de raridade

**Files:**
- Modify: `frontend/nuxt.config.ts`
- Modify: `frontend/app/assets/css/main.css`

- [ ] **Step 1: Substituir o tema no `nuxt.config.ts`**

Troque o topo e o bloco `primevue`/`app` de `frontend/nuxt.config.ts`. Imports no topo:

```ts
import Aura from '@primeuix/themes/aura'
import { definePreset } from '@primeuix/themes'

// Marca "loot": primary verde do Aura → escala ouro. contrastColor escuro
// garante AA em botão/preço dourado (texto escuro sobre ouro).
const LootGold = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#FFFBEA',
      100: '#FFF3C4',
      200: '#FCE588',
      300: '#FADB5F',
      400: '#F7C948',
      500: '#F5C542',
      600: '#DEA818',
      700: '#B0850F',
      800: '#8A680C',
      900: '#5C4509',
      950: '#3A2B05',
    },
    colorScheme: {
      dark: {
        primary: {
          color: '{primary.500}',
          contrastColor: '#0B0E14',
          hoverColor: '{primary.400}',
          activeColor: '{primary.600}',
        },
      },
    },
  },
})
```

In `app.head`, add `htmlAttrs` (keep the existing `link` array for fonts):

```ts
  app: {
    head: {
      htmlAttrs: { class: 'app-dark' }, // dark forçado no app inteiro
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: 'anonymous' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&display=swap',
        },
      ],
    },
  },
```

In `primevue.options.theme`, swap preset + selector:

```ts
  primevue: {
    options: {
      ripple: true,
      theme: {
        preset: LootGold,
        options: { darkModeSelector: '.app-dark', cssLayer: false },
      },
    },
  },
```

- [ ] **Step 2: Adicionar tokens de raridade/superfície no `main.css`**

Replace the `:root` block of `frontend/app/assets/css/main.css` with:

```css
/* Tokens da marca (loot/gaming) + tipografia display. */
:root {
  --font-display: 'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif;
  /* Superfícies obsidiana (nunca #000). */
  --bg: #0b0e14;
  --surf: #141a24;
  --ink: #0a100d;
  --ink-soft: #121b16;
  /* Ouro + raridade (loot). */
  --gold: #f5c542;
  --rare: #3b82f6;   /* azul · item comum */
  --epic: #a855f7;   /* roxo · épico */
  --legend: #fb923c; /* laranja · lendário */
}

/* Fundo obsidiana global (dark forçado). */
body {
  background: var(--bg);
}
```

Keep the existing `h1,h2,h3,.display`, `html { scroll-behavior }` and `prefers-reduced-motion` blocks below it unchanged.

- [ ] **Step 3: Verify the build compiles**

Run: `cd frontend && npm run build`
Expected: build sucesso, sem erro de tipo no `definePreset`.

- [ ] **Step 4: Manual check (dev)**

Run: `cd frontend && npm run dev` → abrir `http://localhost:3000`.
Expected: app inteiro dark; header/botões/preços em ouro; nenhum flash claro.

- [ ] **Step 5: Commit**

```bash
git add frontend/nuxt.config.ts frontend/app/assets/css/main.css
SKIP_FINISH_CHECK=1 git commit -m "feat(theme): rebrand ouro + dark forçado + tokens de raridade"
```

### Task A2: Auditoria de contraste (cores claras hardcoded)

**Files:**
- Modify: `frontend/app/components/HomeHowItWorks.vue:89` (`.num` background)
- (Auditoria) demais componentes/páginas

- [ ] **Step 1: Corrigir o `.num` do HomeHowItWorks**

Em `frontend/app/components/HomeHowItWorks.vue`, o número usa o fundo da página pra "cortar" o trilho conector. Trocar o fallback claro pelo bg obsidiana:

```css
.num {
  position: relative;
  display: inline-block;
  margin-bottom: 0.85rem;
  padding-right: 0.6rem;
  font-family: var(--font-display);
  font-size: clamp(2.4rem, 5vw, 3.4rem);
  font-weight: 800;
  line-height: 1;
  color: transparent;
  -webkit-text-stroke: 1.5px var(--gold);
  /* Fundo da página por trás do número, pra "cortar" o trilho (dark). */
  background: var(--bg);
}
```

(Note também `-webkit-text-stroke` mudou de `var(--p-primary-color)` para `var(--gold)` — já é o mesmo ouro agora, mas explícito.)

- [ ] **Step 2: Auditar páginas e componentes com CSS custom**

Rodar busca por cores claras hardcoded que não adaptam ao dark:

```bash
cd frontend && grep -rnE "#fff|#ffffff|background:\s*white|color:\s*white|#f[0-9a-f]{2}|rgb\(255" app/ --include="*.vue"
```

Para cada acerto fora de hero/stats (que já são dark de propósito), confirmar visualmente em `npm run dev` nas páginas `perfil`, `wallet`, `orders`, `login`, `ranking`. Trocar cor clara fixa por token Aura (`var(--p-text-color)`, `var(--p-content-background)`) ou token de marca (`var(--surf)`, `var(--bg)`). Componentes que só usam tokens Aura já adaptam — não mexer.

- [ ] **Step 3: Verify build + manual sweep**

Run: `cd frontend && npm run build` (expected: sucesso).
Run: `cd frontend && npm run dev`, abrir cada página acima.
Expected: nenhum texto ilegível / bloco branco; contraste AA.

- [ ] **Step 4: Commit**

```bash
git add frontend/app
SKIP_FINISH_CHECK=1 git commit -m "fix(theme): auditoria de contraste no dark global"
```

---

# Fase B — Home: seções repaginadas + efeitos

### Task B1: Tipo + util de raridade + componente `HomeActivity`

**Files:**
- Modify: `frontend/app/types/index.ts`
- Create: `frontend/app/utils/rarity.ts`
- Create: `frontend/app/components/HomeActivity.vue`

- [ ] **Step 1: Adicionar os tipos do feed**

Append ao final de `frontend/app/types/index.ts`:

```ts
export interface ActivityItem {
  type: ListingType
  game: string
  title: string
  amount_cents: number
  completed_at: string
  seller_name: string
}

export interface ActivityResponse {
  data: ActivityItem[]
  in_escrow_count: number
}
```

- [ ] **Step 2: Criar o util de raridade**

Create `frontend/app/utils/rarity.ts` (auto-import, como `formatCents`):

```ts
import type { Listing } from '~/types'

export type Rarity = 'gold' | 'rare' | 'epic' | 'legend'

/**
 * Metáfora loot: boost manda na raridade; sem boost, o tipo do anúncio decide.
 * basic→rare, intermediate→epic, advanced→legend; gold→ouro, item→rare.
 */
export function rarityFor(listing: Listing): Rarity {
  if (listing.boost) {
    if (listing.boost.tier === 'advanced') return 'legend'
    if (listing.boost.tier === 'intermediate') return 'epic'
    return 'rare'
  }
  return listing.type === 'gold' ? 'gold' : 'rare'
}

export const RARITY_VAR: Record<Rarity, string> = {
  gold: 'var(--gold)',
  rare: 'var(--rare)',
  epic: 'var(--epic)',
  legend: 'var(--legend)',
}
```

- [ ] **Step 3: Criar `HomeActivity.vue`**

Create `frontend/app/components/HomeActivity.vue`:

```vue
<script setup lang="ts">
import type { ActivityResponse } from '~/types'

const api = useApi()

// Client-side (server:false) — componente não deve dar top-level await em
// useAsyncData (vira Suspense). default evita null no primeiro render.
const { data, refresh } = await useAsyncData<ActivityResponse>(
  'activity',
  () => api.get<ActivityResponse>('/activity'),
  { server: false, default: () => ({ data: [], in_escrow_count: 0 }) },
)

const sales = computed(() => data.value?.data ?? [])
const inEscrow = computed(() => data.value?.in_escrow_count ?? 0)

// Auto-refresh ~18s (mesmo padrão de polling do DepositDialog: interval
// limpo em onUnmounted, guardado por import.meta.client).
let timer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  if (import.meta.client) {
    timer = setInterval(() => refresh(), 18_000)
  }
})
onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <!-- Só renderiza quando há vendas concluídas. -->
  <section v-if="sales.length" class="activity">
    <div class="escrow">
      <span class="pulse" />
      <strong>{{ inEscrow }}</strong> em escrow agora
    </div>

    <!-- Ticker rolando — PrimeVue não tem primitivo de ticker; CSS custom. -->
    <div class="ticker" aria-label="Vendas recentes">
      <ul class="track">
        <li v-for="(sale, i) in sales" :key="i" class="item">
          <i class="pi pi-bolt" :class="`r-${sale.type === 'gold' ? 'gold' : 'rare'}`" />
          <span class="who">{{ sale.seller_name }}</span> vendeu
          <span class="what">{{ sale.title }}</span>
          <span class="game">({{ sale.game }})</span>
          <span class="price">{{ formatCents(sale.amount_cents) }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.activity {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  margin-bottom: 3rem;
  padding: 0.85rem 1.1rem;
  border-radius: 0.9rem;
  background: var(--surf);
  border: 1px solid color-mix(in srgb, var(--gold) 18%, transparent);
  overflow: hidden;
}
.escrow {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  flex-shrink: 0;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}
.escrow strong {
  color: var(--gold);
}
.pulse {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  background: var(--legend);
  box-shadow: 0 0 0 0 color-mix(in srgb, var(--legend) 70%, transparent);
}
.ticker {
  position: relative;
  flex: 1;
  overflow: hidden;
  mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
}
.track {
  display: flex;
  gap: 2.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
  white-space: nowrap;
}
.item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}
.who { color: var(--p-text-color); font-weight: 600; }
.what { color: var(--p-text-color); }
.game { opacity: 0.7; }
.price { color: var(--gold); font-weight: 700; }
.r-gold { color: var(--gold); }
.r-rare { color: var(--rare); }

@media (prefers-reduced-motion: no-preference) {
  .track {
    animation: ticker 32s linear infinite;
  }
  .pulse {
    animation: ping 1.8s ease-out infinite;
  }
}
@keyframes ticker {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}
@keyframes ping {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--legend) 70%, transparent); }
  70%, 100% { box-shadow: 0 0 0 0.6rem transparent; }
}

@media (max-width: 700px) {
  .escrow { display: none; }
}
</style>
```

> **Nota de motion:** o `@keyframes ticker` usa `translateX(-50%)`; pra rolar sem “salto” o ideal é duplicar a lista. Se quiser loop perfeito, renderize `[...sales, ...sales]` no `v-for` e mantenha `-50%`. Para MVP, uma passada já é aceitável.

- [ ] **Step 4: Verify build**

Run: `cd frontend && npm run build`
Expected: sucesso (tipos `ActivityResponse`/`rarityFor` resolvidos).

- [ ] **Step 5: Commit**

```bash
git add frontend/app/types/index.ts frontend/app/utils/rarity.ts frontend/app/components/HomeActivity.vue
SKIP_FINISH_CHECK=1 git commit -m "feat(home): componente HomeActivity (feed ao vivo) + util de raridade"
```

### Task B2: `HomeHero` repaginado + efeitos máximo

**Files:**
- Modify: `frontend/app/components/HomeHero.vue`

- [ ] **Step 1: Adicionar spotlight no mouse (script)**

Em `frontend/app/components/HomeHero.vue`, dentro do `<script setup>`, adicionar a ref do elemento e o handler com throttle rAF, **sob `prefers-reduced-motion`**:

```ts
const heroEl = ref<HTMLElement | null>(null)
let raf = 0

const onMove = (e: PointerEvent) => {
  if (raf) return
  raf = requestAnimationFrame(() => {
    raf = 0
    const el = heroEl.value
    if (!el) return
    const rect = el.getBoundingClientRect()
    el.style.setProperty('--mx', `${e.clientX - rect.left}px`)
    el.style.setProperty('--my', `${e.clientY - rect.top}px`)
  })
}

onMounted(() => {
  if (import.meta.client
    && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
    heroEl.value?.addEventListener('pointermove', onMove)
  }
})
onUnmounted(() => {
  heroEl.value?.removeEventListener('pointermove', onMove)
  if (raf) cancelAnimationFrame(raf)
})
```

- [ ] **Step 2: Ligar a ref na `<section>`**

Trocar `<section class="hero">` por:

```html
<section ref="heroEl" class="hero">
```

- [ ] **Step 3: Adicionar a camada de spotlight + aurora no CSS**

No `<style scoped>` do hero, ajustar `.hero` e o pseudo-elemento. Manter o gradiente base; somar a camada de spotlight (segue `--mx/--my`, default centralizado) e a aurora animada:

```css
.hero {
  position: relative;
  border-radius: 1.5rem;
  margin-bottom: 3.5rem;
  padding: clamp(2.5rem, 5vw, 4.5rem) clamp(1.5rem, 4vw, 3.5rem);
  overflow: hidden;
  color: #eaf2ee;
  /* default do spotlight no canto sup. direito quando não há mouse. */
  --mx: 78%;
  --my: 12%;
  background:
    radial-gradient(420px 420px at var(--mx) var(--my), color-mix(in srgb, var(--gold) 26%, transparent), transparent 60%),
    radial-gradient(800px 520px at 78% 8%, color-mix(in srgb, var(--p-primary-color) 30%, transparent), transparent 70%),
    radial-gradient(620px 420px at 8% 100%, color-mix(in srgb, var(--epic) 16%, transparent), transparent 70%),
    linear-gradient(160deg, var(--ink) 0%, var(--ink-soft) 60%, var(--ink) 100%);
}
/* Aurora animada por cima do gradiente (GPU; só sob no-preference). */
.hero::after {
  content: '';
  position: absolute;
  inset: -30%;
  background:
    radial-gradient(40% 40% at 30% 30%, color-mix(in srgb, var(--gold) 14%, transparent), transparent 70%),
    radial-gradient(40% 40% at 70% 60%, color-mix(in srgb, var(--epic) 12%, transparent), transparent 70%);
  pointer-events: none;
  opacity: 0.9;
}
@media (prefers-reduced-motion: no-preference) {
  .hero::after { animation: aurora 18s ease-in-out infinite alternate; }
}
@keyframes aurora {
  from { transform: translate3d(-4%, -2%, 0) rotate(0deg); }
  to { transform: translate3d(4%, 3%, 0) rotate(8deg); }
}
```

(O `.hero::before` do grão SVG continua como está.)

- [ ] **Step 4: Adicionar sheen dourado nos CTAs**

Ainda no `<style scoped>`, adicionar varredura de brilho nos botões do hero:

```css
.cta :deep(.p-button) {
  position: relative;
  overflow: hidden;
}
@media (prefers-reduced-motion: no-preference) {
  .cta :deep(.p-button)::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, color-mix(in srgb, #fff 35%, transparent) 50%, transparent 70%);
    transform: translateX(-130%);
    animation: sheen 4.5s ease-in-out infinite;
    pointer-events: none;
  }
}
@keyframes sheen {
  0%, 60% { transform: translateX(-130%); }
  100% { transform: translateX(130%); }
}
```

- [ ] **Step 5: Recolorir os cards do showcase por raridade**

Nas regras `.tag-item`/`.tag-gold` e `.prod`, usar os tokens de raridade. O card "front" (boostado/lendário) ganha glow laranja, o "back" (item) glow azul:

```css
.prod--front {
  top: 7.5rem;
  left: 0;
  box-shadow: 0 30px 60px -25px rgba(0, 0, 0, 0.7),
              0 0 0 1px color-mix(in srgb, var(--legend) 45%, transparent),
              0 0 40px -8px color-mix(in srgb, var(--legend) 45%, transparent);
}
.prod--back {
  top: 0;
  right: 1rem;
  transform: rotate(-5deg);
  opacity: 0.92;
  box-shadow: 0 30px 60px -25px rgba(0, 0, 0, 0.7),
              0 0 0 1px color-mix(in srgb, var(--rare) 40%, transparent);
}
.tag-item { color: var(--rare); background: color-mix(in srgb, var(--rare) 22%, transparent); }
.boost { color: var(--ink); background: var(--legend); }
```

- [ ] **Step 6: Verify build + manual**

Run: `cd frontend && npm run build` (expected: sucesso).
Run: `cd frontend && npm run dev` → mover o mouse no hero (spotlight segue), aurora anima, CTAs com sheen.
Com `prefers-reduced-motion: reduce` (DevTools → Rendering → Emulate CSS media): tudo estático, sem listener de mouse.

- [ ] **Step 7: Commit**

```bash
git add frontend/app/components/HomeHero.vue
SKIP_FINISH_CHECK=1 git commit -m "feat(home): hero com spotlight, aurora, sheen e raridade"
```

### Task B3: `ListingCard` + `FeaturedListings` por raridade

**Files:**
- Modify: `frontend/app/components/ListingCard.vue`
- Modify: `frontend/app/components/FeaturedListings.vue`

- [ ] **Step 1: `ListingCard` — calcular raridade e expor como CSS var**

No `<script setup>` de `ListingCard.vue`, adicionar:

```ts
const rarity = computed(() => rarityFor(props.listing))
const rarityVar = computed(() => RARITY_VAR[rarity.value])
```

Na `<Card>`, aplicar a var e a classe:

```html
<Card class="listing-card" :class="`rar-${rarity}`" :style="{ '--rar': rarityVar }">
```

- [ ] **Step 2: `ListingCard` — glow + borda + tilt por raridade**

Substituir o bloco de hover/`.price` no `<style scoped>`:

```css
.listing-card {
  height: 100%;
  overflow: hidden;
  border: 1px solid color-mix(in srgb, var(--rar) 35%, transparent);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.listing-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 14px 32px -12px color-mix(in srgb, var(--rar) 55%, transparent),
              0 0 0 1px color-mix(in srgb, var(--rar) 55%, transparent);
}
.price {
  font-size: 1.25rem;
  color: var(--rar);
}
```

- [ ] **Step 3: `FeaturedListings` — virar "Loot em alta"**

Trocar o título e o ícone no template:

```html
<section v-if="featured.length" class="featured">
  <h2><i class="pi pi-bolt" /> Loot em alta</h2>
  <div class="row">
    <ListingCard
      v-for="listing in featured"
      :key="listing.id"
      :listing="listing"
      @bought="onBought"
    />
  </div>
</section>
```

E o `.featured h2 i` usa ouro:

```css
.featured h2 i { color: var(--gold); }
```

- [ ] **Step 4: Verify build + manual**

Run: `cd frontend && npm run build` (expected: sucesso).
Run: `cd frontend && npm run dev` → cards `gold` com borda/preço ouro; `item` azul; boostados roxo/laranja; hover com glow da raridade.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/components/ListingCard.vue frontend/app/components/FeaturedListings.vue
SKIP_FINISH_CHECK=1 git commit -m "feat(home): cards e 'Loot em alta' com cor de raridade"
```

### Task B4: Recolor `HomeHowItWorks` + `HomeStats`

**Files:**
- Modify: `frontend/app/components/HomeHowItWorks.vue`
- Modify: `frontend/app/components/HomeStats.vue`

- [ ] **Step 1: `HomeHowItWorks` — kicker/trilho/realce em ouro**

(O `.num` já foi para `var(--bg)` + `var(--gold)` na Task A2.) Trocar `.kicker` e o trilho `.flow::before` para ouro:

```css
.kicker { color: var(--gold); }
.flow::before {
  content: '';
  position: absolute;
  top: 1.7rem;
  left: 4%;
  right: 4%;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--gold) 18%, var(--gold) 82%, transparent);
  opacity: 0.32;
}
```

- [ ] **Step 2: `HomeStats` — realce/contador em ouro, glow roxo na banda**

Em `HomeStats.vue`, ajustar a banda e os realces:

```css
.trust {
  margin-bottom: 4rem;
  padding: clamp(2.25rem, 4vw, 3.25rem);
  border-radius: 1.25rem;
  color: #eaf2ee;
  background:
    radial-gradient(560px 320px at 88% 0%, color-mix(in srgb, var(--epic) 22%, transparent), transparent 70%),
    radial-gradient(420px 280px at 5% 100%, color-mix(in srgb, var(--gold) 12%, transparent), transparent 70%),
    linear-gradient(150deg, var(--ink) 0%, var(--ink-soft) 100%);
}
.hl {
  color: var(--gold);
  box-shadow: inset 0 -0.16em 0 color-mix(in srgb, var(--gold) 45%, transparent);
}
.meta strong { color: var(--gold); font-size: 1.15rem; }
```

- [ ] **Step 3: Verify build + manual**

Run: `cd frontend && npm run build` (expected: sucesso).
Run: `cd frontend && npm run dev` → "Como funciona" com numerais/trilho ouro; banda Stats com glow roxo + ouro.

- [ ] **Step 4: Commit**

```bash
git add frontend/app/components/HomeHowItWorks.vue frontend/app/components/HomeStats.vue
SKIP_FINISH_CHECK=1 git commit -m "feat(home): recolor 'Como funciona' e Stats (ouro + raridade)"
```

### Task B5: Reveal on scroll + nova ordem da home

**Files:**
- Create: `frontend/app/plugins/reveal.client.ts`
- Modify: `frontend/app/assets/css/main.css`
- Modify: `frontend/app/pages/index.vue`

- [ ] **Step 1: Criar a diretiva `v-reveal` (client-only)**

Create `frontend/app/plugins/reveal.client.ts` — IntersectionObserver que revela o elemento ao entrar na viewport, **só sob `prefers-reduced-motion: no-preference`**:

```ts
export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.directive('reveal', {
    mounted(el: HTMLElement) {
      if (!window.matchMedia('(prefers-reduced-motion: no-preference)').matches) return
      el.classList.add('reveal-init')
      const io = new IntersectionObserver((entries, obs) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            el.classList.add('reveal-in')
            obs.unobserve(el)
          }
        }
      }, { threshold: 0.12 })
      io.observe(el)
    },
  })
})
```

- [ ] **Step 2: Classes de reveal no `main.css`**

Append ao final de `frontend/app/assets/css/main.css`:

```css
/* Reveal on scroll (diretiva v-reveal). Só anima quando o usuário permite motion. */
@media (prefers-reduced-motion: no-preference) {
  .reveal-init {
    opacity: 0;
    transform: translateY(22px);
  }
  .reveal-in {
    opacity: 1;
    transform: none;
    transition:
      opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1),
      transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
  }
}
```

- [ ] **Step 3: Nova ordem das seções + `v-reveal`**

Substituir o template de `frontend/app/pages/index.vue`. O `HomeHero` já tem reveal próprio no load — não envolver; as demais seções entram com `v-reveal`:

```vue
<script setup lang="ts">
// Homepage: página fina — só monta as seções (regra 11).
</script>

<template>
  <div>
    <HomeHero />
    <div v-reveal><HomeActivity /></div>
    <div v-reveal><HomeHowItWorks /></div>
    <div v-reveal><FeaturedListings /></div>
    <div v-reveal><HomeStats /></div>
    <div v-reveal><ListingGrid /></div>
  </div>
</template>
```

- [ ] **Step 4: Verify build + manual (home inteira)**

Run: `cd frontend && npm run build` (expected: sucesso).
Run: `cd frontend && npm run dev` → home na ordem: Hero → Activity → Como funciona → Loot em alta → Stats → Vitrine. Ao rolar, cada seção sobe/aparece (reveal). Feed ao vivo aparece quando há vendas completed (criar uma compra+dupla confirmação no fluxo, ou seed). Auto-refresh ~18s. Com `prefers-reduced-motion: reduce`: tudo já visível, sem animação.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/plugins/reveal.client.ts frontend/app/assets/css/main.css frontend/app/pages/index.vue
SKIP_FINISH_CHECK=1 git commit -m "feat(home): reveal on scroll + nova ordem das seções"
```

---

# Fase D — Fechamento (skills, vault, finisher)

### Task D1: Atualizar skills

**Files:**
- Modify: `.claude/skills/rmt-frontend/SKILL.md`
- Modify: `.claude/skills/rmt-context/SKILL.md` (rota/endpoint novo)
- Modify: `.claude/skills/rmt-architecture/SKILL.md` (ActivityFeedService + cache)
- Modify: `.claude/skills/rmt-tests/SKILL.md` (se novo padrão de teste surgir)

- [ ] **Step 1: Documentar o que mudou**

- `rmt-frontend`: nova paleta (ouro + raridade), dark forçado (`.app-dark`), `HomeActivity`, `utils/rarity.ts`, nova ordem da home, efeitos (spotlight/aurora/sheen) sob `prefers-reduced-motion`.
- `rmt-context`: rota pública `GET /api/activity` + `ActivityController`.
- `rmt-architecture`: `ActivityFeedService` (cache Redis 15s, Octane-safe).
- `rmt-tests`: padrão de teste de feed público/anti-vazamento de comprador (se considerar novo).

- [ ] **Step 2: Commit**

```bash
git add .claude/skills
SKIP_FINISH_CHECK=1 git commit -m "docs(skills): registra activity feed + rebrand loot/dark"
```

### Task D2: Notas no vault

**Files:**
- Create: `.vault/Features/2026-06-06 homepage-redesign-loot.md` (de `Templates/Feature.md`)
- Create: `.vault/Decisoes/ADR — rebrand ouro e dark global.md` (de `Templates/ADR.md`)
- Modify: `.vault/MOC.md` (links)

- [ ] **Step 1: Feature note** — preencher "Decisões técnicas" (dark forçado, ouro como primary, feed anonimizado/cacheado) e "Como evitar no futuro" (auditoria de contraste ao forçar dark).
- [ ] **Step 2: ADR** — decisão: trocar primary verde→ouro e forçar dark no app inteiro; contexto, alternativas (só home / system dark), consequências (auditoria de todas as telas).
- [ ] **Step 3: Link no `MOC.md`.**
- [ ] **Step 4: Commit**

```bash
git add .vault
SKIP_FINISH_CHECK=1 git commit -m "docs(vault): feature note + ADR do redesign loot/dark"
```

### Task D3: rmt-feature-finisher + suíte completa

- [ ] **Step 1: Rodar a suíte backend completa**

Run: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan test`
Expected: tudo verde (sem regressão).

- [ ] **Step 2: Build de produção do frontend**

Run: `cd frontend && npm run build`
Expected: sucesso.

- [ ] **Step 3: Invocar o subagent obrigatório (regra 14)**

`Agent(subagent_type="rmt-feature-finisher", ...)` — checklist de arquitetura/segurança/frontend/vault/skills. Corrigir todos os bloqueadores **antes** do commit final.

- [ ] **Step 4: Commit final (sem SKIP, passando pelo hook)**

```bash
git add -A
git commit -m "feat: redesign da homepage loot/gaming (dark + ouro + raridade) + feed ao vivo"
```

---

## Notas de execução

- **Efeitos "máximo":** spotlight (rAF), aurora (CSS), sheen (CSS), glow/tilt (CSS), ticker (CSS) — todos sob `@media (prefers-reduced-motion: no-preference)`. Nada de lib de partícula. Se algo pesar em mobile, o showcase do hero já some < 860px e o ticker `.escrow` some < 700px.
- **CSS de redesign é ponto de partida:** os blocos acima compilam e funcionam; afinação visual (hex exatos, intensidade dos glows) é esperada em `npm run dev`. Não trocar a estrutura sem motivo.
- **Privacidade do feed:** o teste `test_activity_feed_never_exposes_buyer_data` trava o shape; não adicionar campos de comprador.
- **Pipeline morpheus (opcional):** Fase C → Neo/Eliot/Oracle; Fases A/B → Trinity. Se for via morpheus, manter os mesmos arquivos/contratos deste plano.
```
