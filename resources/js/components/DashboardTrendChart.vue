<script setup lang="ts">
import { computed } from 'vue';

interface TrendPoint {
    date: string;
    gross_sales: string;
    orders: number;
}

const props = defineProps<{
    points: TrendPoint[];
    currency: string;
}>();

const width = 1000;
const height = 260;
const margin = { top: 20, right: 16, bottom: 34, left: 16 };
const plotWidth = width - margin.left - margin.right;
const plotHeight = height - margin.top - margin.bottom;

const maxSales = computed(() => Math.max(1, ...props.points.map((point) => Number(point.gross_sales))));
const maxOrders = computed(() => Math.max(1, ...props.points.map((point) => point.orders)));
const step = computed(() => plotWidth / Math.max(1, props.points.length));
const barWidth = computed(() => Math.max(1, Math.min(28, step.value * 0.55)));

const gridLines = computed(() => [0, 0.25, 0.5, 0.75, 1].map((ratio) => margin.top + plotHeight * ratio));

const bars = computed(() =>
    props.points.map((point, index) => {
        const value = Number(point.gross_sales);
        const barHeight = (value / maxSales.value) * plotHeight;

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
        cy: margin.top + plotHeight - (point.orders / maxOrders.value) * plotHeight,
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
        <svg :viewBox="`0 0 ${width} ${height}`" class="h-60 w-full" role="img" aria-label="Order value and order count over time">
            <title>Order value and order count over time</title>

            <line
                v-for="line in gridLines"
                :key="`grid-${line}`"
                :x1="margin.left"
                :x2="width - margin.right"
                :y1="line"
                :y2="line"
                stroke="#17211b"
                stroke-opacity="0.1"
            />

            <rect
                v-for="bar in bars"
                :key="`bar-${bar.date}`"
                :x="bar.x"
                :y="bar.y"
                :width="barWidth"
                :height="bar.height"
                fill="#17211b"
                rx="1"
            >
                <title>{{ bar.date }}: {{ money(bar.value) }} · {{ bar.orders }} orders</title>
            </rect>

            <polyline :points="orderLine" fill="none" stroke="#047857" stroke-width="2" />
            <circle v-for="dot in orderDots" :key="`dot-${dot.date}`" :cx="dot.cx" :cy="dot.cy" r="2.5" fill="#047857">
                <title>{{ dot.date }}: {{ dot.orders }} orders</title>
            </circle>

            <text
                v-for="index in labelIndices"
                :key="`label-${points[index].date}`"
                :x="margin.left + index * step + step / 2"
                :y="height - 12"
                text-anchor="middle"
                class="fill-[#637067] font-mono"
                font-size="11"
            >
                {{ points[index].date.slice(5) }}
            </text>
        </svg>

        <div class="mt-3 flex flex-wrap gap-4 text-xs text-[#637067]">
            <span class="flex items-center gap-2"><span class="inline-block size-3 bg-[#17211b]"></span>Order value ({{ currency }})</span>
            <span class="flex items-center gap-2"><span class="inline-block h-0.5 w-4 bg-[#047857]"></span>Orders</span>
            <span class="ml-auto">Peak: {{ money(maxSales) }} · {{ maxOrders }} orders</span>
        </div>
    </div>
</template>
