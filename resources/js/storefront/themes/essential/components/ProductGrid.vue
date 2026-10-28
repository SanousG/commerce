<script setup lang="ts">
import type { PublicProduct } from '../../../types';
import ProductCard from './ProductCard.vue';
defineProps<{ products: PublicProduct[]; locale: string }>();
</script>
<template><div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4"><ProductCard v-for="product in products" :key="product.slug" :product="product" :locale="locale" /></div></template>
