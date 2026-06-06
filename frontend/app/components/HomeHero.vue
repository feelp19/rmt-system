<script setup lang="ts">
const { isAuthenticated } = useAuth()
const showForm = useState('show_listing_form', () => false)

const announce = () => {
  if (isAuthenticated.value) {
    showForm.value = true
  } else {
    navigateTo('/login')
  }
}

const browse = () => {
  if (import.meta.client) {
    document.getElementById('vitrine')?.scrollIntoView({ behavior: 'smooth' })
  }
}

// Change 1 — Spotlight
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
</script>

<template>
  <!-- Change 2 — bind ref -->
  <section ref="heroEl" class="hero">
    <div class="hero-grid">
      <div class="copy">
        <p class="eyebrow reveal d1"><span class="dot" /> Marketplace com escrow</p>
        <h1 class="title reveal d2">
          Gold e itens de
          <span class="hl">qualquer jogo</span>,
          sem calote.
        </h1>
        <p class="lead reveal d3">
          O valor fica retido no cofre e só vai pro vendedor quando você confirma que recebeu.
          Taxa única de 5% por transação.
        </p>
        <div class="cta reveal d4">
          <Button label="Ver vitrine" icon="pi pi-arrow-down" size="large" @click="browse" />
          <Button label="Anunciar grátis" icon="pi pi-plus" size="large" severity="contrast" outlined @click="announce" />
        </div>
        <ul class="trust reveal d5">
          <li><i class="pi pi-shield" /> Escrow protegido</li>
          <li><i class="pi pi-bolt" /> Liberação rápida</li>
          <li><i class="pi pi-check-circle" /> Dupla confirmação</li>
        </ul>
      </div>

      <!-- Vitrine do próprio produto: cards de anúncio flutuando (é a "imagem" do produto). -->
      <div class="showcase reveal d3" aria-hidden="true">
        <article class="prod prod--back float-slow">
          <header><span class="game">Path of Exile</span><span class="tag-item">Item</span></header>
          <strong class="name">Mageblood Belt</strong>
          <span class="price">R$ 300,00</span>
        </article>

        <article class="prod prod--front float">
          <span class="boost"><i class="pi pi-bolt" /> Turbinado</span>
          <header><span class="game">WoW Retail</span><span class="tag-gold">Gold</span></header>
          <strong class="name">500k Gold</strong>
          <div class="row">
            <span class="price">R$ 120,00</span>
            <span class="buy"><i class="pi pi-shopping-cart" /> Comprar</span>
          </div>
        </article>

        <span class="escrow-pill float-fast"><i class="pi pi-lock" /> em escrow</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Hero gaming: painel contido arredondado (sem full-bleed, pra não cortar),
   tinta verde-escura + brilho radial + grão. Type display em escala grande.
   PrimeVue não tem primitivo de hero (regra 12); accent via --p-primary-color. */

/* Change 3 — .hero com spotlight layer e defaults de --mx/--my */
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

/* Grão sutil por cima do gradiente. */
.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
  opacity: 0.04;
  pointer-events: none;
}

/* Change 4 — Aurora animada por cima do gradiente (GPU; anima só sob no-preference). */
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

/* Content must stack above ::after — position:relative already ensures this;
   adding z-index: 1 to be explicit. */
.hero-grid {
  position: relative;
  z-index: 1;
  max-width: 1180px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.15fr 0.85fr;
  gap: clamp(2rem, 5vw, 4rem);
  align-items: center;
}

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 1.25rem;
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #9fb3a8;
}
.dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: var(--p-primary-color);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--p-primary-color) 22%, transparent);
}

.title {
  margin: 0 0 1.25rem;
  font-size: clamp(2.6rem, 6.5vw, 5.2rem);
  font-weight: 800;
  line-height: 0.98;
  color: #f6faf8;
}
.hl {
  color: var(--p-primary-color);
  box-shadow: inset 0 -0.18em 0 color-mix(in srgb, var(--gold) 55%, transparent);
}

.lead {
  margin: 0 0 2rem;
  max-width: 46ch;
  font-size: clamp(1.05rem, 1.6vw, 1.25rem);
  line-height: 1.6;
  color: #b8c7be;
}

.cta {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

/* Change 5 — Sheen nos CTAs */
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

.trust {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 0.92rem;
  color: #9fb3a8;
}
.trust li {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.trust i {
  color: var(--p-primary-color);
}

/* ── Showcase de produto ───────────────────────────────────────── */
.showcase {
  position: relative;
  min-height: 360px;
}
.prod {
  position: absolute;
  width: 17rem;
  padding: 1.25rem 1.35rem;
  border-radius: 1rem;
  background: linear-gradient(180deg, #18231d, #0e1612);
  border: 1px solid #243029;
  box-shadow: 0 30px 60px -25px rgba(0, 0, 0, 0.7);
}
.prod header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.65rem;
}
.game {
  font-size: 0.8rem;
  color: #93a89c;
}
.tag-item,
.tag-gold {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}

/* Change 6 — tag-item recolorido para raridade rare (azul) */
.tag-item { color: var(--rare); background: color-mix(in srgb, var(--rare) 22%, transparent); }
.tag-gold {
  color: var(--gold);
  background: color-mix(in srgb, var(--gold) 18%, transparent);
}
.prod .name {
  display: block;
  font-family: var(--font-display);
  font-size: 1.35rem;
  color: #f3f8f5;
}
.prod .price {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--p-primary-color);
}

/* Change 6 — prod--back: rare (blue outline), prod--front: legendary (orange glow) */
.prod--back {
  top: 0;
  right: 1rem;
  transform: rotate(-5deg);
  opacity: 0.92;
  box-shadow: 0 30px 60px -25px rgba(0, 0, 0, 0.7),
              0 0 0 1px color-mix(in srgb, var(--rare) 40%, transparent);
}
.prod--front {
  top: 7.5rem;
  left: 0;
  box-shadow: 0 30px 60px -25px rgba(0, 0, 0, 0.7),
              0 0 0 1px color-mix(in srgb, var(--legend) 45%, transparent),
              0 0 40px -8px color-mix(in srgb, var(--legend) 45%, transparent);
}
.prod--front .row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.75rem;
}
.buy {
  font-size: 0.82rem;
  font-weight: 700;
  /* Cor de contraste do tema (garante WCAG AA sobre o verde primário). */
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
}

/* Change 6 — boost recolorido para legendary (laranja) */
.boost {
  position: absolute;
  top: -0.7rem;
  left: 1rem;
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--ink);
  background: var(--legend);
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
}
.escrow-pill {
  position: absolute;
  bottom: 1rem;
  right: 2.5rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #eaf2ee;
  background: color-mix(in srgb, var(--p-primary-color) 16%, #0e1612);
  border: 1px solid color-mix(in srgb, var(--p-primary-color) 40%, transparent);
  padding: 0.4rem 0.8rem;
  border-radius: 999px;
}

/* ── Motion ────────────────────────────────────────────────────── */
@media (prefers-reduced-motion: no-preference) {
  .reveal {
    opacity: 0;
    transform: translateY(18px);
    animation: reveal 0.75s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  }
  .d1 { animation-delay: 0.04s; }
  .d2 { animation-delay: 0.12s; }
  .d3 { animation-delay: 0.22s; }
  .d4 { animation-delay: 0.32s; }
  .d5 { animation-delay: 0.42s; }
  .float { animation: floaty 6s ease-in-out infinite; }
  .float-slow { animation: floaty 8s ease-in-out infinite; }
  .float-fast { animation: floaty 4.5s ease-in-out infinite; }
}
@keyframes reveal {
  to {
    opacity: 1;
    transform: none;
  }
}
@keyframes floaty {
  0%, 100% { transform: translateY(0) rotate(var(--r, 0deg)); }
  50% { transform: translateY(-12px) rotate(var(--r, 0deg)); }
}
/* --r para o float do prod--back (usado pelo floaty keyframe) */
.prod--back { --r: -5deg; }

/* ── Responsivo ────────────────────────────────────────────────── */
@media (max-width: 860px) {
  .hero-grid {
    grid-template-columns: 1fr;
  }
  .showcase {
    display: none; /* foco no texto no mobile; o showcase é decorativo */
  }
}
</style>
