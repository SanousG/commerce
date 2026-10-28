<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PublicStore } from '../../../types';
defineProps<{ store: PublicStore }>();
const { t } = useI18n();
</script>
<template><section class="overflow-hidden rounded-2xl border border-[var(--sf-border)] bg-white shadow-sm"><div class="grid min-h-[25rem] items-stretch lg:grid-cols-2"><div class="flex flex-col justify-center p-6 sm:p-10 lg:p-14"><p class="text-sm font-semibold uppercase tracking-widest text-[var(--sf-accent)]">{{ t('storefront.welcome') }}</p><h1 class="mt-4 text-balance text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl">{{ store.name }}</h1><p v-if="store.description" class="mt-5 max-w-xl text-lg leading-8 text-slate-600">{{ store.description }}</p><div class="mt-8"><Link :href="`/tienda/${store.publicHandle}/productos`" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-[var(--sf-primary)] px-6 font-semibold text-white transition hover:opacity-90">{{ t('storefront.viewCatalog') }}</Link></div></div><div class="relative min-h-64 overflow-hidden bg-[linear-gradient(135deg,var(--sf-primary),var(--sf-accent))]" aria-hidden="true"><div class="absolute -right-16 -top-20 h-72 w-72 rounded-full border-[48px] border-white/15"/><div class="absolute -bottom-24 left-10 h-80 w-80 rotate-12 rounded-[4rem] bg-white/10"/><div class="absolute inset-10 rounded-3xl border border-white/20"/></div></div></section></template>
