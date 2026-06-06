<script setup lang="ts">
const visible = defineModel<boolean>('visible', { default: false })
const emit = defineEmits<{ created: [] }>()

const api = useApi()
const toast = useToast()
const saving = ref(false)

const empty = () => ({ game: '', type: 'item', title: '', description: '', quantity: 1, priceReais: 0 })
const form = reactive(empty())

const typeOptions = [
  { label: 'Item', value: 'item' },
  { label: 'Gold', value: 'gold' },
]

const submit = async () => {
  saving.value = true
  try {
    await api.post('/listings', {
      game: form.game,
      type: form.type,
      title: form.title,
      description: form.description || null,
      quantity: form.quantity,
      price_cents: reaisToCents(form.priceReais),
    })
    toast.add({ severity: 'success', summary: 'Anúncio publicado', life: 3000 })
    Object.assign(form, empty())
    visible.value = false
    emit('created')
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Falha ao criar anúncio.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" header="Novo anúncio" modal :style="{ width: '30rem' }">
    <form class="form" @submit.prevent="submit">
      <InputText v-model="form.game" placeholder="Jogo (ex.: Tibia, WoW)" required fluid />
      <SelectButton v-model="form.type" :options="typeOptions" option-label="label" option-value="value" :allow-empty="false" />
      <InputText v-model="form.title" placeholder="Título do anúncio" required fluid />
      <Textarea v-model="form.description" placeholder="Descrição (opcional)" rows="3" fluid />
      <InputNumber v-model="form.quantity" :min="1" show-buttons placeholder="Quantidade" fluid />
      <InputNumber v-model="form.priceReais" mode="currency" currency="BRL" locale="pt-BR" :min="0" placeholder="Preço total" fluid />
      <Button type="submit" label="Publicar" icon="pi pi-check" :loading="saving" fluid />
    </form>
  </Dialog>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
</style>
