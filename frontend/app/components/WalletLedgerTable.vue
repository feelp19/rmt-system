<script setup lang="ts">
type Entry = {
  id: number
  type: string
  direction: 'credit' | 'debit'
  amount_cents: number
  balance_after_cents: number
  code: string
  created_at: string
}

const api = useApi()
const entries = ref<Entry[]>([])
const loading = ref(true)

const typeLabels: Record<string, string> = {
  deposit_credit: 'Depósito',
  pix_topup_credit: 'PIX',
  escrow_debit: 'Compra (escrow)',
  escrow_release_credit: 'Venda liberada',
  boost_debit: 'Boost',
}

onMounted(async () => {
  try {
    const res = await api.get<{ data: Entry[] }>('/wallet/ledger')
    entries.value = res.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Card class="ledger-card">
    <template #title>Extrato (ledger de confiabilidade)</template>
    <template #content>
      <DataTable :value="entries" :loading="loading" dataKey="id" paginator :rows="10">
        <Column header="Tipo">
          <template #body="{ data }">{{ typeLabels[data.type] ?? data.type }}</template>
        </Column>
        <Column header="Valor">
          <template #body="{ data }">
            <span :class="data.direction === 'credit' ? 'amount-credit' : 'amount-debit'">
              {{ data.direction === 'credit' ? '+' : '−' }}{{ formatCents(data.amount_cents) }}
            </span>
          </template>
        </Column>
        <Column header="Saldo após">
          <template #body="{ data }">{{ formatCents(data.balance_after_cents) }}</template>
        </Column>
        <Column header="Código de confiabilidade">
          <template #body="{ data }"><LedgerReceipt :code="data.code" /></template>
        </Column>
        <template #empty>Nenhuma transação ainda.</template>
      </DataTable>
    </template>
  </Card>
</template>

<style scoped>
.ledger-card {
  margin-top: 1.5rem;
}
.amount-credit {
  color: var(--p-green-600);
  font-weight: 600;
}
.amount-debit {
  color: var(--p-red-600);
  font-weight: 600;
}
</style>
