<template>
  <div class="container">
    <h1 class="title">Ürün Kataloğu</h1>
    <ProductCard
      title="Örnek Ürün"
      price="99.99"
      imgSrc="https://via.placeholder.com/150"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import ProductCard from '@/components/ProductCard.vue';

export default defineComponent({
  name: 'App',
  components: { ProductCard },
});
</script>

<style scoped>
.container { max-width: 800px; margin: 2rem auto; padding: 1rem; }
.title { font-family: 'Inter', sans-serif; color: #ff6b6b; }
</style>
