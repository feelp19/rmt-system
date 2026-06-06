<script setup lang="ts">
const { isAuthenticated, user, logout } = useAuth()
const { balance, refresh: refreshWallet } = useWallet()

watch(isAuthenticated, () => refreshWallet(), { immediate: true })

const initials = computed(() => (user.value?.name ?? '?').trim().charAt(0).toUpperCase())

const doLogout = async () => {
  await logout()
  await navigateTo('/login')
}
</script>

<template>
  <!-- Barra flex: layout puro, sem primitivo PrimeVue equivalente. -->
  <header class="bar">
    <NuxtLink to="/" class="brand">RMT Market</NuxtLink>
    <nav class="nav">
      <Button label="Vitrine" icon="pi pi-shop" text @click="navigateTo('/')" />
      <Button label="Ranking" icon="pi pi-trophy" text @click="navigateTo('/ranking')" />
      <template v-if="isAuthenticated">
        <Button label="Pedidos" icon="pi pi-receipt" text @click="navigateTo('/orders')" />
        <Button label="Carteira" icon="pi pi-wallet" text @click="navigateTo('/wallet')" />
        <Tag v-if="balance !== null" :value="formatCents(balance)" icon="pi pi-wallet" severity="success" />
        <!-- Chip de perfil clicável (avatar + nome + nível): composto, sem primitivo PrimeVue único. -->
        <button type="button" class="me" @click="navigateTo('/perfil')">
          <Avatar :image="user?.avatar_url ?? undefined" :label="initials" shape="circle" size="normal" />
          <span class="who">{{ user?.name }}</span>
          <LevelBadge v-if="user" :level="user.level" />
        </button>
        <Button icon="pi pi-sign-out" severity="secondary" text aria-label="Sair" @click="doLogout" />
      </template>
      <Button v-else label="Entrar" icon="pi pi-sign-in" @click="navigateTo('/login')" />
    </nav>
  </header>
</template>

<style scoped>
.bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 1.5rem;
  border-bottom: 1px solid var(--p-content-border-color);
  flex-wrap: wrap;
}
.brand {
  font-weight: 700;
  font-size: 1.15rem;
  text-decoration: none;
  color: var(--p-primary-color);
}
.nav {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.me {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.5rem;
  border: none;
  background: transparent;
  border-radius: 999px;
  cursor: pointer;
  color: inherit;
}
.me:hover {
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
}
.who {
  font-size: 0.9rem;
  font-weight: 600;
}
</style>
