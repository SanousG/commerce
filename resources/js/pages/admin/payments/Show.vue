<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Transaction { id: string; type: string; status: string; amount: string; currency: string; actor: string | null; occurred_at: string }
interface Payment { id: string; order_id: string; order_number: string; method: string; provider: string; status: string; currency: string; amount: string; captured_amount: string; external_reference: string | null; created_at: string; transactions: Transaction[] }

const props = defineProps<{ payment: Payment }>();
const page = usePage<PageProps>();
const { t, te, locale } = useI18n();
const canManageRefunds = computed(() => page.props.currentTenantMembership?.permissions.includes('refunds.manage') ?? false);
const notice = computed(() => page.props.status ? t('payments.notices.refundCreated') : undefined);
const refundForm = useForm({ amount: '', reason: '', idempotency_key: crypto.randomUUID(), return_id: '' });
const formErrors = computed(() => refundForm.errors as Record<string, string | undefined>);
const refundError = computed(() => {
    const errors = formErrors.value;
    if (errors.amount?.includes('exceeds')) return t('payments.errors.refundableBalance');
    if (errors.amount) return t('payments.errors.amount');
    if (errors.reason) return t('payments.errors.reason');
    if (errors.return_id) return t('payments.errors.returnId');
    if (errors.idempotency_key?.includes('different')) return t('payments.errors.idempotencyConflict');
    if (errors.idempotency_key) return t('payments.errors.idempotency');
    if (errors.currency) return t('payments.errors.currency');
    return errors.refund ?? errors.payment;
});
const enumLabel = (group: string, value: string) => te(`payments.${group}.${value}`) ? t(`payments.${group}.${value}`) : value;
const formatDate = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const formatDecimal = (value: string) => { const [integer, decimal = ''] = value.split('.'); return `${BigInt(integer).toLocaleString(locale.value)}${decimal ? `${locale.value.startsWith('es') ? ',' : '.'}${decimal}` : ''}`; };
const money = (amount: string, currency = props.payment.currency) => `${currency} ${formatDecimal(amount)}`;
const createRefund = () => refundForm.post(`/admin/payments/${props.payment.id}/refunds`, { preserveScroll: true, onSuccess: () => { refundForm.reset('amount', 'reason', 'return_id'); refundForm.idempotency_key = crypto.randomUUID(); } });
</script>

<template>
    <Head :title="t('payments.detailTitle', { order: payment.order_number })" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('payments.title'), href: '/admin/payments' }, { label: payment.order_number }]" />
            <PageHeader :title="t('payments.detailTitle', { order: payment.order_number })" :eyebrow="t('payments.financialRecord')" :description="t('payments.detailDescription', { date: formatDate(payment.created_at) })"><template #actions><Link href="/admin/payments" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold sm:w-auto">{{ t('payments.back') }}</Link></template></PageHeader>
            <p v-if="notice" class="rounded-md border border-admin-success-border bg-admin-success-bg p-3 text-sm font-medium text-admin-success" role="status">{{ notice }}</p>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="admin-card flex items-center justify-between gap-3 p-3"><p class="admin-label">{{ t('common.status') }}</p><StatusBadge :status="payment.status" /></article>
                <article class="admin-card p-3"><p class="admin-label">{{ t('payments.amount') }}</p><p class="mt-1 font-mono text-sm font-semibold tabular-nums">{{ money(payment.amount) }}</p></article>
                <article class="admin-card p-3"><p class="admin-label">{{ t('payments.captured') }}</p><p class="mt-1 font-mono text-sm font-semibold tabular-nums text-admin-secondary">{{ money(payment.captured_amount) }}</p></article>
                <article class="admin-card min-w-0 p-3"><p class="admin-label">{{ t('payments.reference') }}</p><p class="mt-1 truncate font-mono text-sm" :title="payment.external_reference ?? undefined">{{ payment.external_reference ?? t('common.none') }}</p></article>
            </section>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                <article class="admin-card p-4 sm:p-5"><p class="admin-label text-admin-secondary">{{ t('payments.summarySection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('payments.paymentSummary') }}</h2><dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2"><div><dt class="admin-label">{{ t('payments.method') }}</dt><dd class="mt-1 text-sm font-medium">{{ enumLabel('methods', payment.method) }}</dd></div><div><dt class="admin-label">{{ t('payments.provider') }}</dt><dd class="mt-1 text-sm">{{ enumLabel('providers', payment.provider) }}</dd></div><div><dt class="admin-label">{{ t('payments.identifier') }}</dt><dd class="mt-1 break-all font-mono text-xs">{{ payment.id }}</dd></div><div><dt class="admin-label">{{ t('payments.created') }}</dt><dd class="mt-1 text-sm">{{ formatDate(payment.created_at) }}</dd></div></dl></article>
                <article class="admin-card p-4 sm:p-5"><p class="admin-label text-admin-secondary">{{ t('payments.orderSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('payments.linkedOrder') }}</h2><p class="mt-4 font-mono text-sm font-semibold">{{ payment.order_number }}</p><p class="mt-1 text-xs text-admin-neutral">{{ t('payments.orderIntegrity') }}</p><Link :href="`/admin/orders/${payment.order_id}`" class="admin-link mt-4 inline-block text-sm">{{ t('payments.openOrder') }}</Link></article>
            </section>

            <section><div class="mb-3"><p class="admin-label text-admin-secondary">{{ t('payments.ledgerSection') }}</p><h2 class="mt-1 text-xl font-semibold">{{ t('payments.transactions') }}</h2><p class="mt-1 text-sm text-admin-neutral">{{ t('payments.ledgerHelp') }}</p></div><template v-if="payment.transactions.length"><div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[18%] p-3">{{ t('payments.type') }}</th><th class="w-[17%] p-3">{{ t('common.status') }}</th><th class="w-[22%] p-3 text-right">{{ t('payments.amount') }}</th><th class="w-[20%] p-3">{{ t('payments.actor') }}</th><th class="w-[23%] p-3">{{ t('payments.occurred') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="transaction in payment.transactions" :key="transaction.id"><td class="p-3 font-medium">{{ enumLabel('transactionTypes', transaction.type) }}</td><td class="p-3"><StatusBadge :status="transaction.status" /></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ money(transaction.amount, transaction.currency) }}</td><td class="truncate p-3">{{ transaction.actor ?? t('payments.systemActor') }}</td><td class="p-3 text-admin-neutral">{{ formatDate(transaction.occurred_at) }}</td></tr></tbody></table></div><ol class="grid gap-2.5 md:hidden"><li v-for="transaction in payment.transactions" :key="`mobile-${transaction.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold">{{ enumLabel('transactionTypes', transaction.type) }}</p><p class="mt-1 font-mono text-xs font-semibold">{{ money(transaction.amount, transaction.currency) }}</p></div><StatusBadge :status="transaction.status" /></div><div class="mt-3 flex items-end justify-between gap-3 border-t border-admin-border pt-2.5"><div><p class="admin-label">{{ t('payments.actor') }}</p><p class="mt-1 text-xs">{{ transaction.actor ?? t('payments.systemActor') }}</p></div><time class="text-right text-xs text-admin-neutral">{{ formatDate(transaction.occurred_at) }}</time></div></li></ol></template><EmptyState v-else :title="t('payments.noTransactions')" :description="t('payments.noTransactionsHelp')" /></section>

            <form v-if="canManageRefunds" class="admin-card p-4 sm:p-5" @submit.prevent="createRefund"><p class="admin-label text-admin-secondary">{{ t('payments.refundSection') }}</p><h2 class="mt-1 text-xl font-semibold">{{ t('payments.createRefund') }}</h2><p class="mt-1 text-sm text-admin-neutral">{{ t('payments.refundHelp', { amount: money(payment.captured_amount) }) }}</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5" for="refund-amount"><span class="admin-label">{{ t('payments.refundAmount') }}</span><input id="refund-amount" v-model="refundForm.amount" required inputmode="decimal" placeholder="0.0000" class="admin-control w-full px-3 font-mono text-sm" /></label><label class="grid gap-1.5" for="refund-reason"><span class="admin-label">{{ t('payments.reason') }}</span><input id="refund-reason" v-model="refundForm.reason" required maxlength="500" class="admin-control w-full px-3 text-sm" /></label><label class="grid gap-1.5" for="refund-return"><span class="admin-label">{{ t('payments.returnId') }}</span><input id="refund-return" v-model="refundForm.return_id" maxlength="36" :placeholder="t('payments.uuidOptional')" class="admin-control w-full px-3 font-mono text-sm" /></label><label class="grid gap-1.5" for="refund-key"><span class="admin-label">{{ t('payments.idempotencyKey') }}</span><input id="refund-key" v-model="refundForm.idempotency_key" required maxlength="100" class="admin-control w-full px-3 font-mono text-sm" /></label></div><FieldError id="refund-error" :message="refundError" /><div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-admin-neutral">{{ t('payments.refundPendingHelp') }}</p><button :disabled="refundForm.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-40">{{ refundForm.processing ? t('payments.creatingRefund') : t('payments.createPendingRefund') }}</button></div></form>
        </section>
    </AdminLayout>
</template>
