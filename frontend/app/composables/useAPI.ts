export function useAPI<T>(path: string, opts: Parameters<typeof useFetch>[1] = {}) {
  const config = useRuntimeConfig()
  const base = import.meta.server ? config.apiBase : config.public.apiBase
  return useFetch<T>(`${base}${path}`, opts)
  // SSR    -> http://localhost:8000/api/health  (config.apiBase + /health)
  // client -> /api/health                       (config.public.apiBase + /health)
}
