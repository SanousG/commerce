<script setup lang="ts">
import { computed } from 'vue';
import { resolveStorefrontPage } from '../../storefront/themeRegistry';
import type { PublicProduct, PublicStore } from '../../storefront/types';
const props = defineProps<{ store: PublicStore; product: PublicProduct }>();
const page = computed(() => resolveStorefrontPage(props.store.theme, 'Product'));
</script>
<template><component :is="page" v-bind="$props" /></template>
