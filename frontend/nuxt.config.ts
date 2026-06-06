import Aura from '@primeuix/themes/aura'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  ssr: true,
  modules: ['@primevue/nuxt-module'],
  css: ['primeicons/primeicons.css', '~/assets/css/main.css'],
  app: {
    head: {
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: 'anonymous' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&display=swap',
        },
      ],
    },
  },
  primevue: {
    options: {
      ripple: true,
      theme: {
        preset: Aura,
        options: { darkModeSelector: 'system', cssLayer: false },
      },
    },
  },
  runtimeConfig: {
    // SSR (server-side) internal URL. Prod default = Caddy in the app container.
    // Dev: override with NUXT_API_BASE=http://localhost:8000/api
    apiBase: 'http://app/api',
    public: {
      // Browser uses relative, same-origin path. Override with NUXT_PUBLIC_API_BASE.
      apiBase: '/api',
    },
  },
  // Dev only: browser /api → Laravel Octane :8000 (ignored by the built node server).
  nitro: {
    devProxy: {
      '/api': { target: 'http://localhost:8000/api', changeOrigin: true },
    },
  },
})
