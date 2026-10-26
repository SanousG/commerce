<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Metric {
    current: string | number;
    previous: string | number;
    delta: string | number;
    percent: string | null;
}

const props = defineProps<{
    metric?: Metric;
    inverse?: boolean;
}>();
const { t } = useI18n();

const tone = computed(() => {
    if (!props.metric || props.metric.percent === null) {
        return props.inverse ? 'text-white/60' : 'text-admin-neutral';
    }

    const positive = Number(props.metric.percent) >= 0;

    if (props.inverse) {
        return positive ? 'text-green-300' : 'text-red-300';
    }

    return positive ? 'text-admin-success' : 'text-admin-danger';
});

const text = computed(() => {
    const metric = props.metric;
    if (!metric) return '';
    if (metric.percent === null) return t('dashboard.noBaseline');

    const percent = Number(metric.percent);
    return `${percent >= 0 ? '▲' : '▼'} ${Math.abs(percent).toFixed(2)}%`;
});

const title = computed(() => (props.metric ? t('dashboard.previousPeriod', { value: props.metric.previous }) : ''));
</script>

<template>
    <span v-if="metric" :class="['mt-2 block text-[11px] font-medium', tone]" :title="title">{{ text }}</span>
</template>
