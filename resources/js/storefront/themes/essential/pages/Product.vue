<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicProduct, PublicStore } from '../../../types';
import AvailabilityBadge from '../components/AvailabilityBadge.vue';
import PriceDisplay from '../components/PriceDisplay.vue';
import ProductGallery from '../components/ProductGallery.vue';
import VariantSelector from '../components/VariantSelector.vue';
import EssentialLayout from '../EssentialLayout.vue';
const props = defineProps<{ store: PublicStore; product: PublicProduct }>();
const { t } = useI18n();
const initial = props.product.variants.find((variant) => variant.isDefault) ?? props.product.variants[0];
const selectedKey = ref(initial?.key ?? '');
const selected = computed(() => props.product.variants.find((variant) => variant.key === selectedKey.value) ?? initial);
</script>
<template><Head :title="product.name"><meta v-if="product.description" head-key="description" name="description" :content="product.description" /></Head><EssentialLayout :store="store"><div class="mx-auto max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8"><nav class="flex flex-wrap gap-1 text-sm text-slate-500" :aria-label="t('common.breadcrumb')"><Link :href="`/tienda/${store.publicHandle}`">{{ t('storefront.home') }}</Link><span aria-hidden="true">/</span><Link :href="`/tienda/${store.publicHandle}/productos`">{{ t('storefront.catalog') }}</Link><template v-if="product.categories[0]"><span aria-hidden="true">/</span><Link :href="product.categories[0].url">{{ product.categories[0].name }}</Link></template><span aria-hidden="true">/</span><span class="text-slate-800">{{ product.name }}</span></nav><div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-14"><ProductGallery :images="selected?.images ?? []" :fallback-images="product.images" /><section><p v-if="product.categories[0]" class="text-sm font-semibold text-[var(--sf-accent)]">{{ product.categories[0].name }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ product.name }}</h1><PriceDisplay v-if="selected" class="mt-5" :price="selected.price" :locale="store.locale" large /><AvailabilityBadge v-if="selected" class="mt-3" :availability="selected.availability" /><p v-if="product.description" class="mt-7 whitespace-pre-line text-base leading-7 text-slate-600">{{ product.description }}</p><div v-if="product.variants.length > 1" class="mt-8 border-t border-[var(--sf-border)] pt-7"><VariantSelector v-model="selectedKey" :variants="product.variants" /></div><div class="mt-8 rounded-xl border border-[var(--sf-border)] bg-white p-5"><h2 class="font-semibold">{{ t('storefront.purchaseUnavailableTitle') }}</h2><p class="mt-1 text-sm leading-6 text-slate-600">{{ t('storefront.purchaseUnavailableDescription') }}</p></div></section></div></div></EssentialLayout></template>
