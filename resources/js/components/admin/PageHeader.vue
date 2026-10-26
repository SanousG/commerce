<script setup lang="ts">
defineProps<{
    title: string;
    eyebrow?: string;
    description?: string;
}>();
</script>

<template>
    <header class="flex flex-col gap-5 border-b border-admin-border pb-6 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="admin-label text-admin-secondary">{{ eyebrow }}</p>
            <h1 class="mt-1 text-[1.875rem] font-semibold tracking-[-0.03em] text-admin-primary">{{ title }}</h1>
            <p v-if="description" class="mt-1.5 max-w-3xl text-sm text-admin-neutral">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="shrink-0"><slot name="actions" /></div>
    </header>
</template>
