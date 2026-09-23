<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div
        v-if="message"
        role="status"
        class="mb-6 border-l-4 border-emerald-700 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
    >
        {{ message }}
    </div>
</template>
