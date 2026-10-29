<script setup lang="ts">
import { computed } from 'vue';
import { resolveStorefrontPage } from '../../storefront/themeRegistry';
import type { PublicCart, PublicStore } from '../../storefront/types';

const props = defineProps<{ store: PublicStore; cart: PublicCart }>();
const page = computed(() => resolveStorefrontPage(props.store.theme, 'Cart'));
</script>

<template><component :is="page" v-bind="$props" /></template>
