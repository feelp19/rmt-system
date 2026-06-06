export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.directive('reveal', {
    mounted(el: HTMLElement) {
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
    },
  })
})
