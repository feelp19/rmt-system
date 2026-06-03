export function useAPI<T>(path: string, opts: Parameters<typeof useFetch>[1] = {}) {
  const config = useRuntimeConfig()
  const base = import.meta.server ? config.apiBase : config.public.apiBase
  return useFetch<T>(`${base}${path}`, opts)
  // SSR    -> config.apiBase + path (prod: http://app/api; dev: http://localhost:8000/api via NUXT_API_BASE)
  // client -> config.public.apiBase + path (relative /api, same-origin via Caddy/devProxy)
}
