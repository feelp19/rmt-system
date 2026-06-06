<script setup lang="ts">
import type { Listing } from '~/types'

const props = defineProps<{ listing: Listing }>()
const emit = defineEmits<{ bought: [] }>()

const { isAuthenticated, user } = useAuth()
const { refresh: refreshWallet } = useWallet()
const api = useApi()
const toast = useToast()
const buying = ref(false)
const showBoost = ref(false)

const isOwn = computed(() => user.value?.id === props.listing.seller?.id)
const typeLabel = computed(() => (props.listing.type === 'gold' ? 'Gold' : 'Item'))

const buy = async () => {
  buying.value = true
  try {
    await api.post('/orders', { listing_id: props.listing.id })
    await refreshWallet()
    toast.add({
      severity: 'success',
      summary: 'Compra criada',
      detail: 'Valor retido em escrow. Confirme o recebimento quando receber o item/gold.',
      life: 5000,
    })
    emit('bought')
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Não foi possível comprar.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    buying.value = false
  }
}
</script>

<template>
  <Card class="listing-card">
    <template #title>
      <div class="title-row">
        <span>{{ listing.title }}</span>
        <Tag :value="typeLabel" :severity="listing.type === 'gold' ? 'warn' : 'info'" />
      </div>
    </template>
    <template #subtitle>{{ listing.game }} · {{ listing.quantity }} un · {{ listing.seller?.name }}</template>
    <template #content>
      <Tag
        v-if="listing.boost"
        class="boost-badge"
        :value="`Turbinado · ${listing.boost.tier_label}`"
        icon="pi pi-bolt"
        severity="warn"
      />
      <p v-if="listing.description" class="desc">{{ listing.description }}</p>
      <strong class="price">{{ formatCents(listing.price_cents) }}</strong>
    </template>
    <template #footer>
      <Button
        v-if="isAuthenticated && !isOwn"
        label="Comprar"
        icon="pi pi-shopping-cart"
        :loading="buying"
        fluid
        @click="buy"
      />
      <div v-else-if="isOwn" class="own-actions">
        <Tag value="Seu anúncio" severity="secondary" />
        <Button
          v-if="!listing.boost"
          label="Turbinar"
          icon="pi pi-bolt"
          size="small"
          outlined
          @click="showBoost = true"
        />
      </div>
      <Button v-else label="Entre para comprar" icon="pi pi-sign-in" text @click="navigateTo('/login')" />
    </template>
  </Card>

  <BoostDialog v-if="isOwn" v-model:visible="showBoost" :listing="listing" />
</template>

<style scoped>
/* Hover "gaming": leve elevação + glow no accent do tema (regra 12 — efeito não coberto pelo Card). */
.listing-card {
  height: 100%;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.listing-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 30px -12px color-mix(in srgb, var(--p-primary-color) 55%, transparent);
}
.title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.boost-badge {
  margin-bottom: 0.6rem;
}
.own-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.desc {
  margin: 0 0 0.75rem;
  opacity: 0.85;
}
.price {
  font-size: 1.25rem;
  color: var(--p-primary-color);
}
</style>
