<script setup lang="ts">
const { user, fetchMe } = useAuth()
const api = useApi()
const toast = useToast()
const uploading = ref(false)
const localPreview = ref<string | null>(null)

const initials = computed(() => (user.value?.name ?? '?').trim().charAt(0).toUpperCase())
const avatarSrc = computed(() => localPreview.value ?? user.value?.avatar_url ?? undefined)

const onSelect = async (event: { files: File[] }) => {
  const file = event.files?.[0]
  if (!file) return
  if (import.meta.client) localPreview.value = URL.createObjectURL(file) // feedback imediato

  uploading.value = true
  try {
    const body = new FormData()
    body.append('avatar', file)
    await api.post('/me/avatar', body)
    await fetchMe()
    toast.add({ severity: 'success', summary: 'Avatar atualizado', life: 3000 })
  } catch (e: unknown) {
    localPreview.value = null
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Falha ao enviar a foto.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div class="av">
    <Avatar :image="avatarSrc" :label="initials" shape="circle" size="xlarge" />
    <FileUpload
      mode="basic"
      :auto="false"
      custom-upload
      accept="image/*"
      :max-file-size="5242880"
      choose-label="Trocar foto"
      choose-icon="pi pi-camera"
      :disabled="uploading"
      @select="onSelect"
    />
  </div>
</template>

<style scoped>
.av {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}
</style>
