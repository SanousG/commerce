<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PublicStore } from '../../../types';
defineProps<{ store: PublicStore }>();
const { t } = useI18n();
</script>

<template>
    <footer class="mt-16 border-t border-[var(--sf-border)] bg-white">
        <div class="mx-auto grid max-w-[1440px] gap-8 px-4 py-10 sm:px-6 md:grid-cols-3 lg:px-8">
            <div><p class="font-bold text-slate-950">{{ store.name }}</p><p v-if="store.description" class="mt-2 max-w-sm text-sm leading-6 text-slate-600">{{ store.description }}</p></div>
            <div><h2 class="text-sm font-semibold text-slate-950">{{ t('storefront.navigation') }}</h2><div class="mt-3 grid gap-2 text-sm text-slate-600"><Link :href="`/tienda/${store.publicHandle}`">{{ t('storefront.home') }}</Link><Link :href="`/tienda/${store.publicHandle}/productos`">{{ t('storefront.catalog') }}</Link></div></div>
            <div v-if="store.contact.email || store.contact.phone || store.contact.address.length"><h2 class="text-sm font-semibold text-slate-950">{{ t('storefront.contact') }}</h2><address class="mt-3 grid gap-2 text-sm not-italic text-slate-600"><a v-if="store.contact.email" :href="`mailto:${store.contact.email}`">{{ store.contact.email }}</a><a v-if="store.contact.phone" :href="`tel:${store.contact.phone}`">{{ store.contact.phone }}</a><span v-if="store.contact.address.length">{{ store.contact.address.join(', ') }}</span></address></div>
        </div>
        <div class="border-t border-[var(--sf-border)] px-4 py-5 text-center text-xs text-slate-500">© {{ new Date().getFullYear() }} {{ store.name }}</div>
    </footer>
</template>
