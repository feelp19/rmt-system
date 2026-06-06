<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
</script>

<template>
  <div class="wallet-page">
    <div class="narrow">
      <h1>Carteira</h1>
      <WalletPanel />
    </div>
    <WalletLedgerTable />
  </div>
</template>

<style scoped>
.wallet-page {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
.narrow {
  max-width: 30rem;
}
</style>
