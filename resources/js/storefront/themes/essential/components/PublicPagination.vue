<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
defineProps<{ links: Array<{ url: string | null; label: string; active: boolean }>; currentPage: number; lastPage: number }>();
const { t } = useI18n();
</script>
<template><nav v-if="lastPage > 1" class="flex items-center justify-center gap-2" :aria-label="t('storefront.pagination')"><template v-for="(link, index) in links" :key="`${link.label}-${index}`"><Link v-if="link.url" :href="link.url" preserve-scroll class="flex min-h-11 min-w-11 items-center justify-center rounded-lg border px-3 text-sm font-semibold" :class="link.active ? 'border-[var(--sf-primary)] bg-[var(--sf-primary)] text-white' : 'border-[var(--sf-border)] bg-white text-slate-700 hover:bg-slate-50'" :aria-current="link.active ? 'page' : undefined"><span v-if="index === 0">{{ t('storefront.previous') }}</span><span v-else-if="index === links.length - 1">{{ t('storefront.next') }}</span><span v-else v-html="link.label" /></Link><span v-else class="flex min-h-11 min-w-11 items-center justify-center px-2 text-sm text-slate-400"><span v-if="index === 0">{{ t('storefront.previous') }}</span><span v-else-if="index === links.length - 1">{{ t('storefront.next') }}</span><span v-else v-html="link.label" /></span></template></nav></template>
