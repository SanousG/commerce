<script setup lang="ts">
defineProps<{ src: string; alt?: string | null; size?: 'sm' | 'md' | 'lg'; fluid?: boolean }>();
</script>

<template>
    <img
        :src="src"
        :alt="alt ?? ''"
        loading="lazy"
        class="rounded-md border border-admin-border bg-admin-canvas object-cover"
        :class="fluid ? 'aspect-[4/3] h-auto w-full' : size === 'sm' ? 'size-14' : size === 'lg' ? 'size-32' : 'size-20'"
    />
</template>
