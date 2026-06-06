/** Ao carregar no browser, hidrata o usuário a partir do token salvo. */
export default defineNuxtPlugin(async () => {
  const { token, fetchMe } = useAuth()
  if (token.value) {
    await fetchMe()
  }
})
