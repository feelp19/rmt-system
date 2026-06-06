<script setup lang="ts">
import type { Order, Paginated } from '~/types'

definePageMeta({ middleware: 'auth' })

const api = useApi()

const { data, pending, refresh } = await useAsyncData(
  'orders',
  () => api.get<Paginated<Order>>('/orders'),
  { server: false },
)

const orders = computed(() => data.value?.data ?? [])
</script>

<template>
  <section>
    <h1>Meus pedidos</h1>

    <div v-if="pending" class="state"><ProgressSpinner /></div>
    <Message v-else-if="!orders.length" severity="info" :closable="false">
      Você ainda não tem pedidos. Compre algo na vitrine para começar.
    </Message>
    <div v-else class="list">
      <OrderCard v-for="order in orders" :key="order.id" :order="order" @updated="refresh" />
    </div>
  </section>
</template>

<style scoped>
.state {
  display: flex;
  justify-content: center;
  padding: 3rem;
}
.list {
  display: grid;
  gap: 1rem;
  max-width: 640px;
}
</style>
