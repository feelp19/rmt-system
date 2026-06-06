<script setup lang="ts">
// Layout base: cabeçalho + área de conteúdo + camada de toasts.
</script>

<template>
  <div>
    <AppHeader />
    <main class="page">
      <slot />
    </main>
    <Toast />
  </div>
</template>

<style scoped>
/* Container de página — PrimeVue não tem primitivo de layout de página. */
.page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 1.5rem;
  /* Permite seções full-bleed (hero usa 100vw) sem scroll horizontal. */
  overflow-x: clip;
}
</style>
