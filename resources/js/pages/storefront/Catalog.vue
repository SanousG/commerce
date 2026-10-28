<script setup lang="ts">
import { computed } from 'vue';
import { resolveStorefrontPage } from '../../storefront/themeRegistry';
import type { PublicCategory, PublicProduct, PublicStore } from '../../storefront/types';
interface Paginator { data: PublicProduct[]; links: Array<{ url: string | null; label: string; active: boolean }>; current_page: number; last_page: number; total: number }
const props = defineProps<{ store: PublicStore; products: Paginator; categories: PublicCategory[]; filters: { search: string | null; category: string | null } }>();
const page = computed(() => resolveStorefrontPage(props.store.theme, 'Catalog'));
</script>
<template><component :is="page" v-bind="$props" /></template>
