import Aura from '@primeuix/themes/aura'
import { definePreset } from '@primeuix/themes'

// Marca "loot": primary verde do Aura → escala ouro. contrastColor escuro
// garante AA em botão/preço dourado (texto escuro sobre ouro).
const LootGold = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#FFFBEA',
      100: '#FFF3C4',
      200: '#FCE588',
      300: '#FADB5F',
      400: '#F7C948',
      500: '#F5C542',
      600: '#DEA818',
      700: '#B0850F',
      800: '#8A680C',
      900: '#5C4509',
      950: '#3A2B05',
    },
    colorScheme: {
      dark: {
        primary: {
          color: '{primary.500}',
          contrastColor: '#0B0E14',
          hoverColor: '{primary.400}',
          activeColor: '{primary.600}',
        },
      },
    },
  },
})

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  ssr: true,
  modules: ['@primevue/nuxt-module'],
  css: ['primeicons/primeicons.css', '~/assets/css/main.css'],
  app: {
    head: {
      htmlAttrs: { class: 'app-dark' }, // dark forçado no app inteiro
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
        preset: LootGold,
        options: { darkModeSelector: '.app-dark', cssLayer: false },
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
