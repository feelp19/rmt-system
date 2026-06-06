<script setup lang="ts">
import type { Listing, Paginated } from '~/types'

// Lê o cache da vitrine (mesma chave do ListingGrid) — sem fetch extra.
const { data } = useNuxtData<Paginated<Listing>>('listings')
const activeCount = computed(() => data.value?.total ?? null)

const games = [
  'World of Warcraft',
  'Tibia',
  'Path of Exile',
  'RuneScape',
  'Valorant',
  'CS2',
  'Diablo IV',
  'League of Legends',
]
</script>

<template>
  <section class="trust">
    <div class="inner">
      <h2 class="statement">Compre sem medo de <span class="hl">calote</span>.</h2>
      <p class="sub">
        O dinheiro fica retido no cofre e só é liberado quando você confirma o recebimento.
      </p>
      <p class="meta">
        <strong>{{ activeCount ?? '—' }}</strong> anúncios ativos agora<span class="sep">·</span>taxa única de 5%<span class="sep">·</span>dupla confirmação
      </p>
    </div>

    <ul class="games" aria-label="Jogos suportados">
      <li v-for="game in games" :key="game">{{ game }}</li>
    </ul>
  </section>
</template>

<style scoped>
/* Banda de confiança em tinta escura — cria contraste/ritmo entre as seções claras
   (regra 12: arte por seção, sem primitivo PrimeVue equivalente). */
.trust {
  margin-bottom: 4rem;
  padding: clamp(2.25rem, 4vw, 3.25rem);
  border-radius: 1.25rem;
  color: #eaf2ee;
  background:
    radial-gradient(560px 320px at 88% 0%, color-mix(in srgb, var(--p-primary-color) 26%, transparent), transparent 70%),
    linear-gradient(150deg, var(--ink) 0%, var(--ink-soft) 100%);
}
.inner {
  max-width: 40ch;
}
.statement {
  margin: 0 0 0.75rem;
  font-size: clamp(1.9rem, 4vw, 3rem);
  font-weight: 800;
  line-height: 1.02;
  color: #f6faf8;
}
.hl {
  color: var(--p-primary-color);
  box-shadow: inset 0 -0.16em 0 color-mix(in srgb, var(--gold) 55%, transparent);
}
.sub {
  margin: 0 0 1.5rem;
  font-size: 1.05rem;
  line-height: 1.55;
  color: #b8c7be;
}
.meta {
  margin: 0;
  font-size: 0.95rem;
  color: #9fb3a8;
}
.meta strong {
  color: var(--p-primary-color);
  font-size: 1.15rem;
}
.sep {
  margin: 0 0.6rem;
  opacity: 0.45;
}

.games {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin: 2rem 0 0;
  padding: 0;
  list-style: none;
}
.games li {
  font-size: 0.82rem;
  font-weight: 600;
  color: #cfe0d6;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, #eaf2ee 14%, transparent);
}
</style>
