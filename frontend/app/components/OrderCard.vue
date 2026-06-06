<script setup lang="ts">
import type { Order } from '~/types'

const props = defineProps<{ order: Order }>()
const emit = defineEmits<{ updated: [order: Order] }>()

const { user } = useAuth()
const { refresh: refreshWallet } = useWallet()
const api = useApi()
const toast = useToast()
const acting = ref(false)

const isSeller = computed(() => user.value?.id === props.order.seller?.id)
const isBuyer = computed(() => user.value?.id === props.order.buyer?.id)
const isOpen = computed(() => props.order.status === 'awaiting_confirmation')

const statusMeta = computed(() => {
  switch (props.order.status) {
    case 'completed':
      return { label: 'Concluído', severity: 'success' as const }
    case 'cancelled':
      return { label: 'Cancelado', severity: 'danger' as const }
    default:
      return { label: 'Aguardando confirmação', severity: 'warn' as const }
  }
})

const counterpart = computed(() =>
  isBuyer.value
    ? `Comprando de ${props.order.seller?.name ?? '—'}`
    : `Vendendo para ${props.order.buyer?.name ?? '—'}`,
)

const waitingOther = computed(
  () =>
    isOpen.value &&
    ((isSeller.value && props.order.seller_confirmed_at !== null) ||
      (isBuyer.value && props.order.buyer_confirmed_at !== null)),
)

const act = async (path: string, okSummary: string) => {
  acting.value = true
  try {
    const res = await api.post<{ data: Order }>(`/orders/${props.order.id}/${path}`)
    await refreshWallet()
    toast.add({ severity: 'success', summary: okSummary, life: 3500 })
    emit('updated', res.data)
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Falha na confirmação.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    acting.value = false
  }
}
</script>

<template>
  <Card>
    <template #title>
      <div class="title-row">
        <span>{{ order.listing?.title ?? 'Anúncio removido' }}</span>
        <Tag :value="statusMeta.label" :severity="statusMeta.severity" />
      </div>
    </template>
    <template #subtitle>{{ order.listing?.game }} · {{ counterpart }}</template>
    <template #content>
      <div class="rows">
        <span>Valor: <strong>{{ formatCents(order.amount_cents) }}</strong></span>
        <span>Taxa (5%): {{ formatCents(order.fee_cents) }}</span>
        <span>Vendedor recebe: <strong>{{ formatCents(order.seller_payout_cents) }}</strong></span>
      </div>
      <div class="confirm-state">
        <Tag
          :icon="order.seller_confirmed_at ? 'pi pi-check' : 'pi pi-clock'"
          :severity="order.seller_confirmed_at ? 'success' : 'secondary'"
          value="Vendedor entregou"
        />
        <Tag
          :icon="order.buyer_confirmed_at ? 'pi pi-check' : 'pi pi-clock'"
          :severity="order.buyer_confirmed_at ? 'success' : 'secondary'"
          value="Comprador recebeu"
        />
      </div>
    </template>
    <template #footer>
      <Button
        v-if="isOpen && isSeller && !order.seller_confirmed_at"
        label="Confirmar entrega"
        icon="pi pi-box"
        :loading="acting"
        @click="act('confirm-delivery', 'Entrega confirmada')"
      />
      <Button
        v-if="isOpen && isBuyer && !order.buyer_confirmed_at"
        label="Confirmar recebimento"
        icon="pi pi-check-circle"
        :loading="acting"
        @click="act('confirm-receipt', 'Recebimento confirmado')"
      />
      <Message v-if="waitingOther" severity="info" :closable="false">Aguardando a outra parte confirmar.</Message>
      <Message v-if="order.status === 'completed'" severity="success" :closable="false">
        Transação concluída — saldo liberado ao vendedor.
      </Message>
    </template>
  </Card>
</template>

<style scoped>
.title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.rows {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-bottom: 0.85rem;
}
.confirm-state {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
</style>
