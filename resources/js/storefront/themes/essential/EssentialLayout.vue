<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicStore } from '../../types';
import StoreFooter from './components/StoreFooter.vue';
import StoreHeader from './components/StoreHeader.vue';
const props = defineProps<{ store: PublicStore }>();
const { t } = useI18n();
const tokens = computed(() => ({ '--sf-primary': props.store.branding.primaryColor, '--sf-accent': props.store.branding.accentColor, '--sf-secondary': props.store.branding.secondaryColor, '--sf-background': '#F8FAFC', '--sf-surface': '#FFFFFF', '--sf-border': '#E2E8F0' }));
</script>
<template><div :style="tokens" class="min-h-screen min-w-[320px] overflow-x-clip bg-[var(--sf-background)] font-sans text-slate-950"><a href="#storefront-main-content" class="sr-only z-50 rounded bg-white px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4">{{ t('storefront.skipToContent') }}</a><StoreHeader :store="store" /><main id="storefront-main-content" tabindex="-1"><slot /></main><StoreFooter :store="store" /></div></template>
