<script setup lang="ts">
const { login, register } = useAuth()
const toast = useToast()

const tab = ref('login')
const loading = ref(false)
const loginForm = reactive({ email: '', password: '' })
const registerForm = reactive({ name: '', email: '', password: '', password_confirmation: '' })

const submit = async (action: () => Promise<void>) => {
  loading.value = true
  try {
    await action()
    await navigateTo('/')
  } catch (e: unknown) {
    const detail = (e as { data?: { message?: string } })?.data?.message ?? 'Falha na autenticação.'
    toast.add({ severity: 'error', summary: 'Erro', detail, life: 4000 })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Card>
    <template #title>Acessar a plataforma</template>
    <template #content>
      <Tabs v-model:value="tab">
        <TabList>
          <Tab value="login">Entrar</Tab>
          <Tab value="register">Criar conta</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="login">
            <form class="form" @submit.prevent="submit(() => login(loginForm))">
              <InputText v-model="loginForm.email" type="email" placeholder="E-mail" required fluid />
              <Password v-model="loginForm.password" placeholder="Senha" :feedback="false" toggle-mask fluid />
              <Button type="submit" label="Entrar" icon="pi pi-sign-in" :loading="loading" fluid />
            </form>
          </TabPanel>
          <TabPanel value="register">
            <form class="form" @submit.prevent="submit(() => register(registerForm))">
              <InputText v-model="registerForm.name" placeholder="Nome" required fluid />
              <InputText v-model="registerForm.email" type="email" placeholder="E-mail" required fluid />
              <Password v-model="registerForm.password" placeholder="Senha (mín. 8)" :feedback="false" toggle-mask fluid />
              <Password v-model="registerForm.password_confirmation" placeholder="Confirmar senha" :feedback="false" toggle-mask fluid />
              <Button type="submit" label="Criar conta" icon="pi pi-user-plus" :loading="loading" fluid />
            </form>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </template>
  </Card>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding-top: 0.5rem;
}
</style>
