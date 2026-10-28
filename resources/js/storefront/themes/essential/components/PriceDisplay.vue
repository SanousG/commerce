<script setup lang="ts">
import { computed } from 'vue';
import type { PublicPrice } from '../../../types';
const props = defineProps<{ price: PublicPrice; locale: string; large?: boolean }>();
const format = (amount: string) => new Intl.NumberFormat(props.locale, { style: 'currency', currency: props.price.currency }).format(Number(amount));
const finalPrice = computed(() => format(props.price.finalAmount));
const basePrice = computed(() => format(props.price.baseAmount));
</script>

<template>
    <div class="flex flex-wrap items-baseline gap-2 tabular-nums">
        <span :class="large ? 'text-3xl' : 'text-lg'" class="font-bold text-slate-950">{{ finalPrice }}</span>
        <span v-if="price.hasDiscount" class="text-sm text-slate-500 line-through">{{ basePrice }}</span>
        <span v-if="price.hasDiscount && price.promotionName" class="sr-only">{{ price.promotionName }}</span>
    </div>
</template>
