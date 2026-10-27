<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';
import AddressFields from './AddressFields.vue';

type CustomerStatus = 'ACTIVE' | 'INACTIVE' | 'BLOCKED' | 'ARCHIVED';
interface Customer { id: string; first_name: string | null; last_name: string | null; display_name: string; email: string | null; phone: string | null; status: CustomerStatus; notes: string | null }
interface Address { id: string; label: string | null; recipient_name: string | null; phone: string | null; country_code: string; region: string | null; city: string; district: string | null; address_line_1: string; address_line_2: string | null; postal_code: string | null; reference: string | null; is_default_shipping: boolean; is_default_billing: boolean }

const props = defineProps<{ customer: Customer; addresses: Address[]; statuses: CustomerStatus[]; defaultCountry: string }>();
const page = usePage<PageProps>();
const { t } = useI18n();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('customers.update'));
const canArchive = computed(() => permissions.value.includes('customers.archive'));
const customerForm = useForm({ first_name: props.customer.first_name ?? '', last_name: props.customer.last_name ?? '', email: props.customer.email ?? '', phone: props.customer.phone ?? '', status: props.customer.status, notes: props.customer.notes ?? '' });
const emptyAddress = () => ({ label: '', recipient_name: '', phone: '', country_code: props.defaultCountry, region: '', city: '', district: '', address_line_1: '', address_line_2: '', postal_code: '', reference: '', is_default_shipping: false, is_default_billing: false });
const newAddress = useForm(emptyAddress());
const addressForms = props.addresses.map((address) => ({ id: address.id, form: useForm({ label: address.label ?? '', recipient_name: address.recipient_name ?? '', phone: address.phone ?? '', country_code: address.country_code, region: address.region ?? '', city: address.city, district: address.district ?? '', address_line_1: address.address_line_1, address_line_2: address.address_line_2 ?? '', postal_code: address.postal_code ?? '', reference: address.reference ?? '', is_default_shipping: address.is_default_shipping, is_default_billing: address.is_default_billing }) }));
const lifecycle = useForm({});
const addressAction = ref<string | null>(null);
const localizedStatus = computed(() => {
    const keys: Record<string, string> = { 'Customer updated.': 'customers.notices.updated', 'Address created.': 'customers.notices.addressCreated', 'Address updated.': 'customers.notices.addressUpdated', 'Address deleted.': 'customers.notices.addressDeleted', 'Shipping default address updated.': 'customers.notices.defaultUpdated', 'Billing default address updated.': 'customers.notices.defaultUpdated' };
    return page.props.status ? t(keys[page.props.status] ?? 'customers.notices.completed') : undefined;
});
const customerError = (field: 'first_name' | 'last_name' | 'email' | 'phone' | 'status' | 'notes') => {
    if (!customerForm.errors[field]) return undefined;
    if (field === 'first_name' && !customerForm.first_name && !customerForm.last_name && !customerForm.email && !customerForm.phone) return t('customers.errors.identity');
    const keys = { first_name: 'firstName', last_name: 'lastName', email: 'email', phone: 'phone', status: 'status', notes: 'notes' } as const;
    return t(`customers.errors.${keys[field]}`);
};
const saveCustomer = () => { if (canUpdate.value && !customerForm.processing) customerForm.put(`/admin/customers/${props.customer.id}`, { preserveScroll: true }); };
const createAddress = () => { if (!newAddress.processing) newAddress.post(`/admin/customers/${props.customer.id}/addresses`, { preserveScroll: true, onSuccess: () => newAddress.reset() }); };
const saveAddress = (entry: (typeof addressForms)[number]) => { if (!entry.form.processing) entry.form.put(`/admin/customers/${props.customer.id}/addresses/${entry.id}`, { preserveScroll: true }); };
const setDefault = (addressId: string, type: 'shipping' | 'billing') => {
    if (addressAction.value) return;
    addressAction.value = `${addressId}-${type}`;
    router.post(`/admin/customers/${props.customer.id}/addresses/${addressId}/default/${type}`, {}, { preserveScroll: true, onFinish: () => { addressAction.value = null; } });
};
const deleteAddress = (addressId: string) => {
    if (addressAction.value || !window.confirm(t('customers.deleteAddressConfirm'))) return;
    addressAction.value = `${addressId}-delete`;
    router.delete(`/admin/customers/${props.customer.id}/addresses/${addressId}`, { preserveScroll: true, onFinish: () => { addressAction.value = null; } });
};
const archive = () => { if (!lifecycle.processing && window.confirm(t('customers.archiveConfirm', { name: props.customer.display_name }))) lifecycle.post(`/admin/customers/${props.customer.id}/archive`); };
</script>

<template>
    <Head :title="t('customers.editTitle')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-5xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('customers.title'), href: '/admin/customers' }, { label: customer.display_name, href: `/admin/customers/${customer.id}` }, { label: t('common.edit') }]" />
            <PageHeader :title="t('customers.editTitle')" :eyebrow="customer.display_name" :description="t('customers.editDescription')"><template #actions><div class="flex flex-wrap gap-2"><Link :href="`/admin/customers/${customer.id}`" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary">{{ t('common.view') }}</Link><Link href="/admin/customers" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary">{{ t('customers.back') }}</Link></div></template></PageHeader>
            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />
            <aside v-if="!canUpdate" class="rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3"><p class="text-sm font-semibold">{{ t('customers.readOnlyTitle') }}</p><p class="mt-0.5 text-xs text-admin-text-secondary">{{ t('customers.readOnlyHelp') }}</p></aside>
            <aside v-else-if="customer.status === 'ARCHIVED'" class="rounded-md border border-admin-border bg-admin-canvas px-4 py-3"><p class="text-sm font-semibold">{{ t('customers.archivedTitle') }}</p><p class="mt-0.5 text-xs text-admin-text-secondary">{{ t('customers.archivedHelp') }}</p></aside>

            <form class="admin-card overflow-hidden" @submit.prevent="saveCustomer">
                <header class="flex items-start justify-between gap-3 border-b border-admin-border px-5 py-4 sm:px-6"><div><p class="admin-label text-admin-secondary">{{ t('customers.detailsSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('customers.customerDetails') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.customerDetailsHelp') }}</p></div><StatusBadge :status="customerForm.status" /></header>
                <div class="grid gap-5 p-5 sm:p-6 md:grid-cols-2">
                    <div><label for="edit-first-name" class="mb-1.5 block text-sm font-semibold">{{ t('customers.firstName') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="edit-first-name" v-model="customerForm.first_name" :disabled="!canUpdate" autocomplete="given-name" maxlength="120" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" :aria-invalid="Boolean(customerForm.errors.first_name)" /><FieldError :message="customerError('first_name')" /></div>
                    <div><label for="edit-last-name" class="mb-1.5 block text-sm font-semibold">{{ t('customers.lastName') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="edit-last-name" v-model="customerForm.last_name" :disabled="!canUpdate" autocomplete="family-name" maxlength="120" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" :aria-invalid="Boolean(customerForm.errors.last_name)" /><FieldError :message="customerError('last_name')" /></div>
                    <div><label for="edit-email" class="mb-1.5 block text-sm font-semibold">{{ t('customers.email') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="edit-email" v-model="customerForm.email" :disabled="!canUpdate" type="email" autocomplete="email" maxlength="254" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" :aria-invalid="Boolean(customerForm.errors.email)" /><FieldError :message="customerError('email')" /></div>
                    <div><label for="edit-phone" class="mb-1.5 block text-sm font-semibold">{{ t('customers.phone') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="edit-phone" v-model="customerForm.phone" :disabled="!canUpdate" type="tel" autocomplete="tel" maxlength="32" class="admin-control w-full px-3 font-mono text-sm disabled:bg-admin-canvas" :aria-invalid="Boolean(customerForm.errors.phone)" /><FieldError :message="customerError('phone')" /></div>
                    <div><label for="edit-status" class="mb-1.5 block text-sm font-semibold">{{ t('common.status') }} <span class="text-admin-danger">*</span></label><select id="edit-status" v-model="customerForm.status" :disabled="!canUpdate" required class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" :aria-invalid="Boolean(customerForm.errors.status)"><option v-for="value in statuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select><FieldError :message="customerError('status')" /></div>
                    <div class="md:col-span-2"><label for="edit-notes" class="mb-1.5 block text-sm font-semibold">{{ t('customers.notes') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><textarea id="edit-notes" v-model="customerForm.notes" :disabled="!canUpdate" rows="4" maxlength="10000" class="admin-control w-full px-3 py-2 text-sm disabled:bg-admin-canvas" :aria-invalid="Boolean(customerForm.errors.notes)" /><FieldError :message="customerError('notes')" /></div>
                </div><footer v-if="canUpdate" class="flex justify-end border-t border-admin-border bg-admin-canvas px-5 py-4 sm:px-6"><button type="submit" :disabled="customerForm.processing" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:opacity-60 sm:w-auto">{{ customerForm.processing ? t('common.saving') : t('customers.saveCustomer') }}</button></footer>
            </form>

            <section><div class="mb-3"><p class="admin-label text-admin-secondary">02 · {{ t('customers.addressBook') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('customers.addresses') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.addressRequiredHelp') }}</p></div>
                <div v-if="addressForms.length" class="grid gap-4"><form v-for="entry in addressForms" :key="entry.id" class="admin-card overflow-hidden" @submit.prevent="saveAddress(entry)"><header class="flex flex-wrap items-center justify-between gap-3 border-b border-admin-border px-5 py-3"><h3 class="text-sm font-semibold">{{ entry.form.label || t('customers.address') }}</h3><div class="flex flex-wrap gap-1"><span v-if="entry.form.is_default_shipping" class="rounded border border-admin-success-border bg-admin-success-bg px-1.5 py-0.5 text-[10px] font-semibold text-admin-success">{{ t('customers.shippingDefault') }}</span><span v-if="entry.form.is_default_billing" class="rounded border border-admin-info-border bg-admin-info-bg px-1.5 py-0.5 text-[10px] font-semibold text-admin-info">{{ t('customers.billingDefault') }}</span></div></header><div class="grid gap-4 p-5 sm:grid-cols-2"><AddressFields :form="entry.form" :prefix="`address-${entry.id}`" :disabled="!canUpdate" /></div><footer v-if="canUpdate" class="flex flex-wrap items-center justify-end gap-3 border-t border-admin-border bg-admin-canvas px-5 py-3"><button v-if="!entry.form.is_default_shipping" type="button" :disabled="Boolean(addressAction)" class="admin-link text-xs disabled:opacity-50" @click="setDefault(entry.id, 'shipping')">{{ t('customers.setShippingDefault') }}</button><button v-if="!entry.form.is_default_billing" type="button" :disabled="Boolean(addressAction)" class="admin-link text-xs disabled:opacity-50" @click="setDefault(entry.id, 'billing')">{{ t('customers.setBillingDefault') }}</button><button type="button" :disabled="Boolean(addressAction)" class="text-xs font-semibold text-admin-danger disabled:opacity-50" @click="deleteAddress(entry.id)">{{ t('customers.deleteAddress') }}</button><button type="submit" :disabled="entry.form.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-xs font-semibold text-white disabled:opacity-60">{{ entry.form.processing ? t('common.saving') : t('customers.saveAddress') }}</button></footer></form></div>
                <EmptyState v-else :title="t('customers.noAddresses')" :description="canUpdate ? t('customers.addressRequiredHelp') : t('customers.noAddressesHelp')" />
            </section>

            <form v-if="canUpdate" class="admin-card overflow-hidden" @submit.prevent="createAddress"><header class="border-b border-admin-border px-5 py-4"><p class="admin-label text-admin-secondary">03 · {{ t('customers.addAddress') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('customers.addAddress') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.addressRequiredHelp') }}</p></header><div class="grid gap-4 p-5 sm:grid-cols-2"><AddressFields :form="newAddress" prefix="new-address" /></div><footer class="flex justify-end border-t border-admin-border bg-admin-canvas px-5 py-4"><button type="submit" :disabled="newAddress.processing" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:opacity-60 sm:w-auto">{{ newAddress.processing ? t('customers.addingAddress') : t('customers.addAddress') }}</button></footer></form>

            <section v-if="canArchive && customer.status !== 'ARCHIVED'" class="rounded-lg border border-admin-danger-border bg-admin-danger-bg p-5"><p class="admin-label text-admin-danger">{{ t('customers.lifecycleTitle') }}</p><div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><p class="max-w-2xl text-xs leading-5 text-admin-text-secondary">{{ t('customers.lifecycleHelp') }}</p><button type="button" :disabled="lifecycle.processing" class="min-h-9 shrink-0 rounded-md border border-admin-danger-border bg-white px-4 text-sm font-semibold text-admin-danger disabled:opacity-60" @click="archive">{{ lifecycle.processing ? t('common.archiving') : t('common.archive') }}</button></div></section>
        </section>
    </AdminLayout>
</template>
