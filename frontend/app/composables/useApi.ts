/**
 * Cliente HTTP autenticado para a API REST.
 *
 * - Anexa `Authorization: Bearer <token>` a partir do cookie quando presente.
 * - Usa a base interna no SSR e a base relativa (same-origin) no browser,
 *   seguindo o padrão do scaffold (ver useAPI.ts / nuxt.config.ts).
 */
export const useApi = () => {
  const config = useRuntimeConfig()
  const token = useCookie<string | null>('rmt_token')
  const base = () => (import.meta.server ? config.apiBase : config.public.apiBase)

  const request = <T>(path: string, opts: Record<string, unknown> = {}): Promise<T> => {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...((opts.headers as Record<string, string>) ?? {}),
    }
    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`
    }

    return $fetch<T>(`${base()}${path}`, { ...opts, headers })
  }

  return {
    get: <T>(path: string, opts: Record<string, unknown> = {}) =>
      request<T>(path, { ...opts, method: 'GET' }),
    post: <T>(path: string, body?: unknown, opts: Record<string, unknown> = {}) =>
      request<T>(path, { ...opts, method: 'POST', body }),
    del: <T>(path: string, opts: Record<string, unknown> = {}) =>
      request<T>(path, { ...opts, method: 'DELETE' }),
  }
}
