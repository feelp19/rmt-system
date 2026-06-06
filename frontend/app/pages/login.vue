<script setup lang="ts">
const { isAuthenticated } = useAuth()

// Já autenticado → manda pra vitrine.
watchEffect(() => {
  if (isAuthenticated.value) {
    navigateTo('/')
  }
})
</script>

<template>
  <div class="center">
    <AuthCard />
  </div>
</template>

<style scoped>
.center {
  max-width: 28rem;
  margin: 2rem auto;
}
</style>
