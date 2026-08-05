<template>
  <div class="card">
    <img :src="imgSrc" alt="product" />
    <h2>{{ title }}</h2>
    <p class="price">{{ price }} TL</p>
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
export default defineComponent({
  name: 'ProductCard',
  props: {
    title: { type: String as PropType<string>, required: true },
    price: { type: String as PropType<string>, required: true },
    imgSrc: { type: String as PropType<string>, required: true },
  },
});
</script>

<style scoped>
.card { border: 2px solid #ff6b6b; border-radius: 12px; padding: 1rem; text-align: center; }
img { max-width: 100%; border-radius: 8px; }
.price { font-weight: bold; color: #ff6b6b; }
</style>
