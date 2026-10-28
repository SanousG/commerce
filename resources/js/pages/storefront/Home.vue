<script setup lang="ts">
import { computed } from 'vue';
import { resolveStorefrontPage } from '../../storefront/themeRegistry';
import type { PublicCategory, PublicProduct, PublicStore } from '../../storefront/types';
const props = defineProps<{ store: PublicStore; categories: PublicCategory[]; featuredProducts: PublicProduct[] }>();
const page = computed(() => resolveStorefrontPage(props.store.theme, 'Home'));
</script>
<template><component :is="page" v-bind="$props" /></template>
