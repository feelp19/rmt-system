<script setup lang="ts">
const steps = [
  {
    num: '01',
    title: 'Você compra',
    text: 'O valor sai da sua carteira e fica retido no cofre. O vendedor ainda não recebe nada.',
  },
  {
    num: '02',
    title: 'Entrega e confirmação',
    text: 'O vendedor entrega o item ou o gold. Cada lado dá o seu "sim" na plataforma.',
  },
  {
    num: '03',
    title: 'Libera, menos 5%',
    text: 'Com as duas confirmações, o vendedor recebe o valor. A plataforma fica com a taxa de 5%.',
  },
]
</script>

<template>
  <section class="how">
    <p class="kicker">Como funciona</p>
    <h2 class="heading">Seu dinheiro no cofre, do clique à confirmação.</h2>

    <ol class="flow">
      <li v-for="step in steps" :key="step.num" class="step">
        <span class="num">{{ step.num }}</span>
        <h3>{{ step.title }}</h3>
        <p>{{ step.text }}</p>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.how {
  margin-bottom: 4rem;
}
.kicker {
  margin: 0 0 0.5rem;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--p-primary-color);
}
.heading {
  margin: 0 0 2.5rem;
  max-width: 18ch;
  font-size: clamp(1.7rem, 3.5vw, 2.6rem);
  font-weight: 800;
  line-height: 1.05;
}

.flow {
  position: relative;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
/* Trilho que conecta os números. */
.flow::before {
  content: '';
  position: absolute;
  top: 1.7rem;
  left: 4%;
  right: 4%;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--p-primary-color) 18%, var(--p-primary-color) 82%, transparent);
  opacity: 0.28;
}

.num {
  position: relative;
  display: inline-block;
  margin-bottom: 0.85rem;
  padding-right: 0.6rem;
  font-family: var(--font-display);
  font-size: clamp(2.4rem, 5vw, 3.4rem);
  font-weight: 800;
  line-height: 1;
  color: transparent;
  -webkit-text-stroke: 1.5px var(--p-primary-color);
  /* Fundo da página por trás do número, pra "cortar" o trilho. */
  background: var(--p-content-background, #fff);
}
.step h3 {
  margin: 0 0 0.4rem;
  font-size: 1.2rem;
}
.step p {
  margin: 0;
  max-width: 34ch;
  color: var(--p-text-muted-color);
  line-height: 1.55;
}

@media (max-width: 760px) {
  .flow {
    grid-template-columns: 1fr;
    gap: 1.75rem;
  }
  .flow::before {
    display: none;
  }
}
</style>
