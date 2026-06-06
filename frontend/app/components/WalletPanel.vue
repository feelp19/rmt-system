<script setup lang="ts">
const { balance, refresh } = useWallet()
const showDeposit = ref(false)

onMounted(refresh)
</script>

<template>
  <Card>
    <template #title>Carteira</template>
    <template #content>
      <p class="balance">{{ formatCents(balance ?? 0) }}</p>
      <Message severity="info" :closable="false">
        Saldo simulado do MVP. Use "Adicionar saldo" para depositar e poder comprar.
      </Message>
    </template>
    <template #footer>
      <Button label="Adicionar saldo" icon="pi pi-plus" @click="showDeposit = true" />
    </template>
  </Card>

  <DepositDialog v-model:visible="showDeposit" />
</template>

<style scoped>
.balance {
  font-size: 2rem;
  font-weight: 700;
  margin: 0 0 1rem;
  color: var(--p-primary-color);
}
</style>
