<script setup lang="ts">
const { data: health, refresh } = await useAPI<{ status: string }>('/health')
</script>

<template>
  <div style="padding: 2rem; max-width: 32rem; margin: 0 auto">
    <Card>
      <template #title>rmt-system</template>
      <template #content>
        <p>
          API status:
          <strong>{{ health?.status ?? 'down' }}</strong>
          <i
            :class="health?.status === 'ok' ? 'pi pi-check-circle' : 'pi pi-times-circle'"
            :style="{ color: health?.status === 'ok' ? 'green' : 'red', marginLeft: '0.5rem' }"
          />
        </p>
        <Button label="Recarregar" icon="pi pi-refresh" @click="() => refresh()" />
      </template>
    </Card>
  </div>
</template>
