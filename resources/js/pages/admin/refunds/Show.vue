<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Refund { id: string; order_id: string; order_number: string | null; payment_id: string; payment_method: string | null; payment_provider: string | null; return_id: string | null; status: string; amount: string; currency: string; reason: string; external_reference: string | null; completed_at: string | null; created_at: string | null }
interface Balance { captured_total: string; refunded_total: string; refundable_remaining: string }
const props = defineProps<{ refund: Refund; balance: Balance; capabilities: { complete: boolean } }>();
const page = usePage<PageProps>();
const status = computed(() => page.props.status);
const serverErrors = computed(() => page.props.errors as Record<string, string | undefined>);
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('refunds.manage') ?? false);
const form = useForm({ idempotency_key: crypto.randomUUID(), external_reference: '' });
const complete = () => form.post(`/admin/refunds/${props.refund.id}/complete`, { preserveScroll: true, onSuccess: () => { form.idempotency_key = crypto.randomUUID(); form.external_reference = ''; } });
</script>

<template>
    <Head :title="`Refund ${refund.id}`" />
    <AdminLayout><section>
        <div class="flex flex-wrap items-end justify-between gap-5"><div><Link href="/admin/refunds" class="text-sm font-semibold text-emerald-800">Back to refunds</Link><p class="mt-7 font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ refund.status }} / {{ refund.payment_method }} / {{ refund.payment_provider }}</p><h1 class="mt-3 break-all font-mono text-3xl font-semibold">{{ refund.id }}</h1></div><Link :href="`/admin/orders/${refund.order_id}`" class="border border-[#17211b] px-4 py-3 text-sm font-semibold">Open order {{ refund.order_number }}</Link></div>
        <p v-if="status" class="mt-6 border border-emerald-700 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ status }}</p>
        <div class="mt-8 grid gap-px bg-[#17211b]/15 sm:grid-cols-3"><article v-for="row in [['Refund amount', `${refund.currency} ${refund.amount}`], ['Status', refund.status], ['Created', refund.created_at ? new Date(refund.created_at).toLocaleString() : '—']]" :key="row[0]" class="bg-white p-5"><p class="text-xs uppercase text-[#637067]">{{ row[0] }}</p><p class="mt-2 font-mono font-semibold">{{ row[1] }}</p></article></div>
        <div class="mt-8 grid gap-6 lg:grid-cols-2"><article class="border border-[#17211b]/15 bg-white p-6"><h2 class="text-2xl font-semibold">Refund record</h2><dl class="mt-5 grid gap-3 text-sm"><div><dt class="text-[#637067]">Reason</dt><dd class="mt-1">{{ refund.reason }}</dd></div><div><dt class="text-[#637067]">Payment</dt><dd class="mt-1"><Link :href="`/admin/payments/${refund.payment_id}`" class="font-mono font-semibold text-emerald-800">{{ refund.payment_id }}</Link></dd></div><div v-if="refund.return_id"><dt class="text-[#637067]">Return</dt><dd class="mt-1"><Link :href="`/admin/returns/${refund.return_id}`" class="font-mono font-semibold text-emerald-800">{{ refund.return_id }}</Link></dd></div><div><dt class="text-[#637067]">External reference</dt><dd class="mt-1 font-mono">{{ refund.external_reference ?? 'Not recorded' }}</dd></div><div><dt class="text-[#637067]">Completed</dt><dd class="mt-1">{{ refund.completed_at ? new Date(refund.completed_at).toLocaleString() : 'Pending' }}</dd></div></dl></article><article class="border border-[#17211b]/15 bg-[#17211b] p-6 text-white"><h2 class="text-2xl font-semibold">Payment balance</h2><dl class="mt-5 grid gap-3"><div v-for="row in [['Captured', balance.captured_total], ['Refunded', balance.refunded_total], ['Refundable remaining', balance.refundable_remaining]]" :key="row[0]" class="flex justify-between border-b border-white/20 pb-3"><dt class="text-white/60">{{ row[0] }}</dt><dd class="font-mono font-semibold">{{ refund.currency }} {{ row[1] }}</dd></div></dl><p class="mt-4 text-xs text-white/60">The server enforces the refundable balance. This display is informational.</p></article></div>
        <form v-if="capabilities.complete && canManage" class="mt-10 border border-emerald-800/30 bg-emerald-50 p-6" @submit.prevent="complete"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Manual settlement</p><h2 class="mt-2 text-2xl font-semibold">Complete pending refund</h2><p class="mt-2 text-sm text-[#637067]">Record the provider or accounting reference after the money has been returned.</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">External reference<input v-model="form.external_reference" required maxlength="255" class="mt-2 w-full border bg-white px-3 py-2 font-normal" /></label><label class="text-sm font-semibold">Idempotency key<input v-model="form.idempotency_key" required maxlength="100" class="mt-2 w-full border bg-white px-3 py-2 font-mono font-normal" /></label></div><FieldError :message="form.errors.external_reference ?? form.errors.idempotency_key ?? serverErrors.refund" /><button :disabled="form.processing" class="mt-4 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-40">Complete refund</button></form>
    </section></AdminLayout>
</template>
