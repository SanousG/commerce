<script setup lang="ts">
import { computed } from 'vue';
import { resolveStorefrontPage } from '../../storefront/themeRegistry';
import type { PublicCheckout, PublicStore } from '../../storefront/types';

const props = defineProps<{ store: PublicStore; checkout: PublicCheckout }>();
const page = computed(() => resolveStorefrontPage(props.store.theme, 'Checkout'));
</script>

<template><component :is="page" v-bind="$props" /></template>
