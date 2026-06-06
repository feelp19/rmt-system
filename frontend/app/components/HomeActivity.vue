<script setup lang="ts">
import type { ActivityResponse } from '~/types'

const api = useApi()

// Client-side (server:false), SEM top-level await — componente filho com await
// vira async component/Suspense (pitfall documentado no vault). default evita
// null no primeiro render; o feed popula reativo após o mount.
const { data, refresh } = useAsyncData<ActivityResponse>(
  'activity',
  () => api.get<ActivityResponse>('/activity'),
  { server: false, default: () => ({ data: [], in_escrow_count: 0 }) },
)

const sales = computed(() => data.value?.data ?? [])
const inEscrow = computed(() => data.value?.in_escrow_count ?? 0)

// Auto-refresh ~18s (padrão de polling do DepositDialog: interval limpo em
// onUnmounted, guardado por import.meta.client).
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
          <i class="pi pi-bolt" :class="sale.type === 'gold' ? 'r-gold' : 'r-rare'" />
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
