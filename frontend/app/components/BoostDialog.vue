<script setup lang="ts">
import type { BoostTier, Listing } from '~/types'

const props = defineProps<{ listing: Listing }>()
const visible = defineModel<boolean>('visible', { default: false })

const api = useApi()
const { refresh: refreshWallet } = useWallet()
const toast = useToast()
const saving = ref(false)
const selected = ref<BoostTier>('intermediate')

const packages: { tier: BoostTier; label: string; priceCents: number; reach: string }[] = [
  { tier: 'basic', label: 'Básico', priceCents: 500, reach: 'Aparece na faixa "Em destaque" da home.' },
  { tier: 'intermediate', label: 'Intermediário', priceCents: 1500, reach: 'Destaque + topo da vitrine geral.' },
  { tier: 'advanced', label: 'Avançado', priceCents: 2500, reach: 'Destaque + topo + slot patrocinado nas listagens.' },
]

const current = computed(() => packages.find((pkg) => pkg.tier === selected.value)!)

const confirm = async () => {
  saving.value = true
  try {
    await api.post(`/listings/${props.listing.id}/boosts`, {
      tier: selected.value,
      payment_method: 'wallet',
    })
    await refreshWallet()
    await refreshNuxtData(['listings', 'featured'])
    toast.add({ severity: 'success', summary: 'Anúncio turbinado!', detail: 'Já está em destaque.', life: 4000 })
    visible.value = false
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Não foi possível turbinar.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" header="Turbinar anúncio" modal :style="{ width: '32rem' }">
    <p class="lead">Quanto mais alto o pacote, mais gente vê seu anúncio. Pago com o saldo da carteira.</p>

    <SelectButton
      v-model="selected"
      :options="packages"
      option-label="label"
      option-value="tier"
      :allow-empty="false"
    />

    <div class="detail">
      <span class="price">{{ formatCents(current.priceCents) }}</span>
      <Message severity="info" :closable="false">{{ current.reach }}</Message>
    </div>

    <template #footer>
      <Button label="Cancelar" text @click="visible = false" />
      <Button
        :label="`Turbinar por ${formatCents(current.priceCents)}`"
        icon="pi pi-bolt"
        :loading="saving"
        @click="confirm"
      />
    </template>
  </Dialog>
</template>

<style scoped>
.lead {
  margin: 0 0 1rem;
  color: var(--p-text-muted-color);
}
.detail {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
}
.price {
  font-size: 1.75rem;
  font-weight: 800;
  color: var(--p-primary-color);
}
</style>
