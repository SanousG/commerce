<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Refund { id: string; order_id: string; order_number: string | null; payment_id: string; payment_method: string | null; payment_provider: string | null; return_id: string | null; status: string; amount: string; currency: string; reason: string; external_reference: string | null; completed_at: string | null; created_at: string | null }
interface Balance { captured_total: string; refunded_total: string; refundable_remaining: string }

const props = defineProps<{ refund: Refund; balance: Balance; capabilities: { complete: boolean } }>();
const { t, te, locale } = useI18n();
const page = usePage<PageProps>();
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('refunds.manage') ?? false);
const canComplete = computed(() => props.capabilities.complete && canManage.value);
const notice = computed(() => page.props.status ? t('refunds.notices.completed') : undefined);
const serverErrors = computed(() => page.props.errors as Record<string, string | undefined>);
const form = useForm({ idempotency_key: crypto.randomUUID(), external_reference: '' });
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : t('common.notAvailable');
const formatDecimal = (value: string) => { const [integer, decimal = ''] = value.split('.'); return `${BigInt(integer).toLocaleString(locale.value)}${decimal ? `${locale.value.startsWith('es') ? ',' : '.'}${decimal}` : ''}`; };
const money = (value: string) => `${props.refund.currency} ${formatDecimal(value)}`;
const enumLabel = (group: string, value: string | null) => value && te(`payments.${group}.${value}`) ? t(`payments.${group}.${value}`) : (value ?? t('common.notAvailable'));
const localizedError = (message?: string) => {
    if (!message) return undefined;
    if (message.includes('already in use')) return t('refunds.errors.referenceUsed');
    if (message.includes('external reference')) return t('refunds.errors.referenceRequired');
    if (message.includes('different refund completion')) return t('refunds.errors.idempotencyConflict');
    if (message.includes('idempotency')) return t('refunds.errors.idempotency');
    if (message.includes('Only pending')) return t('refunds.errors.pendingOnly');
    if (message.includes('currencies must match')) return t('refunds.errors.currency');
    if (message.includes('exceeds')) return t('refunds.errors.balance');
    return message;
};
const complete = () => {
    if (!window.confirm(t('refunds.completeConfirm'))) return;
    form.post(`/admin/refunds/${props.refund.id}/complete`, { preserveScroll: true, onSuccess: () => { form.idempotency_key = crypto.randomUUID(); form.external_reference = ''; } });
};
</script>

<template>
    <Head :title="t('refunds.detailTitle', { id: refund.id.slice(0, 8) })" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('refunds.title'), href: '/admin/refunds' }, { label: refund.id.slice(0, 8) }]" />
            <PageHeader :title="t('refunds.detailTitle', { id: refund.id.slice(0, 8) })" :eyebrow="t('refunds.financialRecord')" :description="t('refunds.detailDescription', { date: formatDate(refund.created_at) })"><template #actions><div class="grid grid-cols-2 gap-2 sm:flex"><Link :href="`/admin/payments/${refund.payment_id}`" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('refunds.openPayment') }}</Link><Link :href="`/admin/orders/${refund.order_id}`" class="inline-flex min-h-10 items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('refunds.openOrder') }}</Link></div></template></PageHeader>
            <StatusNotice :message="notice" />

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" :aria-label="t('refunds.summary')">
                <article class="admin-card flex items-center justify-between gap-3 p-3"><p class="admin-label">{{ t('common.status') }}</p><StatusBadge :status="refund.status" /></article>
                <article class="admin-card p-3"><p class="admin-label">{{ t('refunds.amount') }}</p><p class="mt-1 font-mono text-base font-semibold tabular-nums text-admin-secondary">{{ money(refund.amount) }}</p></article>
                <article class="admin-card p-3"><p class="admin-label">{{ t('refunds.created') }}</p><p class="mt-1 text-xs text-admin-neutral">{{ formatDate(refund.created_at) }}</p></article>
                <article class="admin-card p-3"><p class="admin-label">{{ t('refunds.completed') }}</p><p class="mt-1 text-xs text-admin-neutral">{{ refund.completed_at ? formatDate(refund.completed_at) : t('refunds.notCompleted') }}</p></article>
            </section>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(18rem,2fr)]">
                <article class="admin-card min-w-0 p-4 sm:p-5"><p class="admin-label text-admin-secondary">{{ t('refunds.refundSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('refunds.refundRecord') }}</h2><dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2"><div class="sm:col-span-2"><dt class="admin-label">{{ t('refunds.reason') }}</dt><dd class="mt-1 break-words text-sm">{{ refund.reason }}</dd></div><div><dt class="admin-label">{{ t('refunds.identifier') }}</dt><dd class="mt-1 break-all font-mono text-xs">{{ refund.id }}</dd></div><div><dt class="admin-label">{{ t('refunds.externalReference') }}</dt><dd class="mt-1 break-all font-mono text-xs">{{ refund.external_reference ?? t('refunds.notRecorded') }}</dd></div><div v-if="refund.return_id"><dt class="admin-label">{{ t('refunds.relatedReturn') }}</dt><dd class="mt-1"><Link :href="`/admin/returns/${refund.return_id}`" class="admin-link break-all font-mono text-xs">{{ refund.return_id }}</Link></dd></div></dl></article>
                <article class="admin-card p-4 sm:p-5"><p class="admin-label text-admin-secondary">{{ t('refunds.sourceSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('refunds.paymentAndOrder') }}</h2><dl class="mt-4 grid gap-4"><div><dt class="admin-label">{{ t('refunds.order') }}</dt><dd class="mt-1"><Link :href="`/admin/orders/${refund.order_id}`" class="admin-link font-mono text-sm font-semibold">{{ refund.order_number ?? refund.order_id.slice(0, 8) }}</Link></dd></div><div><dt class="admin-label">{{ t('refunds.payment') }}</dt><dd class="mt-1"><Link :href="`/admin/payments/${refund.payment_id}`" class="admin-link break-all font-mono text-xs">{{ refund.payment_id }}</Link></dd><dd class="mt-1 text-xs text-admin-neutral">{{ enumLabel('methods', refund.payment_method) }} · {{ enumLabel('providers', refund.payment_provider) }}</dd></div></dl></article>
            </section>

            <section><div class="mb-3"><p class="admin-label text-admin-secondary">{{ t('refunds.ledgerSection') }}</p><h2 class="mt-1 text-xl font-semibold">{{ t('refunds.paymentBalance') }}</h2><p class="mt-1 text-sm text-admin-neutral">{{ t('refunds.balanceHelp') }}</p></div><div class="admin-card grid overflow-hidden sm:grid-cols-3"><article v-for="row in [{ label: t('refunds.capturedTotal'), value: balance.captured_total }, { label: t('refunds.refundedTotal'), value: balance.refunded_total }, { label: t('refunds.refundableRemaining'), value: balance.refundable_remaining }]" :key="row.label" class="border-b border-admin-border p-4 last:border-b-0 sm:border-r sm:border-b-0 sm:last:border-r-0"><p class="admin-label">{{ row.label }}</p><p class="mt-2 font-mono text-base font-semibold tabular-nums">{{ money(row.value) }}</p></article></div><div class="mt-3 flex flex-col gap-2 text-xs text-admin-neutral sm:flex-row sm:items-center sm:justify-between"><p>{{ t('refunds.ledgerAuthority') }}</p><Link :href="`/admin/payments/${refund.payment_id}`" class="admin-link shrink-0">{{ t('refunds.openPaymentLedger') }}</Link></div></section>

            <form v-if="canComplete" class="rounded-lg border border-admin-warning-border bg-admin-warning-bg p-4 sm:p-5" @submit.prevent="complete"><p class="admin-label text-amber-700">{{ t('refunds.operationSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('refunds.completeRefund') }}</h2><p class="mt-1 max-w-3xl text-sm text-admin-text-secondary">{{ t('refunds.completeHelp') }}</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5" for="refund-reference"><span class="admin-label">{{ t('refunds.externalReference') }}</span><input id="refund-reference" v-model="form.external_reference" required maxlength="255" autocomplete="off" class="admin-control w-full px-3 font-mono text-sm" :aria-describedby="form.errors.external_reference ? 'refund-reference-error' : undefined" /><FieldError id="refund-reference-error" :message="localizedError(form.errors.external_reference)" /></label><label class="grid gap-1.5" for="refund-key"><span class="admin-label">{{ t('refunds.idempotencyKey') }}</span><input id="refund-key" v-model="form.idempotency_key" required maxlength="100" autocomplete="off" class="admin-control w-full px-3 font-mono text-sm" :aria-describedby="form.errors.idempotency_key ? 'refund-key-error' : undefined" /><FieldError id="refund-key-error" :message="localizedError(form.errors.idempotency_key)" /></label></div><FieldError :message="localizedError(serverErrors.refund ?? serverErrors.currency ?? serverErrors.amount)" /><div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-admin-neutral">{{ t('refunds.completeWarning') }}</p><button :disabled="form.processing" class="min-h-10 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? t('refunds.completing') : t('refunds.completeRefund') }}</button></div></form>
        </section>
    </AdminLayout>
</template>
