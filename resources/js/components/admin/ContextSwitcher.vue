<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    href: string;
    label: string;
    value: string;
    icon: 'tenant' | 'store';
}>();
</script>

<template>
    <Link :href="href" class="group flex min-w-0 items-center gap-2 rounded-md border border-admin-border bg-white px-2.5 py-2 transition hover:border-admin-border-strong hover:bg-slate-50">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4 shrink-0 text-admin-neutral" aria-hidden="true">
            <path v-if="icon === 'tenant'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 20h16M6 20V8l6-4 6 4v12M9 12h6m-6 4h6" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 10h16M5 10l1-5h12l1 5v10H5V10Zm4 10v-6h6v6" />
        </svg>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-xs font-semibold text-admin-text">{{ value }}</span>
            <span class="block text-[9px] font-semibold tracking-[0.08em] text-admin-neutral uppercase">{{ label }}</span>
        </span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-3.5 shrink-0 text-slate-400 transition group-hover:text-admin-secondary" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" /></svg>
    </Link>
</template>
