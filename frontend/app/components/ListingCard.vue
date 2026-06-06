<script setup lang="ts">
import type { Listing } from '~/types'

const props = defineProps<{ listing: Listing }>()
const emit = defineEmits<{ bought: [] }>()

const { isAuthenticated, user } = useAuth()
const { refresh: refreshWallet } = useWallet()
const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const buying = ref(false)
const showBoost = ref(false)
const showEdit = ref(false)

const isOwn = computed(() => user.value?.id === props.listing.seller?.id)
const typeLabel = computed(() => (props.listing.type === 'gold' ? 'Gold' : 'Item'))
const sellerInitial = computed(() => (props.listing.seller?.name ?? '?').charAt(0).toUpperCase())

const rarity = computed(() => rarityFor(props.listing))
const rarityVar = computed(() => RARITY_VAR[rarity.value])

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

const removeListing = () => {
  confirm.require({
    header: 'Excluir anúncio',
    message: 'Tem certeza? O anúncio sai da vitrine.',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Excluir',
    rejectLabel: 'Cancelar',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await api.del(`/listings/${props.listing.id}`)
        await refreshNuxtData(['listings', 'featured'])
        toast.add({ severity: 'success', summary: 'Anúncio excluído', life: 3000 })
      } catch (e: unknown) {
        const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Falha ao excluir.'
        toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
      }
    },
  })
}
</script>

<template>
  <Card class="listing-card" :class="`rar-${rarity}`" :style="{ '--rar': rarityVar }">
    <template #header>
      <div class="thumb">
        <img v-if="listing.photo_url" :src="listing.photo_url" :alt="listing.title" loading="lazy" />
        <!-- Placeholder p/ anúncios antigos sem foto. -->
        <div v-else class="thumb-ph"><i class="pi pi-image" /></div>
        <Tag
          v-if="listing.boost"
          class="thumb-boost"
          :value="listing.boost.tier_label"
          icon="pi pi-bolt"
          severity="warn"
        />
      </div>
    </template>
    <template #title>
      <div class="title-row">
        <span>{{ listing.title }}</span>
        <Tag :value="typeLabel" :severity="listing.type === 'gold' ? 'warn' : 'info'" />
      </div>
    </template>
    <template #subtitle>
      <span class="seller">
        <Avatar :image="listing.seller?.avatar_url ?? undefined" :label="sellerInitial" shape="circle" size="small" />
        <span>{{ listing.seller?.name }}</span>
        <LevelBadge v-if="listing.seller" :level="listing.seller.level" />
      </span>
      <span class="meta">{{ listing.game }} · {{ listing.quantity }} un</span>
    </template>
    <template #content>
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
        <Button v-if="!listing.boost" label="Turbinar" icon="pi pi-bolt" size="small" outlined @click="showBoost = true" />
        <Button label="Editar" icon="pi pi-pencil" size="small" severity="secondary" outlined @click="showEdit = true" />
        <Button label="Excluir" icon="pi pi-trash" size="small" severity="danger" text @click="removeListing" />
      </div>
      <Button v-else label="Entre para comprar" icon="pi pi-sign-in" text @click="navigateTo('/login')" />
    </template>
  </Card>

  <BoostDialog v-if="isOwn" v-model:visible="showBoost" :listing="listing" />
  <ListingFormDialog v-if="isOwn" v-model:visible="showEdit" :listing="listing" @updated="() => {}" />
</template>

<style scoped>
/* Hover "gaming": leve elevação + glow por raridade (regra 12 — efeito não coberto pelo Card). */
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
.thumb {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: color-mix(in srgb, var(--p-content-border-color) 60%, transparent);
}
.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.thumb-ph {
  display: grid;
  place-items: center;
  height: 100%;
  color: var(--p-text-muted-color);
  font-size: 2.5rem;
}
.thumb-boost {
  position: absolute;
  top: 0.6rem;
  left: 0.6rem;
}
.title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.seller {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}
.meta {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}
.own-actions {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}
.desc {
  margin: 0 0 0.75rem;
  opacity: 0.85;
}
.price {
  font-size: 1.25rem;
  color: var(--rar);
}
</style>
