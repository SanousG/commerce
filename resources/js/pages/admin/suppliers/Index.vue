<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Supplier { id: string; name: string; code: string; contact_name: string | null; email: string | null; phone: string | null; status: string; products_count: number }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: Supplier[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }

const props = defineProps<{ suppliers: Paginator; filters: { search: string; status: string }; statuses: string[] }>();
const { t, te } = useI18n();
const page = usePage<PageProps>();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const filtering = ref(false);
const canCreate = computed(() => page.props.currentTenantMembership?.permissions.includes('suppliers.create') ?? false);
const hasFilters = computed(() => Boolean(props.filters.search || props.filters.status));
watch(() => props.filters, (value) => { search.value = value.search ?? ''; status.value = value.status ?? ''; }, { deep: true });
const applyFilters = () => router.get('/admin/suppliers', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true, onStart: () => { filtering.value = true; }, onFinish: () => { filtering.value = false; } });
const clearFilters = () => { search.value = ''; status.value = ''; applyFilters(); };
</script>

<template>
    <Head :title="t('procurement.suppliers.title')" />
    <AdminLayout><section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
        <Breadcrumbs :items="[{ label: t('nav.procurement') }, { label: t('procurement.suppliers.title') }]" />
        <PageHeader :title="t('procurement.suppliers.title')" :eyebrow="t('common.allCompany')" :description="t('procurement.suppliers.description')"><template #actions><Link v-if="canCreate" href="/admin/suppliers/create" class="inline-flex min-h-10 w-full items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white sm:w-auto">{{ t('procurement.suppliers.create') }}</Link></template></PageHeader>
        <form class="admin-card grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:p-4" role="search" @submit.prevent="applyFilters">
            <label class="grid gap-1.5" for="supplier-search"><span class="admin-label">{{ t('procurement.suppliers.search') }}</span><input id="supplier-search" v-model="search" type="search" maxlength="160" autocomplete="off" :placeholder="t('procurement.suppliers.searchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label>
            <label class="grid gap-1.5" for="supplier-status"><span class="admin-label">{{ t('common.status') }}</span><select id="supplier-status" v-model="status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('procurement.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select></label>
            <div class="flex items-end gap-2"><button :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60">{{ filtering ? t('common.searching') : t('procurement.filter') }}</button><button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold" @click="clearFilters">{{ t('common.clear') }}</button></div>
        </form>
        <template v-if="suppliers.data.length">
            <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[25%] p-3">{{ t('procurement.supplier') }}</th><th class="w-[32%] p-3">{{ t('procurement.contact') }}</th><th class="w-[15%] p-3">{{ t('common.status') }}</th><th class="w-[13%] p-3 text-right">{{ t('procurement.products') }}</th><th class="w-[15%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="supplier in suppliers.data" :key="supplier.id"><td class="p-3"><p class="truncate font-semibold">{{ supplier.name }}</p><p class="mt-0.5 truncate font-mono text-[11px] text-admin-neutral">{{ supplier.code }}</p></td><td class="p-3"><p class="truncate">{{ supplier.contact_name ?? t('procurement.noContact') }}</p><p class="mt-0.5 truncate text-admin-neutral">{{ supplier.email ?? supplier.phone ?? t('procurement.noDetails') }}</p></td><td class="p-3"><StatusBadge :status="supplier.status" /></td><td class="p-3 text-right font-mono tabular-nums">{{ supplier.products_count }}</td><td class="p-3 text-right"><Link :href="`/admin/suppliers/${supplier.id}/edit`" class="admin-link">{{ t('procurement.openSupplier') }}</Link></td></tr></tbody></table></div>
            <div class="grid gap-2.5 md:hidden"><article v-for="supplier in suppliers.data" :key="`mobile-${supplier.id}`" class="admin-card min-w-0 p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="break-words font-semibold">{{ supplier.name }}</h2><p class="mt-0.5 font-mono text-xs text-admin-neutral">{{ supplier.code }}</p></div><StatusBadge :status="supplier.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-3 text-xs"><div class="min-w-0"><dt class="admin-label">{{ t('procurement.contact') }}</dt><dd class="mt-1 truncate">{{ supplier.contact_name ?? t('procurement.noContact') }}</dd><dd class="truncate text-admin-neutral">{{ supplier.email ?? supplier.phone ?? t('procurement.noDetails') }}</dd></div><div><dt class="admin-label">{{ t('procurement.products') }}</dt><dd class="mt-1 font-mono font-semibold tabular-nums">{{ supplier.products_count }}</dd></div></dl><Link :href="`/admin/suppliers/${supplier.id}/edit`" class="admin-link mt-3 inline-flex min-h-9 items-center text-sm">{{ t('procurement.openSupplier') }}</Link></article></div>
        </template>
        <EmptyState v-else :title="hasFilters ? t('procurement.suppliers.noMatch') : t('procurement.suppliers.empty')" :description="hasFilters ? t('procurement.suppliers.noMatchHelp') : t('procurement.suppliers.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('procurement.clearFilters') }}</button></template></EmptyState>
        <Pagination :links="suppliers.links" :from="suppliers.from" :to="suppliers.to" :total="suppliers.total" preserve-state />
    </section></AdminLayout>
</template>
