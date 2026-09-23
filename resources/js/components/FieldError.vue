<script setup lang="ts">
defineProps<{
    id?: string;
    message?: string;
}>();
</script>

<template>
    <p v-if="message" :id="id" role="alert" class="mt-2 text-sm font-medium text-red-700">
        {{ message }}
    </p>
</template>
