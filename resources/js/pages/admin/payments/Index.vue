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

interface Payment { id: string; order_id: string; order_number: string; method: string; provider: string; status: string; currency: string; amount: string; captured_amount: string; external_reference: string | null; created_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: Payment[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }
interface PaymentFilters { search: string; status: string; order_id: string }

const props = defineProps<{ payments: Paginator; filters: PaymentFilters; statuses: string[] }>();
const { t, te, locale } = useI18n();
const filters = ref<PaymentFilters>({ ...props.filters });
const filtering = ref(false);
const hasFilters = computed(() => Object.values(props.filters).some(Boolean));

watch(() => props.filters, (value) => { filters.value = { ...value }; }, { deep: true });
const query = () => Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== ''));
const applyFilters = () => router.get('/admin/payments', query(), { preserveState: true, replace: true, onStart: () => { filtering.value = true; }, onFinish: () => { filtering.value = false; } });
const clearFilters = () => { filters.value = { search: '', status: '', order_id: '' }; applyFilters(); };
const clearOrder = () => { filters.value.order_id = ''; applyFilters(); };
const enumLabel = (group: string, value: string) => te(`payments.${group}.${value}`) ? t(`payments.${group}.${value}`) : value;
const formatDate = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const formatDecimal = (value: string) => { const [integer, decimal = ''] = value.split('.'); return `${BigInt(integer).toLocaleString(locale.value)}${decimal ? `${locale.value.startsWith('es') ? ',' : '.'}${decimal}` : ''}`; };
const money = (payment: Payment, amount: string) => `${payment.currency} ${formatDecimal(amount)}`;
</script>

<template>
    <Head :title="t('payments.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('payments.title') }]" />
            <PageHeader :title="t('payments.title')" :eyebrow="t('common.currentStore')" :description="t('payments.description')" />

            <form class="admin-card grid gap-3 p-3 sm:grid-cols-2 sm:p-4 lg:grid-cols-[minmax(0,1fr)_14rem_auto]" role="search" @submit.prevent="applyFilters">
                <label class="grid min-w-0 gap-1.5" for="payment-search"><span class="admin-label">{{ t('payments.search') }}</span><input id="payment-search" v-model="filters.search" type="search" autocomplete="off" :placeholder="t('payments.searchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label>
                <label class="grid gap-1.5" for="payment-status"><span class="admin-label">{{ t('common.status') }}</span><select id="payment-status" v-model="filters.status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('payments.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select></label>
                <div class="flex items-end gap-2"><button type="submit" :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60">{{ filtering ? t('common.searching') : t('payments.filter') }}</button><button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold" @click="clearFilters">{{ t('common.clear') }}</button></div>
            </form>

            <div v-if="filters.order_id" class="flex flex-wrap items-center gap-2 rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2 text-xs"><span>{{ t('payments.orderFilter', { id: filters.order_id.slice(0, 8) }) }}</span><button type="button" class="admin-link font-semibold" @click="clearOrder">{{ t('payments.showAllOrders') }}</button></div>

            <template v-if="payments.data.length">
                <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[15%] p-3">{{ t('payments.order') }}</th><th class="w-[15%] p-3">{{ t('payments.channel') }}</th><th class="w-[12%] p-3">{{ t('common.status') }}</th><th class="w-[14%] p-3 text-right">{{ t('payments.amount') }}</th><th class="w-[14%] p-3 text-right">{{ t('payments.captured') }}</th><th class="w-[14%] p-3">{{ t('payments.reference') }}</th><th class="w-[16%] p-3">{{ t('payments.created') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="payment in payments.data" :key="payment.id"><td class="p-3"><Link :href="`/admin/payments/${payment.id}`" class="admin-link font-mono font-semibold">{{ payment.order_number }}</Link></td><td class="p-3"><p class="font-medium">{{ enumLabel('methods', payment.method) }}</p><p class="text-admin-neutral">{{ enumLabel('providers', payment.provider) }}</p></td><td class="p-3"><StatusBadge :status="payment.status" /></td><td class="p-3 text-right font-mono tabular-nums">{{ money(payment, payment.amount) }}</td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ money(payment, payment.captured_amount) }}</td><td class="truncate p-3 font-mono" :title="payment.external_reference ?? undefined">{{ payment.external_reference ?? t('common.none') }}</td><td class="p-3 text-admin-neutral">{{ formatDate(payment.created_at) }}</td></tr></tbody></table></div>
                <div class="grid gap-2.5 md:hidden"><article v-for="payment in payments.data" :key="`mobile-${payment.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="admin-label">{{ t('payments.order') }}</p><Link :href="`/admin/payments/${payment.id}`" class="admin-link mt-1 block truncate font-mono text-sm font-semibold">{{ payment.order_number }}</Link><p class="mt-1 text-xs text-admin-neutral">{{ enumLabel('methods', payment.method) }} · {{ enumLabel('providers', payment.provider) }}</p></div><StatusBadge :status="payment.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('payments.amount') }}</dt><dd class="mt-1 font-mono text-xs">{{ money(payment, payment.amount) }}</dd></div><div><dt class="admin-label">{{ t('payments.captured') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ money(payment, payment.captured_amount) }}</dd></div><div><dt class="admin-label">{{ t('payments.reference') }}</dt><dd class="mt-1 truncate font-mono text-xs">{{ payment.external_reference ?? t('common.none') }}</dd></div><div><dt class="admin-label">{{ t('payments.created') }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(payment.created_at) }}</dd></div></dl><div class="mt-3 text-right"><Link :href="`/admin/payments/${payment.id}`" class="admin-link text-xs">{{ t('payments.openPayment') }}</Link></div></article></div>
            </template>
            <EmptyState v-else :title="hasFilters ? t('payments.noMatch') : t('payments.empty')" :description="hasFilters ? t('payments.noMatchHelp') : t('payments.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('payments.clearFilters') }}</button></template></EmptyState>
            <Pagination :links="payments.links" :from="payments.from" :to="payments.to" :total="payments.total" preserve-state />
        </section>
    </AdminLayout>
</template>
