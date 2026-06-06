<script setup lang="ts">
import type { Listing, Paginated } from '~/types'

const api = useApi()
const { isAuthenticated } = useAuth()
// Estado compartilhado com o HomeHero: o botão "Anunciar" do hero abre este dialog.
const showForm = useState('show_listing_form', () => false)

// Sem top-level await (componente filho): `pending` controla o loading.
const { data, pending, refresh } = useAsyncData(
  'listings',
  () => api.get<Paginated<Listing>>('/listings'),
  { server: false },
)

const listings = computed(() => data.value?.data ?? [])

const onBought = async () => {
  await refresh()
  await navigateTo('/orders')
}

const openForm = () => {
  if (isAuthenticated.value) {
    showForm.value = true
  } else {
    navigateTo('/login')
  }
}
</script>

<template>
  <section id="vitrine" class="vitrine">
    <div class="head">
      <h2>Anúncios disponíveis</h2>
      <Button v-if="isAuthenticated" label="Anunciar" icon="pi pi-plus" @click="openForm" />
    </div>

    <div v-if="pending" class="state"><ProgressSpinner /></div>
    <Message v-else-if="!listings.length" severity="info" :closable="false">
      Nenhum anúncio ativo ainda. {{ isAuthenticated ? 'Crie o primeiro!' : 'Entre e crie o primeiro!' }}
    </Message>
    <div v-else class="grid">
      <ListingCard v-for="listing in listings" :key="listing.id" :listing="listing" @bought="onBought" />
    </div>

    <ListingFormDialog v-model:visible="showForm" @created="refresh" />
  </section>
</template>

<style scoped>
.vitrine {
  scroll-margin-top: 1rem;
}
.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}
.head h2 {
  margin: 0;
}
.state {
  display: flex;
  justify-content: center;
  padding: 3rem;
}
/* Grid responsivo de cards — PrimeVue não tem primitivo de grid. */
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}
</style>
