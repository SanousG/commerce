<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicStore } from '../../../types';
import SearchBar from './SearchBar.vue';
defineProps<{ store: PublicStore }>();
const open = ref(false);
const { t } = useI18n();
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-[var(--sf-border)] bg-white/95 shadow-sm backdrop-blur">
        <div class="mx-auto flex min-h-16 max-w-[1440px] items-center gap-3 px-4 sm:min-h-20 sm:px-6 lg:px-8">
            <button type="button" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg md:hidden" :aria-expanded="open" :aria-label="t('storefront.toggleNavigation')" @click="open = !open"><span aria-hidden="true">☰</span></button>
            <Link :href="`/tienda/${store.publicHandle}`" class="flex min-w-0 shrink-0 items-center gap-2 rounded-lg">
                <img v-if="store.branding.logoUrl" :src="store.branding.logoUrl" alt="" class="h-9 w-9 rounded-lg object-contain" />
                <span v-else class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--sf-primary)] font-bold text-white" aria-hidden="true">{{ store.name.charAt(0) }}</span>
                <span class="max-w-40 truncate text-base font-black tracking-wide text-slate-950 sm:max-w-64 sm:text-lg">{{ store.name }}</span>
            </Link>
            <nav class="ml-4 hidden items-center gap-6 md:flex" :aria-label="t('storefront.mainNavigation')">
                <Link :href="`/tienda/${store.publicHandle}`" class="text-sm font-semibold text-slate-700 hover:text-[var(--sf-accent)]">{{ t('storefront.home') }}</Link>
                <Link :href="`/tienda/${store.publicHandle}/productos`" class="text-sm font-semibold text-slate-700 hover:text-[var(--sf-accent)]">{{ t('storefront.catalog') }}</Link>
            </nav>
            <div class="ml-auto hidden w-full max-w-xs sm:block"><SearchBar :action="`/tienda/${store.publicHandle}/productos`" compact /></div>
        </div>
        <div v-if="open" class="border-t border-[var(--sf-border)] bg-white px-4 py-4 md:hidden">
            <SearchBar :action="`/tienda/${store.publicHandle}/productos`" compact />
            <nav class="mt-3 grid" :aria-label="t('storefront.mainNavigation')"><Link :href="`/tienda/${store.publicHandle}`" class="flex min-h-11 items-center rounded-lg px-3 font-semibold">{{ t('storefront.home') }}</Link><Link :href="`/tienda/${store.publicHandle}/productos`" class="flex min-h-11 items-center rounded-lg px-3 font-semibold">{{ t('storefront.catalog') }}</Link></nav>
        </div>
    </header>
</template>
