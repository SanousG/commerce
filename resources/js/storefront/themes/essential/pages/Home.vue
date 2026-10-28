<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PublicCategory, PublicProduct, PublicStore } from '../../../types';
import CategoryCard from '../components/CategoryCard.vue';
import EmptyState from '../components/EmptyState.vue';
import HeroSection from '../components/HeroSection.vue';
import ProductGrid from '../components/ProductGrid.vue';
import EssentialLayout from '../EssentialLayout.vue';
defineProps<{ store: PublicStore; categories: PublicCategory[]; featuredProducts: PublicProduct[] }>();
const { t } = useI18n();
</script>
<template><Head :title="store.name"><meta v-if="store.description" head-key="description" name="description" :content="store.description" /><link v-if="store.branding.faviconUrl" rel="icon" :href="store.branding.faviconUrl" /></Head><EssentialLayout :store="store"><div class="mx-auto max-w-[1440px] space-y-14 px-4 py-6 sm:px-6 sm:py-8 lg:px-8"><HeroSection :store="store" /><section v-if="categories.length" aria-labelledby="home-categories"><div class="mb-5 flex items-end justify-between gap-4"><div><h2 id="home-categories" class="text-2xl font-bold tracking-tight sm:text-3xl">{{ t('storefront.exploreCategories') }}</h2><p class="mt-1 text-slate-600">{{ t('storefront.categoriesDescription') }}</p></div><Link :href="`/tienda/${store.publicHandle}/productos`" class="hidden text-sm font-semibold text-[var(--sf-accent)] sm:block">{{ t('storefront.viewAll') }}</Link></div><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><CategoryCard v-for="category in categories" :key="category.slug" :category="category" /></div></section><section aria-labelledby="home-products"><div class="mb-5"><h2 id="home-products" class="text-2xl font-bold tracking-tight sm:text-3xl">{{ t('storefront.products') }}</h2><p class="mt-1 text-slate-600">{{ t('storefront.productsDescription') }}</p></div><ProductGrid v-if="featuredProducts.length" :products="featuredProducts" :locale="store.locale" /><EmptyState v-else :title="t('storefront.emptyCatalogTitle')" :description="t('storefront.emptyCatalogDescription')" /></section></div></EssentialLayout></template>
