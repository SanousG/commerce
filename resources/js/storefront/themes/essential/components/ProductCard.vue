<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PublicProduct } from '../../../types';
import AvailabilityBadge from './AvailabilityBadge.vue';
import PriceDisplay from './PriceDisplay.vue';
defineProps<{ product: PublicProduct; locale: string }>();
const { t } = useI18n();
</script>

<template>
    <article class="group min-w-0 rounded-xl border border-[var(--sf-border)] bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-4">
        <Link :href="product.url" class="block rounded-lg focus-visible:outline-offset-4">
            <div class="relative aspect-square overflow-hidden rounded-lg bg-slate-100">
                <img v-if="product.image" :src="product.image.thumbnailUrl ?? product.image.mediumUrl" :alt="product.image.alt" :width="product.image.width" :height="product.image.height" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" />
                <div v-else class="flex h-full items-center justify-center px-4 text-center text-sm text-slate-500">{{ t('storefront.imageUnavailable') }}</div>
                <span v-if="product.price.hasDiscount" class="absolute left-2.5 top-2.5 rounded bg-[var(--sf-accent)] px-2 py-1 text-xs font-semibold text-white">{{ t('storefront.discount') }}</span>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2">
                <span class="truncate text-xs text-slate-500">{{ product.categories[0]?.name }}</span>
                <AvailabilityBadge :availability="product.availability" />
            </div>
            <h3 class="mt-1 line-clamp-2 min-h-12 text-base font-semibold leading-6 text-slate-950 group-hover:text-[var(--sf-accent)]">{{ product.name }}</h3>
            <PriceDisplay class="mt-2" :price="product.price" :locale="locale" />
        </Link>
    </article>
</template>
