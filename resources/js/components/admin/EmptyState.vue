<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-44 place-items-center rounded-lg border border-dashed border-admin-border-strong bg-admin-surface px-5 py-8 text-center">
        <div class="max-w-md">
            <div class="mx-auto grid size-10 place-items-center rounded-lg border border-admin-border bg-admin-canvas text-admin-neutral">
                <slot name="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M5 5h14v14H5V5Zm4 0v14M9 9h10M9 14h10" /></svg>
                </slot>
            </div>
            <h2 class="mt-3 text-sm font-semibold text-admin-text">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-xs leading-5 text-admin-neutral">{{ description }}</p>
            <div v-if="$slots.actions" class="mt-4"><slot name="actions" /></div>
        </div>
    </div>
</template>
