<script setup lang="ts">
// Layout base: cabeçalho + área de conteúdo + camada de toasts.
</script>

<template>
  <div class="shell">
    <AppHeader />
    <main class="page">
      <slot />
    </main>
    <Toast />
    <ConfirmDialog />
  </div>
</template>

<style scoped>
/* Wrapper full-width: segura o scroll horizontal das seções full-bleed (hero usa
   100vw) sem cortá-las. O clip TEM que ficar aqui (largura total), nunca no
   .page (max-width 1100px) — senão o hero é cortado. */
.shell {
  overflow-x: clip;
}

/* Container de página — PrimeVue não tem primitivo de layout de página.
   Sem overflow aqui: o hero precisa "vazar" pra 100vw. */
.page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 1.5rem;
}
</style>
