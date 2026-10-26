<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ status: string }>();
const { t, te } = useI18n();
const displayStatus = computed(() => te(`status.${props.status}`) ? t(`status.${props.status}`) : t('common.unknownStatus'));

const tone = computed(() => {
    const status = props.status.toUpperCase();
    if (['ACTIVE', 'COMPLETED', 'PAID', 'FULFILLED', 'ACCEPTED', 'SUCCEEDED'].includes(status)) return 'border-admin-success-border bg-admin-success-bg text-admin-success';
    if (['PENDING', 'PROCESSING', 'PARTIALLY_PAID', 'PARTIALLY_FULFILLED', 'SUBMITTED', 'SUBMISSION_PENDING', 'GENERATED', 'DRAFT'].includes(status)) return 'border-admin-warning-border bg-admin-warning-bg text-amber-700';
    if (['FAILED', 'REJECTED', 'CANCELLED', 'UNPAID', 'UNKNOWN'].includes(status)) return 'border-admin-danger-border bg-admin-danger-bg text-admin-danger';
    return 'border-admin-border bg-slate-50 text-admin-text-secondary';
});
</script>

<template>
    <span :class="tone" class="inline-flex max-w-full rounded border px-1.5 py-0.5 font-mono text-[10px] font-medium tracking-[0.02em]">{{ displayStatus }}</span>
</template>
