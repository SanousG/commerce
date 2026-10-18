<script setup lang="ts">
defineProps<{ src: string; alt?: string | null; size?: 'sm' | 'md' | 'lg' }>();
</script>

<template>
    <img :src="src" :alt="alt ?? ''" loading="lazy" class="rounded-sm border border-[#17211b]/15 bg-[#ece8de] object-cover" :class="size === 'sm' ? 'size-14' : size === 'lg' ? 'size-32' : 'size-20'" />
</template>
