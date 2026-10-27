<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const { t } = useI18n();
const form = useForm({ name: '', code: '', contact_name: '', email: '', phone: '', website: '', notes: '' });
const submit = () => { if (!form.processing) form.post('/admin/suppliers'); };
const error = (field: keyof typeof form.errors) => form.errors[field] ? t(`procurement.errors.${field}`) : undefined;
</script>

<template>
    <Head :title="t('procurement.suppliers.create')" />
    <AdminLayout><section class="mx-auto grid w-full max-w-4xl gap-5 sm:gap-6">
        <Breadcrumbs :items="[{ label: t('nav.procurement') }, { label: t('procurement.suppliers.title'), href: '/admin/suppliers' }, { label: t('common.create') }]" />
        <PageHeader :title="t('procurement.suppliers.create')" :eyebrow="t('common.allCompany')" :description="t('procurement.suppliers.createDescription')"><template #actions><Link href="/admin/suppliers" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('procurement.backSuppliers') }}</Link></template></PageHeader>
        <aside class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3"><p class="text-sm font-semibold">{{ t('procurement.suppliers.activeTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('procurement.suppliers.activeHelp') }}</p></aside>
        <form class="admin-card overflow-hidden" @submit.prevent="submit"><header class="border-b border-admin-border px-5 py-4 sm:px-6"><p class="admin-label text-admin-secondary">{{ t('procurement.suppliers.section') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('procurement.suppliers.details') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('procurement.suppliers.detailsHelp') }}</p></header>
            <div class="grid gap-5 p-5 sm:p-6 md:grid-cols-2">
                <div><label for="supplier-name" class="mb-1.5 block text-sm font-semibold">{{ t('procurement.name') }} *</label><input id="supplier-name" v-model="form.name" required autofocus maxlength="160" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.name)" /><FieldError :message="error('name')" /></div>
                <div><label for="supplier-code" class="mb-1.5 block text-sm font-semibold">{{ t('procurement.code') }} *</label><input id="supplier-code" v-model="form.code" required maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" class="admin-control w-full px-3 font-mono text-sm uppercase" :aria-invalid="Boolean(form.errors.code)" /><p class="mt-1 text-xs text-admin-neutral">{{ t('procurement.codeHelp') }}</p><FieldError :message="error('code')" /></div>
                <div><label for="supplier-contact" class="mb-1.5 block text-sm font-semibold">{{ t('procurement.contactName') }}</label><input id="supplier-contact" v-model="form.contact_name" maxlength="160" autocomplete="name" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.contact_name)" /><FieldError :message="error('contact_name')" /></div>
                <div><label for="supplier-email" class="mb-1.5 block text-sm font-semibold">{{ t('procurement.email') }}</label><input id="supplier-email" v-model="form.email" type="email" maxlength="254" autocomplete="email" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.email)" /><FieldError :message="error('email')" /></div>
                <div><label for="supplier-phone" class="mb-1.5 block text-sm font-semibold">{{ t('procurement.phone') }}</label><input id="supplier-phone" v-model="form.phone" type="tel" maxlength="40" autocomplete="tel" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.phone)" /><FieldError :message="error('phone')" /></div>
                <div><label for="supplier-website" class="mb-1.5 block text-sm font-semibold">{{ t('procurement.website') }}</label><input id="supplier-website" v-model="form.website" type="url" maxlength="2048" placeholder="https://" autocomplete="url" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.website)" /><FieldError :message="error('website')" /></div>
                <div class="md:col-span-2"><label for="supplier-notes" class="mb-1.5 block text-sm font-semibold">{{ t('procurement.notes') }}</label><textarea id="supplier-notes" v-model="form.notes" rows="4" maxlength="10000" class="admin-control w-full px-3 py-2 text-sm" :aria-invalid="Boolean(form.errors.notes)" /><FieldError :message="error('notes')" /></div>
            </div><footer class="flex flex-col-reverse gap-2 border-t border-admin-border bg-admin-canvas px-5 py-4 sm:flex-row sm:justify-end sm:px-6"><Link href="/admin/suppliers" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('common.cancel') }}</Link><button :disabled="form.processing" class="min-h-10 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? t('common.creating') : t('procurement.suppliers.create') }}</button></footer>
        </form>
    </section></AdminLayout>
</template>
