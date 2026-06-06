<script setup lang="ts">
import type { Listing, ProfileStats } from '~/types'

definePageMeta({ middleware: 'auth' })

const api = useApi()

const { data: profile } = await useAsyncData(
  'profile',
  () => api.get<{ stats: ProfileStats }>('/me/profile'),
  { server: false },
)
const { data: listingsData, refresh } = await useAsyncData(
  'my-listings',
  () => api.get<{ data: Listing[] }>('/me/listings'),
  { server: false },
)

const stats = computed(() => profile.value?.stats ?? null)
const myListings = computed(() => listingsData.value?.data ?? [])
</script>

<template>
  <section class="profile">
    <ProfileSummary :stats="stats" />

    <h2>Meus anúncios</h2>
    <div v-if="myListings.length" class="grid">
      <ListingCard v-for="listing in myListings" :key="listing.id" :listing="listing" @bought="refresh" />
    </div>
    <Message v-else severity="info" :closable="false">Você ainda não tem anúncios. Crie um na vitrine.</Message>
  </section>
</template>

<style scoped>
.profile h2 {
  margin: 2.5rem 0 1.25rem;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}
</style>
