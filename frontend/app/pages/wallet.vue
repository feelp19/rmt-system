<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
</script>

<template>
  <div class="narrow">
    <h1>Carteira</h1>
    <WalletPanel />
  </div>
</template>

<style scoped>
.narrow {
  max-width: 30rem;
}
</style>
