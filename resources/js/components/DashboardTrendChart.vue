<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface TrendPoint {
    date: string;
    gross_sales: string;
    orders: number;
}

const props = defineProps<{
    points: TrendPoint[];
    currency: string;
}>();
const { t } = useI18n();

const width = 1000;
const height = 260;
const margin = { top: 20, right: 16, bottom: 34, left: 16 };
const plotWidth = width - margin.left - margin.right;
const plotHeight = height - margin.top - margin.bottom;

const peakSales = computed(() => Math.max(0, ...props.points.map((point) => Number(point.gross_sales))));
const peakOrders = computed(() => Math.max(0, ...props.points.map((point) => point.orders)));
const scaleSales = computed(() => Math.max(1, peakSales.value));
const scaleOrders = computed(() => Math.max(1, peakOrders.value));
const hasActivity = computed(() => props.points.some((point) => Number(point.gross_sales) !== 0 || point.orders !== 0));
const step = computed(() => plotWidth / Math.max(1, props.points.length));
const barWidth = computed(() => Math.max(1, Math.min(28, step.value * 0.55)));

const gridLines = computed(() => [0, 0.25, 0.5, 0.75, 1].map((ratio) => margin.top + plotHeight * ratio));

const bars = computed(() =>
    props.points.map((point, index) => {
        const value = Number(point.gross_sales);
        const barHeight = (value / scaleSales.value) * plotHeight;

        return {
            ...point,
            value,
            x: margin.left + index * step.value + (step.value - barWidth.value) / 2,
            y: margin.top + plotHeight - barHeight,
            height: barHeight,
        };
    }),
);

const orderDots = computed(() =>
    props.points.map((point, index) => ({
        date: point.date,
        orders: point.orders,
        cx: margin.left + index * step.value + step.value / 2,
        cy: margin.top + plotHeight - (point.orders / scaleOrders.value) * plotHeight,
    })),
);

const orderLine = computed(() => orderDots.value.map((dot) => `${dot.cx},${dot.cy}`).join(' '));

const labelIndices = computed(() => {
    const total = props.points.length;
    if (total === 0) return [];
    if (total <= 8) return props.points.map((_, index) => index);

    const indices: number[] = [];
    for (let index = 0; index < 8; index += 1) {
        indices.push(Math.round((index * (total - 1)) / 7));
    }

    return [...new Set(indices)];
});

const money = (value: number) => {
    const [whole = '0', fraction = '00'] = value.toFixed(2).split('.');
    return `${props.currency} ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction}`;
};
</script>

<template>
    <div>
        <div v-if="!hasActivity" class="grid min-h-36 place-items-center rounded-md border border-dashed border-admin-border-strong bg-admin-canvas px-4 py-6 text-center" role="status" :aria-label="t('dashboard.noSales')">
            <div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mx-auto size-6 text-admin-neutral" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 19V9m5 10V5m5 14v-7m5 7V8" /></svg>
                <p class="mt-2 text-sm font-semibold text-admin-text-secondary">{{ t('dashboard.noSales') }}</p>
                <p class="mt-1 text-xs text-admin-neutral">{{ t('dashboard.noSalesHelp') }}</p>
            </div>
        </div>

        <template v-else>
        <svg :viewBox="`0 0 ${width} ${height}`" class="h-48 w-full sm:h-60" role="img" :aria-label="t('dashboard.chartLabel')">
            <title>{{ t('dashboard.chartLabel') }}</title>

            <line
                v-for="line in gridLines"
                :key="`grid-${line}`"
                :x1="margin.left"
                :x2="width - margin.right"
                :y1="line"
                :y2="line"
                stroke="#CBD5E1"
                stroke-opacity="0.1"
            />

            <rect
                v-for="bar in bars"
                :key="`bar-${bar.date}`"
                :x="bar.x"
                :y="bar.y"
                :width="barWidth"
                :height="bar.height"
                fill="#0F172A"
                rx="2"
            >
                <title>{{ bar.date }}: {{ money(bar.value) }} · {{ t('dashboard.ordersCount', { count: bar.orders }) }}</title>
            </rect>

            <polyline :points="orderLine" fill="none" stroke="#2563EB" stroke-width="2" />
            <circle v-for="dot in orderDots" :key="`dot-${dot.date}`" :cx="dot.cx" :cy="dot.cy" r="2.5" fill="#2563EB">
                <title>{{ dot.date }}: {{ t('dashboard.ordersCount', { count: dot.orders }) }}</title>
            </circle>

            <text
                v-for="index in labelIndices"
                :key="`label-${points[index].date}`"
                :x="margin.left + index * step + step / 2"
                :y="height - 12"
                text-anchor="middle"
                class="fill-admin-neutral font-mono"
                font-size="11"
            >
                {{ points[index].date.slice(5) }}
            </text>
        </svg>

        <div class="mt-3 flex flex-wrap gap-4 text-xs text-admin-neutral">
            <span class="flex items-center gap-2"><span class="inline-block size-3 rounded-sm bg-admin-primary"></span>{{ t('dashboard.orderValue', { currency }) }}</span>
            <span class="flex items-center gap-2"><span class="inline-block h-0.5 w-4 bg-admin-secondary"></span>{{ t('nav.orders') }}</span>
            <span class="ml-auto">{{ t('dashboard.peak', { sales: money(peakSales), orders: peakOrders }) }}</span>
        </div>
        </template>
    </div>
</template>
