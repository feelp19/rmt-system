/** Bloqueia rotas privadas sem token (cookie legível em SSR e no browser). */
export default defineNuxtRouteMiddleware(() => {
  const token = useCookie<string | null>('rmt_token')
  if (!token.value) {
    return navigateTo('/login')
  }
})
