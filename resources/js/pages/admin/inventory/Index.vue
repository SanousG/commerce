<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface VariantRow { product_name: string; variant_id: string; sku: string; on_hand: number; reserved: number; available: number; locations_with_stock: number }
interface PageLink { url: string | null; label: string; active: boolean }
const props = defineProps<{ variants: { data: VariantRow[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }; filters: { search: string; can_adjust: boolean } }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canViewCost = computed(() => permissions.value.includes('inventory.cost.view'));
const search = ref(props.filters.search ?? '');
const searching = ref(false);
const hasSearch = computed(() => props.filters.search !== '');
const { t } = useI18n();
const statusMessage = computed(() => page.props.status === 'Inventory transfer completed.' ? t('inventory.notices.transferCompleted') : undefined);
watch(() => props.filters.search, (value) => { search.value = value; });
const visit = (value: string) => router.get('/admin/inventory', value.trim() ? { search: value.trim() } : {}, { preserveState: true, replace: true, onStart: () => { searching.value = true; }, onFinish: () => { searching.value = false; } });
const submitSearch = () => visit(search.value);
const clearSearch = () => { search.value = ''; visit(''); };
</script>

<template>
    <Head :title="t('inventory.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.operations') }, { label: t('inventory.title') }]" />
            <PageHeader :title="t('inventory.title')" :eyebrow="t('common.allCompany')" :description="t('inventory.description')"><template #actions><div class="flex flex-col gap-2 sm:flex-row"><Link href="/admin/inventory/locations" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50">{{ t('inventory.locations.title') }}</Link><Link v-if="canViewCost" href="/admin/inventory/valuation" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50">{{ t('inventory.valuation.title') }}</Link><Link v-if="filters.can_adjust" href="/admin/inventory/transfers/create" class="inline-flex min-h-9 items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover">{{ t('inventory.newTransfer') }}</Link></div></template></PageHeader>
            <StatusNotice :message="statusMessage" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />
            <form class="admin-card flex flex-col gap-3 p-3 sm:flex-row sm:items-end sm:p-4" role="search" @submit.prevent="submitSearch"><label for="inventory-search" class="grid min-w-0 flex-1 gap-1.5"><span class="admin-label">{{ t('inventory.search') }}</span><input id="inventory-search" v-model="search" type="search" autocomplete="off" :placeholder="t('inventory.searchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label><div class="flex gap-2"><button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('common.search') }}</button><button v-if="hasSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary sm:flex-none" @click="clearSearch">{{ t('common.clear') }}</button></div></form>
            <template v-if="variants.data.length"><div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[32%] p-3">{{ t('inventory.productVariant') }}</th><th class="w-[14%] p-3 text-right">{{ t('inventory.onHand') }}</th><th class="w-[14%] p-3 text-right">{{ t('inventory.reserved') }}</th><th class="w-[14%] p-3 text-right">{{ t('inventory.available') }}</th><th class="w-[14%] p-3 text-right">{{ t('inventory.stockLocations') }}</th><th class="w-[12%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="variant in variants.data" :key="variant.variant_id"><td class="p-3"><p class="truncate text-sm font-semibold text-admin-text">{{ variant.product_name }}</p><p class="mt-0.5 font-mono text-[10px] text-admin-neutral">{{ variant.sku }}</p></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ variant.on_hand }}</td><td class="p-3 text-right font-mono tabular-nums">{{ variant.reserved }}</td><td class="p-3 text-right font-mono font-semibold tabular-nums" :class="variant.available <= 0 ? 'text-admin-danger' : 'text-admin-success'">{{ variant.available }}</td><td class="p-3 text-right font-mono tabular-nums">{{ variant.locations_with_stock }}</td><td class="p-3 text-right"><Link :href="`/admin/inventory/variants/${variant.variant_id}`" class="admin-link">{{ t('common.view') }}</Link></td></tr></tbody></table></div>
                <div class="grid gap-2.5 md:hidden"><article v-for="variant in variants.data" :key="`mobile-${variant.variant_id}`" class="admin-card p-3.5"><div><h2 class="text-sm font-semibold text-admin-text">{{ variant.product_name }}</h2><p class="mt-0.5 font-mono text-[10px] text-admin-neutral">{{ variant.sku }}</p></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('inventory.onHand') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ variant.on_hand }}</dd></div><div><dt class="admin-label">{{ t('inventory.reserved') }}</dt><dd class="mt-1 font-mono text-xs">{{ variant.reserved }}</dd></div><div><dt class="admin-label">{{ t('inventory.available') }}</dt><dd class="mt-1 font-mono text-xs font-semibold" :class="variant.available <= 0 ? 'text-admin-danger' : 'text-admin-success'">{{ variant.available }}</dd></div><div><dt class="admin-label">{{ t('inventory.stockLocations') }}</dt><dd class="mt-1 font-mono text-xs">{{ variant.locations_with_stock }}</dd></div></dl><div class="mt-3 text-right"><Link :href="`/admin/inventory/variants/${variant.variant_id}`" class="admin-link text-xs">{{ t('inventory.openVariant') }}</Link></div></article></div></template>
            <EmptyState v-else :title="hasSearch ? t('inventory.noMatch') : t('inventory.empty')" :description="hasSearch ? t('inventory.noMatchHelp') : t('inventory.emptyHelp')"><template v-if="hasSearch" #actions><button type="button" class="admin-link text-sm" @click="clearSearch">{{ t('inventory.clearSearch') }}</button></template></EmptyState>
            <Pagination :links="variants.links" :from="variants.from" :to="variants.to" :total="variants.total" />
        </section>
    </AdminLayout>
</template>
