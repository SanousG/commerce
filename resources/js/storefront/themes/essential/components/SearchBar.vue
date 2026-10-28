<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ action: string; initialValue?: string | null; compact?: boolean }>();
const query = ref(props.initialValue ?? '');
const { t } = useI18n();
function search() {
    router.get(props.action, query.value.trim() ? { q: query.value.trim() } : {}, { preserveState: true });
}
</script>

<template>
    <form role="search" class="relative w-full" @submit.prevent="search">
        <label class="sr-only" :for="`store-search-${compact ? 'compact' : 'full'}`">{{ t('storefront.search') }}</label>
        <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input :id="`store-search-${compact ? 'compact' : 'full'}`" v-model="query" name="q" type="search" maxlength="100" class="min-h-11 w-full rounded-lg border border-[var(--sf-border)] bg-white py-2 pl-10 pr-3 text-sm text-slate-950 placeholder:text-slate-500 focus:border-[var(--sf-accent)] focus:outline-none focus:ring-2 focus:ring-[color:var(--sf-accent)]/20" :placeholder="t('storefront.searchPlaceholder')" />
    </form>
</template>
