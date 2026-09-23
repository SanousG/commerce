<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

type CustomerStatus = 'ACTIVE' | 'INACTIVE' | 'BLOCKED' | 'ARCHIVED';
interface Customer { id: string; first_name: string | null; last_name: string | null; display_name: string; email: string | null; phone: string | null; status: CustomerStatus; notes: string | null }
interface Address { id: string; label: string | null; recipient_name: string | null; phone: string | null; country_code: string; region: string | null; city: string; district: string | null; address_line_1: string; address_line_2: string | null; postal_code: string | null; reference: string | null; is_default_shipping: boolean; is_default_billing: boolean }
const props = defineProps<{ customer: Customer; addresses: Address[]; statuses: CustomerStatus[]; defaultCountry: string }>();
const page = usePage<PageProps>();
const canUpdate = computed(() => page.props.currentTenantMembership?.permissions.includes('customers.update') ?? false);
const canArchive = computed(() => page.props.currentTenantMembership?.permissions.includes('customers.archive') ?? false);
const customerForm = useForm({ first_name: props.customer.first_name ?? '', last_name: props.customer.last_name ?? '', email: props.customer.email ?? '', phone: props.customer.phone ?? '', status: props.customer.status, notes: props.customer.notes ?? '' });
const emptyAddress = () => ({ label: '', recipient_name: '', phone: '', country_code: props.defaultCountry, region: '', city: '', district: '', address_line_1: '', address_line_2: '', postal_code: '', reference: '', is_default_shipping: false, is_default_billing: false });
const newAddress = useForm(emptyAddress());
const addressForms = props.addresses.map((address) => ({ id: address.id, form: useForm({ label: address.label ?? '', recipient_name: address.recipient_name ?? '', phone: address.phone ?? '', country_code: address.country_code, region: address.region ?? '', city: address.city, district: address.district ?? '', address_line_1: address.address_line_1, address_line_2: address.address_line_2 ?? '', postal_code: address.postal_code ?? '', reference: address.reference ?? '', is_default_shipping: address.is_default_shipping, is_default_billing: address.is_default_billing }) }));
const addressFields = [
    { key: 'label', label: 'Label' }, { key: 'recipient_name', label: 'Recipient name' },
    { key: 'phone', label: 'Phone' }, { key: 'country_code', label: 'Country code' },
    { key: 'region', label: 'Region / province' }, { key: 'city', label: 'City' },
    { key: 'district', label: 'District' }, { key: 'address_line_1', label: 'Address line 1' },
    { key: 'address_line_2', label: 'Address line 2' }, { key: 'postal_code', label: 'Postal code' },
    { key: 'reference', label: 'Reference' },
] as const;
const saveCustomer = () => customerForm.put(`/admin/customers/${props.customer.id}`);
const createAddress = () => newAddress.post(`/admin/customers/${props.customer.id}/addresses`, { preserveScroll: true, onSuccess: () => newAddress.reset() });
const saveAddress = (entry: (typeof addressForms)[number]) => entry.form.put(`/admin/customers/${props.customer.id}/addresses/${entry.id}`, { preserveScroll: true });
const setDefault = (addressId: string, type: 'shipping' | 'billing') => router.post(`/admin/customers/${props.customer.id}/addresses/${addressId}/default/${type}`, {}, { preserveScroll: true });
const deleteAddress = (addressId: string) => { if (window.confirm('Delete this address?')) router.delete(`/admin/customers/${props.customer.id}/addresses/${addressId}`, { preserveScroll: true }); };
const archive = () => { if (window.confirm(`Archive ${props.customer.display_name}?`)) router.post(`/admin/customers/${props.customer.id}/archive`); };
</script>

<template>
    <Head :title="customer.display_name" />
    <AdminLayout><section><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Customer record</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ customer.display_name }}.</h1></div><div class="flex gap-4"><button v-if="canArchive && customer.status !== 'ARCHIVED'" class="py-3 text-sm font-semibold text-red-800" @click="archive">Archive</button><Link href="/admin/customers" class="py-3 text-sm font-semibold text-emerald-800">Back</Link></div></div>
        <p v-if="page.props.status" class="mt-6 border border-emerald-800/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ page.props.status }}</p>
        <p v-if="!canUpdate" class="mt-6 border border-amber-800/20 bg-amber-50 px-4 py-3 text-sm text-amber-900">You have view-only access.</p>
        <form class="mt-8 grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-2 sm:p-8" @submit.prevent="saveCustomer">
            <div><label class="mb-2 block font-semibold">First name</label><input v-model="customerForm.first_name" :disabled="!canUpdate" maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:opacity-60" /><FieldError :message="customerForm.errors.first_name" /></div><div><label class="mb-2 block font-semibold">Last name</label><input v-model="customerForm.last_name" :disabled="!canUpdate" maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:opacity-60" /><FieldError :message="customerForm.errors.last_name" /></div>
            <div><label class="mb-2 block font-semibold">Email</label><input v-model="customerForm.email" :disabled="!canUpdate" type="email" maxlength="254" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:opacity-60" /><FieldError :message="customerForm.errors.email" /></div><div><label class="mb-2 block font-semibold">Phone</label><input v-model="customerForm.phone" :disabled="!canUpdate" type="tel" maxlength="32" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:opacity-60" /><FieldError :message="customerForm.errors.phone" /></div>
            <div><label class="mb-2 block font-semibold">Status</label><select v-model="customerForm.status" :disabled="!canUpdate" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:opacity-60"><option v-for="status in statuses" :key="status" :value="status">{{ status }}</option></select><FieldError :message="customerForm.errors.status" /></div><div class="sm:col-span-2"><label class="mb-2 block font-semibold">Internal notes</label><textarea v-model="customerForm.notes" :disabled="!canUpdate" rows="5" maxlength="10000" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:opacity-60" /><FieldError :message="customerForm.errors.notes" /></div>
            <button v-if="canUpdate" :disabled="customerForm.processing" class="w-fit bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ customerForm.processing ? 'Saving...' : 'Save customer' }}</button>
        </form>

        <div class="mt-14"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Address book</p><h2 class="mt-3 text-3xl font-semibold">Addresses</h2></div>
        <p v-if="!addresses.length" class="mt-6 text-[#637067]">No addresses yet.</p>
        <form v-for="entry in addressForms" :key="entry.id" class="mt-6 grid gap-4 border border-[#17211b]/15 bg-white p-6 sm:grid-cols-2" @submit.prevent="saveAddress(entry)">
            <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3"><strong>{{ entry.form.label || 'Address' }}</strong><div class="flex flex-wrap gap-2"><span v-if="entry.form.is_default_shipping" class="bg-emerald-100 px-2 py-1 text-xs font-semibold">Shipping default</span><span v-if="entry.form.is_default_billing" class="bg-blue-100 px-2 py-1 text-xs font-semibold">Billing default</span></div></div>
            <div v-for="field in addressFields" :key="field.key"><label class="mb-2 block text-sm font-semibold">{{ field.label }}</label><input v-model="entry.form[field.key]" :disabled="!canUpdate" :required="field.key === 'country_code' || field.key === 'city' || field.key === 'address_line_1'" class="w-full border border-[#17211b]/25 px-3 py-2 disabled:opacity-60" /><FieldError :message="entry.form.errors[field.key]" /></div>
            <div class="flex flex-wrap gap-5 sm:col-span-2"><label><input v-model="entry.form.is_default_shipping" :disabled="!canUpdate" type="checkbox" /> Default shipping</label><label><input v-model="entry.form.is_default_billing" :disabled="!canUpdate" type="checkbox" /> Default billing</label></div>
            <div v-if="canUpdate" class="flex flex-wrap gap-4 sm:col-span-2"><button :disabled="entry.form.processing" class="bg-[#17211b] px-4 py-2 text-sm font-semibold text-white">Save address</button><button v-if="!entry.form.is_default_shipping" type="button" class="text-sm font-semibold text-emerald-800" @click="setDefault(entry.id, 'shipping')">Set shipping default</button><button v-if="!entry.form.is_default_billing" type="button" class="text-sm font-semibold text-blue-800" @click="setDefault(entry.id, 'billing')">Set billing default</button><button type="button" class="text-sm font-semibold text-red-800" @click="deleteAddress(entry.id)">Delete</button></div>
        </form>

        <form v-if="canUpdate" class="mt-8 grid gap-4 border border-dashed border-[#17211b]/30 bg-[#f8f5ed] p-6 sm:grid-cols-2" @submit.prevent="createAddress"><h3 class="text-xl font-semibold sm:col-span-2">Add address</h3>
            <div v-for="field in addressFields" :key="field.key"><label class="mb-2 block text-sm font-semibold">{{ field.label }}</label><input v-model="newAddress[field.key]" :required="field.key === 'country_code' || field.key === 'city' || field.key === 'address_line_1'" class="w-full border border-[#17211b]/25 bg-white px-3 py-2" /><FieldError :message="newAddress.errors[field.key]" /></div>
            <div class="flex flex-wrap gap-5 sm:col-span-2"><label><input v-model="newAddress.is_default_shipping" type="checkbox" /> Default shipping</label><label><input v-model="newAddress.is_default_billing" type="checkbox" /> Default billing</label></div><button :disabled="newAddress.processing" class="w-fit bg-emerald-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ newAddress.processing ? 'Adding...' : 'Add address' }}</button>
        </form>
    </section></AdminLayout>
</template>
