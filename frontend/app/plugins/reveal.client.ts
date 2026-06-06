// Diretiva v-reveal: revela o elemento ao entrar na viewport (IntersectionObserver).
// Client-only; respeita prefers-reduced-motion. Guarda o observer no elemento pra
// desconectar em unmounted (evita leak quando a seção é desmontada antes de aparecer).
type RevealEl = HTMLElement & { _io?: IntersectionObserver }

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.directive('reveal', {
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
