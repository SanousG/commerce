<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Refund { id: string; order_id: string; order_number: string | null; payment_id: string; payment_method: string | null; payment_provider: string | null; return_id: string | null; status: string; amount: string; currency: string; reason: string; external_reference: string | null; completed_at: string | null; created_at: string | null }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: Refund[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }
interface RefundFilters { search: string; status: string; order_id: string }

const props = defineProps<{ refunds: Paginator; filters: RefundFilters; statuses: string[] }>();
const { t, te, locale } = useI18n();
const filters = ref<RefundFilters>({ ...props.filters });
const filtering = ref(false);
const hasFilters = computed(() => Object.values(props.filters).some(Boolean));

watch(() => props.filters, (value) => { filters.value = { ...value }; }, { deep: true });
const query = () => Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== ''));
const applyFilters = () => router.get('/admin/refunds', query(), { preserveState: true, replace: true, onStart: () => { filtering.value = true; }, onFinish: () => { filtering.value = false; } });
const clearFilters = () => { filters.value = { search: '', status: '', order_id: '' }; applyFilters(); };
const clearOrder = () => { filters.value.order_id = ''; applyFilters(); };
const enumLabel = (group: string, value: string | null) => value && te(`payments.${group}.${value}`) ? t(`payments.${group}.${value}`) : (value ?? t('common.notAvailable'));
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : t('common.notAvailable');
const formatDecimal = (value: string) => { const [integer, decimal = ''] = value.split('.'); return `${BigInt(integer).toLocaleString(locale.value)}${decimal ? `${locale.value.startsWith('es') ? ',' : '.'}${decimal}` : ''}`; };
const money = (refund: Refund) => `${refund.currency} ${formatDecimal(refund.amount)}`;
const shortId = (value: string) => value.slice(0, 8);
</script>

<template>
    <Head :title="t('refunds.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('refunds.title') }]" />
            <PageHeader :title="t('refunds.title')" :eyebrow="t('common.currentStore')" :description="t('refunds.description')"><template #actions><Link href="/admin/payments" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold sm:w-auto">{{ t('refunds.openPayments') }}</Link></template></PageHeader>

            <form class="admin-card grid gap-3 p-3 sm:grid-cols-2 sm:p-4 lg:grid-cols-[minmax(0,1fr)_14rem_auto]" role="search" @submit.prevent="applyFilters">
                <label class="grid min-w-0 gap-1.5" for="refund-search"><span class="admin-label">{{ t('refunds.search') }}</span><input id="refund-search" v-model="filters.search" type="search" maxlength="120" autocomplete="off" :placeholder="t('refunds.searchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label>
                <label class="grid gap-1.5" for="refund-status"><span class="admin-label">{{ t('common.status') }}</span><select id="refund-status" v-model="filters.status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('refunds.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select></label>
                <div class="flex items-end gap-2"><button type="submit" :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60">{{ filtering ? t('common.searching') : t('refunds.filter') }}</button><button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold" @click="clearFilters">{{ t('common.clear') }}</button></div>
            </form>

            <div v-if="filters.order_id" class="flex flex-wrap items-center gap-2 rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2 text-xs"><span>{{ t('refunds.orderFilter', { id: shortId(filters.order_id) }) }}</span><button type="button" class="admin-link font-semibold" @click="clearOrder">{{ t('refunds.showAllOrders') }}</button></div>

            <template v-if="refunds.data.length">
                <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[20%] p-3">{{ t('refunds.refund') }}</th><th class="w-[14%] p-3">{{ t('refunds.order') }}</th><th class="w-[20%] p-3">{{ t('refunds.payment') }}</th><th class="w-[14%] p-3 text-right">{{ t('refunds.amount') }}</th><th class="w-[14%] p-3">{{ t('common.status') }}</th><th class="w-[18%] p-3">{{ t('refunds.created') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="refund in refunds.data" :key="refund.id"><td class="p-3"><Link :href="`/admin/refunds/${refund.id}`" class="admin-link font-mono font-semibold">{{ shortId(refund.id) }}</Link><p class="mt-1 line-clamp-2 text-admin-neutral" :title="refund.reason">{{ refund.reason }}</p></td><td class="p-3"><Link :href="`/admin/orders/${refund.order_id}`" class="admin-link font-mono font-semibold">{{ refund.order_number ?? shortId(refund.order_id) }}</Link></td><td class="p-3"><Link :href="`/admin/payments/${refund.payment_id}`" class="admin-link font-medium">{{ enumLabel('methods', refund.payment_method) }}</Link><p class="mt-0.5 text-admin-neutral">{{ enumLabel('providers', refund.payment_provider) }}</p></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ money(refund) }}</td><td class="p-3"><StatusBadge :status="refund.status" /></td><td class="p-3 text-admin-neutral">{{ formatDate(refund.created_at) }}</td></tr></tbody></table></div>
                <div class="grid gap-2.5 md:hidden"><article v-for="refund in refunds.data" :key="`mobile-${refund.id}`" class="admin-card min-w-0 p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="admin-label">{{ t('refunds.refundNumber', { id: shortId(refund.id) }) }}</p><Link :href="`/admin/refunds/${refund.id}`" class="admin-link mt-1 block font-mono text-base font-semibold tabular-nums">{{ money(refund) }}</Link></div><StatusBadge :status="refund.status" /></div><p class="mt-3 break-words text-sm">{{ refund.reason }}</p><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div class="min-w-0"><dt class="admin-label">{{ t('refunds.order') }}</dt><dd class="mt-1 truncate font-mono text-xs font-semibold"><Link :href="`/admin/orders/${refund.order_id}`" class="admin-link">{{ refund.order_number ?? shortId(refund.order_id) }}</Link></dd></div><div class="min-w-0"><dt class="admin-label">{{ t('refunds.payment') }}</dt><dd class="mt-1 truncate text-xs"><Link :href="`/admin/payments/${refund.payment_id}`" class="admin-link">{{ enumLabel('methods', refund.payment_method) }}</Link></dd><dd class="truncate text-[11px] text-admin-neutral">{{ enumLabel('providers', refund.payment_provider) }}</dd></div></dl><div class="mt-3 flex items-center justify-between gap-3"><time class="text-[11px] text-admin-neutral">{{ formatDate(refund.created_at) }}</time><Link :href="`/admin/refunds/${refund.id}`" class="admin-link text-xs">{{ t('refunds.openRefund') }}</Link></div></article></div>
            </template>
            <EmptyState v-else :title="hasFilters ? t('refunds.noMatch') : t('refunds.empty')" :description="hasFilters ? t('refunds.noMatchHelp') : t('refunds.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('refunds.clearFilters') }}</button></template></EmptyState>
            <Pagination :links="refunds.links" :from="refunds.from" :to="refunds.to" :total="refunds.total" preserve-state />
        </section>
    </AdminLayout>
</template>
