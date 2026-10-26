<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{ items: Array<{ label: string; href?: string }> }>();

const { t } = useI18n();
</script>

<template>
    <nav :aria-label="t('common.breadcrumb')" class="flex flex-wrap items-center gap-1.5 text-xs text-admin-neutral">
        <template v-for="(item, index) in items" :key="`${item.label}-${index}`">
            <svg v-if="index" viewBox="0 0 20 20" fill="currentColor" class="size-3 text-slate-300" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 4.2a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L11.92 10 7.2 5.26a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
            <Link v-if="item.href" :href="item.href" class="hover:text-admin-secondary">{{ item.label }}</Link>
            <span v-else class="font-medium text-admin-text-secondary" aria-current="page">{{ item.label }}</span>
        </template>
    </nav>
</template>
