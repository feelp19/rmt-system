<script setup lang="ts">
// Homepage: página fina — só monta as seções (regra 11).
</script>

<template>
  <div>
    <HomeHero />
    <div v-reveal><HomeActivity /></div>
    <div v-reveal><HomeHowItWorks /></div>
    <div v-reveal><FeaturedListings /></div>
    <div v-reveal><HomeStats /></div>
    <div v-reveal><ListingGrid /></div>
  </div>
</template>
