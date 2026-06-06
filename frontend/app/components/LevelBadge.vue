<script setup lang="ts">
defineProps<{ level: number }>()
</script>

<template>
  <span class="lvl"><i class="pi pi-star-fill" /> Nv {{ level }}</span>
</template>

<style scoped>
/* Badge de nível — ouro com texto ink (contraste alto). */
.lvl {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.72rem;
  font-weight: 800;
  line-height: 1;
  padding: 0.18rem 0.45rem;
  border-radius: 999px;
  color: var(--ink, #0a100d);
  background: var(--gold, #f3b340);
}
</style>
