<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface ExchangeItem { id: string; return_item_id: string; quantity: number; returned_item: { product_name: string; variant_name: string | null; sku: string }; replacement_variant: { id: string; product_name: string; name: string | null; sku: string } }
interface Exchange { id: string; short_id: string; status: string; approved_at: string | null; completed_at: string | null; cancelled_at: string | null; cancellation_reason: string | null; created_at: string | null; order: { id: string; order_number: string; customer_id: string | null; customer_name: string; email: string | null; phone: string | null }; return: { id: string; short_id: string; status: string; requested_at: string | null }; items: ExchangeItem[] }
interface Capabilities { manage: boolean; approve: boolean; complete: boolean; cancel: boolean }

const props = defineProps<{ exchange: Exchange; capabilities: Capabilities }>();
const { t, locale } = useI18n();
const page = usePage<PageProps>();
const noticeKeys: Record<string, string> = { 'Exchange created.': 'created', 'Exchange approved.': 'approved', 'Exchange completed.': 'completed', 'Exchange cancelled.': 'cancelled' };
const errorKeys: Record<string, string> = {
    'A cancellation reason is required.': 'reasonRequired',
    'The exchange cannot transition to APPROVED.': 'approveState',
    'The exchange cannot transition to COMPLETED.': 'completeState',
    'The exchange cannot transition to CANCELLED.': 'cancelState',
};
const statusNotice = computed(() => { const value = page.props.status; return value ? t(`exchanges.notices.${noticeKeys[value] ?? 'completedOperation'}`) : undefined; });
const errors = computed(() => page.props.errors as Record<string, string | undefined>);
const approveForm = useForm({});
const completeForm = useForm({});
const cancelForm = useForm({ reason: '' });
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : t('common.notAvailable');
const localizedError = (message?: string) => message && errorKeys[message] ? t(`exchanges.errors.${errorKeys[message]}`) : message;
const approve = () => { if (window.confirm(t('exchanges.approveConfirm'))) approveForm.post(`/admin/exchanges/${props.exchange.id}/approve`, { preserveScroll: true }); };
const complete = () => { if (window.confirm(t('exchanges.completeConfirm'))) completeForm.post(`/admin/exchanges/${props.exchange.id}/complete`, { preserveScroll: true }); };
const cancel = () => { if (window.confirm(t('exchanges.cancelConfirm'))) cancelForm.post(`/admin/exchanges/${props.exchange.id}/cancel`, { preserveScroll: true }); };
</script>

<template>
    <Head :title="t('exchanges.detailTitle', { id: exchange.short_id })" />
    <AdminLayout><div class="space-y-6">
        <Breadcrumbs :items="[{ label: t('nav.operations') }, { label: t('exchanges.title'), href: '/admin/exchanges' }, { label: exchange.short_id }]" />
        <PageHeader :title="t('exchanges.detailTitle', { id: exchange.short_id })" :eyebrow="t('exchanges.operationalRecord')" :description="t('exchanges.detailDescription', { order: exchange.order.order_number, date: formatDate(exchange.created_at) })"><template #actions><div class="grid grid-cols-2 gap-2 sm:flex"><Link :href="`/admin/returns/${exchange.return.id}`" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold hover:bg-slate-50">{{ t('exchanges.returnNumber', { id: exchange.return.short_id }) }}</Link><Link :href="`/admin/orders/${exchange.order.id}`" class="inline-flex min-h-10 items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('exchanges.openOrder') }}</Link></div></template></PageHeader>
        <StatusNotice :message="statusNotice" />

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" :aria-label="t('exchanges.summary')"><article class="admin-card p-3"><p class="admin-label">{{ t('common.status') }}</p><div class="mt-2"><StatusBadge :status="exchange.status" /></div></article><article class="admin-card p-3"><p class="admin-label">{{ t('exchanges.order') }}</p><p class="mt-1 font-mono text-sm font-semibold">{{ exchange.order.order_number }}</p></article><article class="admin-card p-3"><p class="admin-label">{{ t('exchanges.sourceReturn') }}</p><div class="mt-1 flex items-center gap-2"><Link :href="`/admin/returns/${exchange.return.id}`" class="admin-link font-mono text-sm font-semibold">{{ exchange.return.short_id }}</Link><StatusBadge :status="exchange.return.status" /></div></article><article class="admin-card p-3"><p class="admin-label">{{ t('exchanges.items') }}</p><p class="mt-1 font-mono text-lg font-semibold">{{ exchange.items.length }}</p><p class="text-xs text-admin-neutral">{{ t('exchanges.replacementLines') }}</p></article></section>

        <section v-if="capabilities.approve || capabilities.complete" class="admin-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="admin-label text-admin-secondary">{{ t('exchanges.nextOperation') }}</p><p class="mt-1 text-sm text-admin-neutral">{{ t('exchanges.operationHelp') }}</p></div><div class="grid gap-2 sm:flex"><button v-if="capabilities.approve" :disabled="approveForm.processing" class="rounded-md bg-admin-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="approve">{{ approveForm.processing ? t('exchanges.processing') : t('exchanges.approve') }}</button><button v-if="capabilities.complete" :disabled="completeForm.processing" class="rounded-md border border-admin-success-border bg-admin-success-bg px-4 py-2.5 text-sm font-semibold text-admin-success disabled:opacity-50" @click="complete">{{ completeForm.processing ? t('exchanges.processing') : t('exchanges.complete') }}</button></div></section>
        <FieldError :message="localizedError(errors.exchange)" />

        <section class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(18rem,2fr)]"><div class="min-w-0"><p class="admin-label text-admin-secondary">{{ t('exchanges.manifestSection') }}</p><h2 class="mt-1 text-xl font-semibold">{{ t('exchanges.manifest') }}</h2><p class="mt-1 text-sm text-admin-neutral">{{ t('exchanges.manifestHelp') }}</p><div class="mt-4 grid gap-3"><article v-for="item in exchange.items" :key="item.id" class="admin-card grid min-w-0 gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center"><div class="min-w-0"><p class="admin-label">{{ t('exchanges.returned') }}</p><p class="mt-1 truncate text-sm font-semibold" :title="item.returned_item.product_name">{{ item.returned_item.product_name }}</p><p class="mt-0.5 truncate text-xs text-admin-neutral">{{ item.returned_item.variant_name ?? t('exchanges.defaultVariant') }}</p><p class="mt-2 break-all font-mono text-xs">{{ item.returned_item.sku }}</p></div><div class="flex items-center gap-2 border-y border-admin-border py-2 sm:block sm:border-0 sm:py-0 sm:text-center"><span class="font-mono text-lg text-admin-secondary" aria-hidden="true">→</span><strong class="font-mono text-xs">{{ t('exchanges.quantityValue', { count: item.quantity }) }}</strong></div><div class="min-w-0"><p class="admin-label">{{ t('exchanges.replacement') }}</p><p class="mt-1 truncate text-sm font-semibold" :title="item.replacement_variant.product_name">{{ item.replacement_variant.product_name }}</p><p class="mt-0.5 truncate text-xs text-admin-neutral">{{ item.replacement_variant.name ?? t('exchanges.defaultVariant') }}</p><p class="mt-2 break-all font-mono text-xs">{{ item.replacement_variant.sku }}</p></div></article></div></div>
            <aside><p class="admin-label text-admin-secondary">{{ t('exchanges.contextSection') }}</p><h2 class="mt-1 text-xl font-semibold">{{ t('exchanges.customerAndSource') }}</h2><div class="admin-card mt-4 divide-y divide-admin-border px-4"><div class="py-3"><p class="admin-label">{{ t('exchanges.customer') }}</p><p class="mt-1 text-sm font-semibold">{{ exchange.order.customer_name }}</p><p class="mt-1 break-all text-xs text-admin-neutral">{{ exchange.order.email ?? t('exchanges.noEmail') }}</p><p class="text-xs text-admin-neutral">{{ exchange.order.phone ?? t('exchanges.noPhone') }}</p></div><div class="py-3"><p class="admin-label">{{ t('exchanges.sourceReturn') }}</p><Link :href="`/admin/returns/${exchange.return.id}`" class="admin-link mt-1 inline-flex font-mono text-xs font-semibold">{{ exchange.return.short_id }}</Link><p class="mt-1 text-xs text-admin-neutral">{{ t('exchanges.requestedOn', { date: formatDate(exchange.return.requested_at) }) }}</p></div></div></aside></section>

        <section class="admin-card p-4"><p class="admin-label text-admin-secondary">{{ t('exchanges.lifecycleSection') }}</p><h2 class="mt-1 text-base font-semibold">{{ t('exchanges.lifecycle') }}</h2><dl class="mt-3 grid gap-px overflow-hidden rounded-md bg-admin-border sm:grid-cols-4"><div v-for="row in [{ label: t('exchanges.created'), value: exchange.created_at }, { label: t('exchanges.approvedAt'), value: exchange.approved_at }, { label: t('exchanges.completedAt'), value: exchange.completed_at }, { label: t('exchanges.cancelledAt'), value: exchange.cancelled_at }]" :key="row.label" class="bg-admin-canvas p-3"><dt class="admin-label">{{ row.label }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(row.value) }}</dd></div></dl><p v-if="exchange.cancellation_reason" class="mt-3 rounded-md border border-admin-danger-border bg-admin-danger-bg p-3 text-xs"><strong>{{ t('exchanges.cancellationReason') }}:</strong> {{ exchange.cancellation_reason }}</p></section>

        <form v-if="capabilities.cancel" class="rounded-lg border border-admin-danger-border bg-admin-danger-bg p-4" @submit.prevent="cancel"><p class="admin-label text-admin-danger">{{ t('exchanges.dangerSection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-danger">{{ t('exchanges.cancelExchange') }}</h2><p class="mt-1 text-xs text-admin-text-secondary">{{ t('exchanges.cancelHelp') }}</p><label class="mt-4 grid gap-1.5 text-xs font-medium">{{ t('exchanges.cancellationReason') }}<textarea v-model="cancelForm.reason" required maxlength="500" class="admin-control min-h-24 p-3" /></label><FieldError :message="localizedError(cancelForm.errors.reason ?? errors.exchange)" /><button :disabled="cancelForm.processing" class="mt-3 w-full rounded-md border border-admin-danger-border bg-white px-4 py-2.5 text-sm font-semibold text-admin-danger disabled:opacity-50 sm:w-auto">{{ cancelForm.processing ? t('exchanges.processing') : t('exchanges.cancelExchange') }}</button></form>
    </div></AdminLayout>
</template>
