<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const form = useForm({ first_name: '', last_name: '', email: '', phone: '', notes: '' });
const { t } = useI18n();
const submit = () => { if (!form.processing) form.post('/admin/customers'); };
const error = (field: 'first_name' | 'last_name' | 'email' | 'phone' | 'notes') => {
    if (!form.errors[field]) return undefined;
    if (field === 'first_name' && !form.first_name && !form.last_name && !form.email && !form.phone) return t('customers.errors.identity');
    const keys = { first_name: 'firstName', last_name: 'lastName', email: 'email', phone: 'phone', notes: 'notes' } as const;
    return t(`customers.errors.${keys[field]}`);
};
</script>

<template>
    <Head :title="t('customers.create')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-4xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('customers.title'), href: '/admin/customers' }, { label: t('common.create') }]" />
            <PageHeader :title="t('customers.create')" :eyebrow="t('common.allCompany')" :description="t('customers.identityRuleHelp')"><template #actions><Link href="/admin/customers" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50">{{ t('customers.back') }}</Link></template></PageHeader>
            <aside class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3"><p class="text-sm font-semibold text-admin-text">{{ t('customers.createdActive') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('customers.createdActiveHelp') }}</p></aside>

            <form class="admin-card overflow-hidden" @submit.prevent="submit">
                <header class="border-b border-admin-border px-5 py-4 sm:px-6"><p class="admin-label text-admin-secondary">{{ t('customers.identitySection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('customers.identity') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.identityHelp') }}</p></header>
                <div class="grid gap-5 p-5 sm:p-6 md:grid-cols-2">
                    <div><label for="customer-first-name" class="mb-1.5 block text-sm font-semibold">{{ t('customers.firstName') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="customer-first-name" v-model="form.first_name" autofocus autocomplete="given-name" maxlength="120" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.first_name)" /><FieldError :message="error('first_name')" /></div>
                    <div><label for="customer-last-name" class="mb-1.5 block text-sm font-semibold">{{ t('customers.lastName') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="customer-last-name" v-model="form.last_name" autocomplete="family-name" maxlength="120" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.last_name)" /><FieldError :message="error('last_name')" /></div>
                </div>
                <section class="border-t border-admin-border"><header class="px-5 pt-5 sm:px-6"><p class="admin-label text-admin-secondary">{{ t('customers.contactSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('customers.contact') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.contactHelp') }}</p></header><div class="grid gap-5 p-5 sm:p-6 md:grid-cols-2"><div><label for="customer-email" class="mb-1.5 block text-sm font-semibold">{{ t('customers.email') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="customer-email" v-model="form.email" type="email" autocomplete="email" maxlength="254" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.email)" /><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.uniquenessHelp') }}</p><FieldError :message="error('email')" /></div><div><label for="customer-phone" class="mb-1.5 block text-sm font-semibold">{{ t('customers.phone') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="customer-phone" v-model="form.phone" type="tel" autocomplete="tel" maxlength="32" inputmode="tel" placeholder="+51999111222" class="admin-control w-full px-3 font-mono text-sm" :aria-invalid="Boolean(form.errors.phone)" /><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.phoneHelp') }}</p><FieldError :message="error('phone')" /></div></div></section>
                <section class="border-t border-admin-border"><header class="px-5 pt-5 sm:px-6"><p class="admin-label text-admin-secondary">{{ t('customers.notesSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('customers.notes') }}</h2></header><div class="p-5 sm:p-6"><label for="customer-notes" class="sr-only">{{ t('customers.notes') }}</label><textarea id="customer-notes" v-model="form.notes" rows="5" maxlength="10000" class="admin-control w-full px-3 py-2 text-sm" :aria-invalid="Boolean(form.errors.notes)" /><p class="mt-1 text-xs text-admin-neutral">{{ t('customers.notesHelp') }}</p><FieldError :message="error('notes')" /></div></section>
                <footer class="flex flex-col-reverse gap-3 border-t border-admin-border bg-admin-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6"><Link href="/admin/customers" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-5 text-sm font-semibold text-admin-text-secondary">{{ t('common.cancel') }}</Link><button type="submit" :disabled="form.processing" class="min-h-10 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:opacity-60">{{ form.processing ? t('customers.creating') : t('customers.createAction') }}</button></footer>
            </form>
        </section>
    </AdminLayout>
</template>
