<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const { t } = useI18n();
const form = useForm({ name: '', currency: 'PEN', timezone: 'America/Lima', locale: 'es_PE' });
const currencies = ['PEN', 'USD', 'EUR'];
const timezoneSuggestions = ['America/Lima', 'America/New_York', 'Europe/Madrid', 'UTC'];
const localeSuggestions = ['es_PE', 'en_US', 'es_ES'];

const submit = () => {
    if (form.processing) return;
    form.post('/admin/stores', { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('stores.createPage.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-4xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('stores.title'), href: '/admin/stores' }, { label: t('stores.createPage.title') }]" />
            <PageHeader :title="t('stores.createPage.title')" :eyebrow="t('stores.createPage.eyebrow')" :description="t('stores.createPage.description')" />

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_17rem] lg:items-start">
                <form class="admin-card min-w-0 p-4 sm:p-5" @submit.prevent="submit">
                    <div class="border-b border-admin-border pb-4">
                        <p class="admin-label">{{ t('stores.createPage.section') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.createPage.details') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.createPage.detailsHelp') }}</p>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label for="store-name" class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('stores.name') }}</span><input id="store-name" v-model="form.name" type="text" required autofocus maxlength="120" autocomplete="organization" :placeholder="t('stores.namePlaceholder')" aria-describedby="store-name-help store-name-error" :aria-invalid="Boolean(form.errors.name)" class="admin-control w-full px-3 text-sm" /></label>
                        <div class="sm:col-span-2"><p id="store-name-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.nameHelp') }}</p><FieldError id="store-name-error" :message="form.errors.name" /></div>

                        <label for="store-currency" class="grid gap-1.5"><span class="admin-label">{{ t('stores.currency') }}</span><select id="store-currency" v-model="form.currency" required aria-describedby="store-currency-help store-currency-error" :aria-invalid="Boolean(form.errors.currency)" class="admin-control w-full px-3 text-sm"><option v-for="currency in currencies" :key="currency" :value="currency">{{ t(`stores.currencies.${currency}`) }}</option></select><span id="store-currency-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.currencyHelp') }}</span><FieldError id="store-currency-error" :message="form.errors.currency" /></label>
                        <label for="store-timezone" class="grid gap-1.5"><span class="admin-label">{{ t('stores.timezone') }}</span><input id="store-timezone" v-model="form.timezone" type="text" list="store-timezone-options" required placeholder="America/Lima" autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="store-timezone-help store-timezone-error" :aria-invalid="Boolean(form.errors.timezone)" class="admin-control w-full px-3 text-sm" /><span id="store-timezone-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.timezoneHelp') }}</span><FieldError id="store-timezone-error" :message="form.errors.timezone" /></label>
                        <label for="store-locale" class="grid gap-1.5"><span class="admin-label">{{ t('stores.locale') }}</span><input id="store-locale" v-model="form.locale" type="text" list="store-locale-options" required maxlength="10" placeholder="es_PE" autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="store-locale-help store-locale-error" :aria-invalid="Boolean(form.errors.locale)" class="admin-control w-full px-3 text-sm" /><span id="store-locale-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.localeHelp') }}</span><FieldError id="store-locale-error" :message="form.errors.locale" /></label>
                    </div>

                    <datalist id="store-timezone-options"><option v-for="timezone in timezoneSuggestions" :key="timezone" :value="timezone" /></datalist>
                    <datalist id="store-locale-options"><option v-for="locale in localeSuggestions" :key="locale" :value="locale" /></datalist>

                    <div class="mt-5 flex flex-col-reverse gap-2 border-t border-admin-border pt-4 sm:flex-row sm:justify-end">
                        <Link href="/admin/stores" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('common.cancel') }}</Link>
                        <button type="submit" :disabled="form.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60">{{ form.processing ? t('stores.createPage.creating') : t('stores.create') }}</button>
                    </div>
                </form>

                <aside class="admin-card p-4" aria-labelledby="store-creation-outcome">
                    <p class="admin-label">{{ t('stores.createPage.outcomeSection') }}</p>
                    <h2 id="store-creation-outcome" class="mt-1 text-sm font-semibold text-admin-primary">{{ t('stores.createPage.outcome') }}</h2>
                    <ul class="mt-3 grid gap-2 text-xs leading-5 text-admin-text-secondary">
                        <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-admin-secondary" aria-hidden="true" />{{ t('stores.createPage.tenantOutcome') }}</li>
                        <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-admin-secondary" aria-hidden="true" />{{ t('stores.createPage.activeOutcome') }}</li>
                        <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-admin-secondary" aria-hidden="true" />{{ t('stores.createPage.firstOutcome') }}</li>
                    </ul>
                </aside>
            </div>
        </section>
    </AdminLayout>
</template>
