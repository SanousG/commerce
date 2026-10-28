<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { PublicAvailability } from '../../../types';
defineProps<{ availability: PublicAvailability }>();
const { t } = useI18n();
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold" :class="availability.state === 'available' ? 'text-emerald-700' : 'text-slate-500'">
        <span class="h-2 w-2 rounded-full" :class="availability.state === 'available' ? 'bg-emerald-600' : 'bg-slate-400'" aria-hidden="true" />
        {{ t(`storefront.availability.${availability.state}`) }}
    </span>
</template>
