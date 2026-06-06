<script setup lang="ts">
const props = defineProps<{ code: string }>()
const api = useApi()

const verifying = ref(false)
const result = ref<{ valid: boolean } | null>(null)

const short = (h: string) => `${h.slice(0, 8)}…${h.slice(-8)}`

async function verify() {
  verifying.value = true
  try {
    const res = await api.get<{ data: { valid: boolean } }>(`/ledger/${props.code}/verify`)
    result.value = { valid: res.data.valid }
  } finally {
    verifying.value = false
  }
}
</script>

<template>
  <div class="receipt">
    <Tag :value="short(code)" severity="secondary" />
    <Button label="Verificar" size="small" text :loading="verifying" @click="verify" />
    <Tag
      v-if="result"
      :value="result.valid ? 'Legítima' : 'Adulterada'"
      :severity="result.valid ? 'success' : 'danger'"
    />
  </div>
</template>

<style scoped>
.receipt {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
</style>
