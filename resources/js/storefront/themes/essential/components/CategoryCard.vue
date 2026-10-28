<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PublicCategory } from '../../../types';
defineProps<{ category: PublicCategory }>();
const { t } = useI18n();
</script>
<template><Link :href="category.url" class="group flex min-h-32 flex-col justify-between rounded-xl border border-[var(--sf-border)] bg-white p-5 shadow-sm transition hover:border-[var(--sf-accent)]"><div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-[var(--sf-accent)]" aria-hidden="true">#</div><div><h3 class="text-lg font-semibold text-slate-950">{{ category.name }}</h3><p class="mt-1 text-sm text-slate-500">{{ t('storefront.productCount', category.productCount) }}</p></div></Link></template>
