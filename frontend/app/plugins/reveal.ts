// Diretiva v-reveal: revela o elemento ao entrar na viewport (IntersectionObserver).
// Plugin UNIVERSAL (não .client): a diretiva é usada em index.vue (SSR), então
// precisa existir no server — senão ssrGetDirectiveProps quebra com
// "Cannot read properties of undefined (reading 'getSSRProps')". getSSRProps()
// no-op torna o SSR seguro; mounted/unmounted só rodam no client (garantia do Vue),
// então a lógica de IntersectionObserver segue client-only. Respeita prefers-reduced-motion.
type RevealEl = HTMLElement & { _io?: IntersectionObserver }

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.directive('reveal', {
    getSSRProps() {
      return {}
    },
    mounted(el: RevealEl) {
      if (!window.matchMedia('(prefers-reduced-motion: no-preference)').matches) return
      el.classList.add('reveal-init')
      const io = new IntersectionObserver((entries, obs) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            el.classList.add('reveal-in')
            obs.unobserve(el)
          }
        }
      }, { threshold: 0.12 })
      io.observe(el)
      el._io = io
    },
    unmounted(el: RevealEl) {
      el._io?.disconnect()
      delete el._io
    },
  })
})
