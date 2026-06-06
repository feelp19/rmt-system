<script setup lang="ts">
import type { ProfileStats } from '~/types'

defineProps<{ stats?: ProfileStats | null }>()

const { user } = useAuth()

const xpPct = computed(() => {
  const u = user.value
  if (!u || u.next_level_xp === null) return 100
  const span = u.next_level_xp - u.level_floor
  return span > 0 ? Math.min(100, Math.round(((u.xp - u.level_floor) / span) * 100)) : 0
})
</script>

<template>
  <Card class="summary">
    <template #content>
      <div class="top">
        <AvatarUploader />
        <div class="who">
          <h1>{{ user?.name }}</h1>
          <div class="meta">
            <LevelBadge v-if="user" :level="user.level" />
            <span class="email">{{ user?.email }}</span>
          </div>
        </div>
      </div>

      <div v-if="user" class="xp">
        <div class="xp-head">
          <span>Nível {{ user.level }}</span>
          <span>{{ user.xp }} XP{{ user.next_level_xp ? ` / ${user.next_level_xp}` : ' (máx)' }}</span>
        </div>
        <ProgressBar :value="xpPct" :show-value="false" />
      </div>

      <div v-if="stats" class="stats">
        <div class="stat"><strong>{{ stats.active_listings }}</strong><span>anúncios ativos</span></div>
        <div class="stat"><strong>{{ stats.sales }}</strong><span>vendas</span></div>
        <div class="stat"><strong>{{ stats.purchases }}</strong><span>compras</span></div>
      </div>
    </template>
  </Card>
</template>

<style scoped>
.top {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
}
.who h1 {
  margin: 0 0 0.4rem;
  font-size: clamp(1.5rem, 3vw, 2rem);
}
.meta {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}
.email {
  color: var(--p-text-muted-color);
  font-size: 0.9rem;
}
.xp {
  margin-top: 1.5rem;
}
.xp-head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.35rem;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}
.stats {
  display: flex;
  gap: 2rem;
  margin-top: 1.5rem;
  flex-wrap: wrap;
}
.stat {
  display: flex;
  flex-direction: column;
}
.stat strong {
  font-size: 1.6rem;
  color: var(--p-primary-color);
}
.stat span {
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}
</style>
