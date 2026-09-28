<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import FieldError from '../../../components/FieldError.vue';

interface Settings { bank_transfer_enabled: boolean; cash_enabled: boolean; online_enabled: boolean; cod_enabled: boolean; cod_deposit_type: string; cod_deposit_value: string }
const props = defineProps<{ settings: Settings }>();
const form = useForm({ ...props.settings });
const submit = () => form.transform((data) => ({
    ...data,
    cod_deposit_value: data.cod_deposit_type === 'NONE' ? '0.0000' : data.cod_deposit_value,
})).patch('/admin/settings/payments', { preserveScroll: true });
</script>

<template>
    <Head title="Payment settings" />
    <AdminLayout><section class="max-w-3xl"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Store configuration</p><h1 class="mt-4 text-5xl font-semibold tracking-[-0.04em]">Payment settings</h1><p class="mt-3 text-[#637067]">Enable methods and define the COD deposit snapshot used by new order plans. Provider credentials are intentionally not stored here.</p><form class="mt-8 border border-[#17211b]/15 bg-white p-6" @submit.prevent="submit"><fieldset class="grid gap-4 sm:grid-cols-2"><label v-for="field in ['bank_transfer_enabled', 'cash_enabled', 'online_enabled', 'cod_enabled'] as const" :key="field" class="flex items-center gap-3 border p-4"><input v-model="form[field]" type="checkbox"><span class="font-semibold">{{ field.replaceAll('_', ' ') }}</span></label></fieldset><div class="mt-6 grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">COD deposit type<select v-model="form.cod_deposit_type" class="mt-2 w-full border px-3 py-2"><option value="NONE">None</option><option value="PERCENTAGE">Percentage</option><option value="FIXED_AMOUNT">Fixed amount</option></select></label><label class="text-sm font-semibold">COD deposit value<input v-model="form.cod_deposit_value" :disabled="form.cod_deposit_type === 'NONE'" inputmode="decimal" class="mt-2 w-full border px-3 py-2 disabled:bg-gray-100"></label></div><FieldError :message="form.errors.cod_deposit_type ?? form.errors.cod_deposit_value" /><button :disabled="form.processing" class="mt-6 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Save payment settings</button></form></section></AdminLayout>
</template>
