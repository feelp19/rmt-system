<script setup lang="ts">
import type { Listing } from '~/types'

const api = useApi()

const { data } = useAsyncData(
  'featured',
  () => api.get<{ data: Listing[] }>('/listings/featured'),
  { server: false },
)

const featured = computed(() => data.value?.data ?? [])

const onBought = async () => {
  await refreshNuxtData(['listings', 'featured'])
  await navigateTo('/orders')
}
</script>

<template>
  <!-- Só renderiza quando há anúncios turbinados. -->
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
</template>

<style scoped>
.featured {
  margin-bottom: 2.5rem;
}
.featured h2 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 1.25rem;
}
.featured h2 i {
  color: var(--gold);
}
/* Faixa horizontal rolável — PrimeVue não tem primitivo de carrossel simples de cards. */
.row {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}
</style>
