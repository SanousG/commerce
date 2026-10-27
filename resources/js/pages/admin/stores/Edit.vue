<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface StoreData { id: string; name: string; slug: string; status: 'ACTIVE' | 'INACTIVE'; currency: string; timezone: string; locale: string }
const props = defineProps<{ store: StoreData }>();
const page = usePage<PageProps>();
const { t } = useI18n();
const form = useForm({ name: props.store.name, status: props.store.status, currency: props.store.currency, timezone: props.store.timezone, locale: props.store.locale });
const isCurrentStore = computed(() => page.props.currentStore?.id === props.store.id);
const currencies = ['PEN', 'USD', 'EUR'];

const submit = () => {
    if (form.processing) return;
    if (props.store.status === 'ACTIVE' && form.status === 'INACTIVE' && !window.confirm(t(isCurrentStore.value ? 'stores.editPage.deactivateCurrentConfirm' : 'stores.editPage.deactivateConfirm', { name: props.store.name }))) return;
    form.patch(`/admin/stores/${props.store.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('stores.editPage.head', { name: store.name })" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-4xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('stores.title'), href: '/admin/stores' }, { label: t('stores.editPage.title') }]" />
            <PageHeader :title="t('stores.editPage.title')" :eyebrow="store.slug" :description="t('stores.editPage.description')">
                <template #actions><Link :href="`/admin/stores/${store.id}/settings`" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold sm:w-auto">{{ t('stores.openSettings') }}</Link></template>
            </PageHeader>

            <div v-if="isCurrentStore" class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3 text-xs leading-5 text-admin-info"><p class="font-semibold">{{ t('stores.editPage.currentTitle') }}</p><p class="mt-0.5">{{ t('stores.editPage.currentHelp') }}</p></div>

            <form class="admin-card min-w-0 p-4 sm:p-5" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-3 border-b border-admin-border pb-4">
                    <div><p class="admin-label">{{ t('stores.editPage.section') }}</p><h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.editPage.details') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.editPage.detailsHelp') }}</p></div>
                    <StatusBadge :status="store.status" />
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label for="store-name" class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('stores.name') }}</span><input id="store-name" v-model="form.name" required maxlength="120" autocomplete="organization" aria-describedby="store-name-help store-name-error" :aria-invalid="Boolean(form.errors.name)" class="admin-control w-full px-3 text-sm" /><span id="store-name-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.nameHelp') }}</span><FieldError id="store-name-error" :message="form.errors.name" /></label>
                    <label for="store-status" class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select id="store-status" v-model="form.status" required aria-describedby="store-status-help store-status-error" :aria-invalid="Boolean(form.errors.status)" class="admin-control w-full px-3 text-sm"><option value="ACTIVE">{{ t('status.ACTIVE') }}</option><option value="INACTIVE">{{ t('status.INACTIVE') }}</option></select><span id="store-status-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.editPage.statusHelp') }}</span><FieldError id="store-status-error" :message="form.errors.status" /></label>
                    <label for="store-currency" class="grid gap-1.5"><span class="admin-label">{{ t('stores.currency') }}</span><select id="store-currency" v-model="form.currency" required aria-describedby="store-currency-help store-currency-error" :aria-invalid="Boolean(form.errors.currency)" class="admin-control w-full px-3 text-sm"><option v-for="currency in currencies" :key="currency" :value="currency">{{ t(`stores.currencies.${currency}`) }}</option></select><span id="store-currency-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.currencyHelp') }}</span><FieldError id="store-currency-error" :message="form.errors.currency" /></label>
                    <label for="store-timezone" class="grid gap-1.5"><span class="admin-label">{{ t('stores.timezone') }}</span><input id="store-timezone" v-model="form.timezone" required placeholder="America/Lima" aria-describedby="store-timezone-help store-timezone-error" :aria-invalid="Boolean(form.errors.timezone)" class="admin-control w-full px-3 text-sm" /><span id="store-timezone-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.timezoneHelp') }}</span><FieldError id="store-timezone-error" :message="form.errors.timezone" /></label>
                    <label for="store-locale" class="grid gap-1.5"><span class="admin-label">{{ t('stores.locale') }}</span><input id="store-locale" v-model="form.locale" required maxlength="10" placeholder="es_PE" aria-describedby="store-locale-help store-locale-error" :aria-invalid="Boolean(form.errors.locale)" class="admin-control w-full px-3 text-sm" /><span id="store-locale-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.localeHelp') }}</span><FieldError id="store-locale-error" :message="form.errors.locale" /></label>
                </div>
                <div class="mt-5 flex flex-col-reverse gap-2 border-t border-admin-border pt-4 sm:flex-row sm:justify-end"><Link href="/admin/stores" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('common.cancel') }}</Link><button type="submit" :disabled="form.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60">{{ form.processing ? t('stores.editPage.saving') : t('stores.editPage.save') }}</button></div>
            </form>
        </section>
    </AdminLayout>
</template>
