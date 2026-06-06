<script setup lang="ts">
import type { RankingEntry } from '~/types'

const api = useApi()

const { data } = await useAsyncData(
  'leaderboard',
  () => api.get<{ data: RankingEntry[] }>('/leaderboard'),
  { server: false },
)

const ranking = computed(() => data.value?.data ?? [])
const initial = (name: string) => name.charAt(0).toUpperCase()
</script>

<template>
  <section>
    <h1>Ranking</h1>
    <p class="lead">Os vendedores com mais XP na plataforma.</p>

    <Message v-if="!ranking.length" severity="info" :closable="false">Ninguém no ranking ainda.</Message>
    <ol v-else class="rank">
      <li v-for="(entry, i) in ranking" :key="entry.id" class="row">
        <span class="pos" :class="{ podium: i < 3 }">{{ i + 1 }}</span>
        <Avatar :image="entry.avatar_url ?? undefined" :label="initial(entry.name)" shape="circle" />
        <span class="name">{{ entry.name }}</span>
        <LevelBadge :level="entry.level" />
        <span class="xp">{{ entry.xp }} XP</span>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.lead {
  color: var(--p-text-muted-color);
  margin: 0 0 1.5rem;
}
.rank {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 640px;
}
.row {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.6rem 0.85rem;
  border-radius: 0.75rem;
  border: 1px solid var(--p-content-border-color);
}
.pos {
  font-family: var(--font-display);
  font-weight: 800;
  width: 1.8rem;
  text-align: center;
  color: var(--p-text-muted-color);
}
.pos.podium {
  color: var(--gold);
}
.name {
  font-weight: 600;
  flex: 1;
}
.xp {
  color: var(--p-primary-color);
  font-weight: 700;
}
</style>
