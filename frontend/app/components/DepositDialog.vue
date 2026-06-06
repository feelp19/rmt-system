<script setup lang="ts">
import type { PixCharge } from '~/types'

const visible = defineModel<boolean>('visible', { default: false })

const api = useApi()
const { deposit, refresh: refreshWallet } = useWallet()
const toast = useToast()

const amountReais = ref(0)
const charge = ref<PixCharge | null>(null)
const generating = ref(false)
const demoLoading = ref(false)
let pollTimer: ReturnType<typeof setInterval> | null = null

const stopPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

const reset = () => {
  stopPolling()
  charge.value = null
  amountReais.value = 0
}

const startPolling = () => {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (!charge.value) return
    try {
      const res = await api.get<{ data: PixCharge }>(`/wallet/pix/${charge.value.id}`)
      charge.value = res.data
      if (res.data.status === 'paid') {
        stopPolling()
        await refreshWallet()
        toast.add({ severity: 'success', summary: 'Saldo creditado!', life: 4000 })
        reset()
        visible.value = false
      } else if (res.data.status === 'expired' || res.data.status === 'canceled') {
        stopPolling()
        toast.add({ severity: 'warn', summary: 'PIX expirado', detail: 'Gere um novo para tentar de novo.', life: 4000 })
      }
    } catch {
      // mantém o polling; erro transitório
    }
  }, 3000)
}

const generatePix = async () => {
  generating.value = true
  try {
    const res = await api.post<{ data: PixCharge }>('/wallet/pix', { amount_cents: reaisToCents(amountReais.value) })
    charge.value = res.data
    startPolling()
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Não foi possível gerar o PIX.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    generating.value = false
  }
}

const copyCode = async () => {
  if (!charge.value || !import.meta.client) return
  await navigator.clipboard.writeText(charge.value.qr_code)
  toast.add({ severity: 'info', summary: 'Código copiado', life: 2500 })
}

const demoDeposit = async () => {
  demoLoading.value = true
  try {
    await deposit(reaisToCents(amountReais.value))
    toast.add({ severity: 'success', summary: 'Crédito demo aplicado', life: 3000 })
    reset()
    visible.value = false
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Falha no depósito.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    demoLoading.value = false
  }
}

watch(visible, (open) => {
  if (!open) reset()
})
onUnmounted(stopPolling)
</script>

<template>
  <Dialog v-model:visible="visible" header="Adicionar saldo" modal :style="{ width: '27rem' }">
    <div v-if="!charge" class="form">
      <InputNumber v-model="amountReais" mode="currency" currency="BRL" locale="pt-BR" :min="5" fluid />
      <Button
        label="Gerar PIX"
        icon="pi pi-qrcode"
        :loading="generating"
        :disabled="amountReais < 5"
        fluid
        @click="generatePix"
      />
      <Button
        label="Crédito demo (instantâneo)"
        icon="pi pi-bolt"
        severity="secondary"
        text
        :loading="demoLoading"
        :disabled="amountReais < 1"
        @click="demoDeposit"
      />
      <Message severity="secondary" :closable="false">
        No sandbox o PIX gera um QR real, mas a confirmação depende do pagamento. Use o crédito demo para testar o resto.
      </Message>
    </div>

    <div v-else class="pix">
      <p>Pague <strong>{{ formatCents(charge.amount_cents) }}</strong> escaneando o QR ou copiando o código:</p>
      <Image :src="charge.qr_code_base64" alt="QR Code PIX" width="220" />
      <div class="copy">
        <InputText :model-value="charge.qr_code" readonly fluid />
        <Button icon="pi pi-copy" aria-label="Copiar código" @click="copyCode" />
      </div>
      <div class="status">
        <ProgressSpinner style="width: 1.4rem; height: 1.4rem" stroke-width="6" />
        <span>Aguardando pagamento…</span>
      </div>
      <Button label="Cancelar" text @click="reset" />
    </div>
  </Dialog>
</template>

<style scoped>
.form,
.pix {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  align-items: stretch;
}
.pix {
  align-items: center;
  text-align: center;
}
.copy {
  display: flex;
  gap: 0.5rem;
  width: 100%;
}
.status {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  color: var(--p-text-muted-color);
}
</style>
