<script setup lang="ts">
import type { Listing } from '~/types'

const props = defineProps<{ listing?: Listing | null }>()
const visible = defineModel<boolean>('visible', { default: false })
const emit = defineEmits<{ created: []; updated: [] }>()

const api = useApi()
const toast = useToast()
const saving = ref(false)

const isEdit = computed(() => !!props.listing)

const empty = () => ({ game: '', type: 'item', title: '', description: '', quantity: 1, priceReais: 0 })
const form = reactive(empty())
const photoFile = ref<File | null>(null)
const photoPreview = ref<string | null>(null)

const typeOptions = [
  { label: 'Item', value: 'item' },
  { label: 'Gold', value: 'gold' },
]

const hydrate = () => {
  if (props.listing) {
    form.game = props.listing.game
    form.type = props.listing.type
    form.title = props.listing.title
    form.description = props.listing.description ?? ''
    form.quantity = props.listing.quantity
    form.priceReais = props.listing.price_cents / 100
    photoPreview.value = props.listing.photo_url
  } else {
    Object.assign(form, empty())
    photoPreview.value = null
  }
  photoFile.value = null
}

watch(visible, (open) => {
  if (open) hydrate()
})

const onPhotoSelect = (event: { files: File[] }) => {
  const file = event.files?.[0]
  if (!file) return
  photoFile.value = file
  if (import.meta.client) {
    if (photoPreview.value?.startsWith('blob:')) URL.revokeObjectURL(photoPreview.value)
    photoPreview.value = URL.createObjectURL(file)
  }
}

const canSubmit = computed(() => {
  const base = !!form.game && !!form.title && form.priceReais > 0
  // Foto é obrigatória só na criação (na edição mantém a atual).
  return isEdit.value ? base : base && !!photoFile.value
})

const submit = async () => {
  saving.value = true
  try {
    const body = new FormData()
    body.append('game', form.game)
    body.append('type', form.type)
    body.append('title', form.title)
    if (form.description) body.append('description', form.description)
    body.append('quantity', String(form.quantity))
    body.append('price_cents', String(reaisToCents(form.priceReais)))
    if (photoFile.value) body.append('photo', photoFile.value)

    if (isEdit.value) {
      body.append('_method', 'PUT') // method spoofing (multipart PUT não é parseado direto)
      await api.post(`/listings/${props.listing!.id}`, body)
      toast.add({ severity: 'success', summary: 'Anúncio atualizado', life: 3000 })
      emit('updated')
    } else {
      await api.post('/listings', body)
      toast.add({ severity: 'success', summary: 'Anúncio publicado', life: 3000 })
      emit('created')
    }

    await refreshNuxtData(['listings', 'featured'])
    visible.value = false
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Falha ao salvar o anúncio.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" :header="isEdit ? 'Editar anúncio' : 'Novo anúncio'" modal :style="{ width: '32rem' }">
    <form class="form" @submit.prevent="submit">
      <InputText v-model="form.game" placeholder="Jogo (ex.: Tibia, WoW)" required fluid />
      <SelectButton v-model="form.type" :options="typeOptions" option-label="label" option-value="value" :allow-empty="false" />
      <InputText v-model="form.title" placeholder="Título do anúncio" required fluid />
      <Textarea v-model="form.description" placeholder="Descrição (opcional)" rows="3" fluid />
      <InputNumber v-model="form.quantity" :min="1" show-buttons placeholder="Quantidade" fluid />
      <InputNumber v-model="form.priceReais" mode="currency" currency="BRL" locale="pt-BR" :min="0" placeholder="Preço total" fluid />

      <div class="photo">
        <img v-if="photoPreview" :src="photoPreview" alt="Prévia da foto" class="preview" />
        <FileUpload
          mode="basic"
          :auto="false"
          custom-upload
          accept="image/*"
          :max-file-size="5242880"
          :choose-label="isEdit ? 'Trocar foto' : 'Escolher foto'"
          choose-icon="pi pi-image"
          @select="onPhotoSelect"
        />
        <small v-if="!isEdit" class="req">Foto obrigatória (JPEG, PNG ou WebP, até 5MB).</small>
      </div>

      <Button type="submit" :label="isEdit ? 'Salvar alterações' : 'Publicar'" icon="pi pi-check" :loading="saving" :disabled="!canSubmit" fluid />
    </form>
  </Dialog>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.photo {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.preview {
  width: 100%;
  max-height: 200px;
  object-fit: cover;
  border-radius: 0.5rem;
}
.req {
  color: var(--p-text-muted-color);
}
</style>
