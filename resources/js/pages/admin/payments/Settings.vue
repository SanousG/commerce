<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Settings { bank_transfer_enabled: boolean; cash_enabled: boolean; online_enabled: boolean; cod_enabled: boolean; cod_deposit_type: string; cod_deposit_value: string }
const props = defineProps<{ settings: Settings }>();
const page = usePage<PageProps>();
const { t } = useI18n();
const form = useForm({ ...props.settings });
const notice = computed(() => page.props.status ? t('payments.notices.settingsUpdated') : undefined);
const settingsError = computed(() => form.errors.cod_deposit_type ? t('payments.errors.depositType') : form.errors.cod_deposit_value ? t('payments.errors.depositValue') : undefined);
const methods = ['bank_transfer_enabled', 'cash_enabled', 'online_enabled', 'cod_enabled'] as const;
const submit = () => form.transform((data) => ({ ...data, cod_deposit_value: data.cod_deposit_type === 'NONE' ? '0.0000' : data.cod_deposit_value })).patch('/admin/settings/payments', { preserveScroll: true });
</script>

<template>
    <Head :title="t('payments.settingsTitle')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-5xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('shell.settings') }, { label: t('payments.settingsTitle') }]" />
            <PageHeader :title="t('payments.settingsTitle')" :eyebrow="t('payments.storeConfiguration')" :description="t('payments.settingsDescription')"><template #actions><Link href="/admin/payments" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold sm:w-auto">{{ t('payments.viewPayments') }}</Link></template></PageHeader>
            <p v-if="notice" class="rounded-md border border-admin-success-border bg-admin-success-bg p-3 text-sm font-medium text-admin-success" role="status">{{ notice }}</p>

            <form class="grid gap-4" @submit.prevent="submit">
                <section class="admin-card p-4 sm:p-5"><p class="admin-label text-admin-secondary">{{ t('payments.methodsSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('payments.acceptedMethods') }}</h2><p class="mt-1 text-sm text-admin-neutral">{{ t('payments.methodsHelp') }}</p><fieldset class="mt-4 grid gap-3 sm:grid-cols-2"><legend class="sr-only">{{ t('payments.acceptedMethods') }}</legend><label v-for="field in methods" :key="field" class="flex cursor-pointer items-start justify-between gap-4 rounded-md border border-admin-border bg-admin-canvas p-3.5"><span><span class="block text-sm font-semibold">{{ t(`payments.settingsMethods.${field}.title`) }}</span><span class="mt-1 block text-xs leading-5 text-admin-neutral">{{ t(`payments.settingsMethods.${field}.help`) }}</span></span><input v-model="form[field]" type="checkbox" class="mt-0.5 size-4 shrink-0 accent-admin-primary" /></label></fieldset></section>

                <section class="admin-card p-4 sm:p-5"><p class="admin-label text-admin-secondary">{{ t('payments.codSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('payments.codDeposit') }}</h2><p class="mt-1 text-sm text-admin-neutral">{{ t('payments.codHelp') }}</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5" for="cod-type"><span class="admin-label">{{ t('payments.depositType') }}</span><select id="cod-type" v-model="form.cod_deposit_type" class="admin-control w-full px-3 text-sm"><option value="NONE">{{ t('payments.depositTypes.NONE') }}</option><option value="PERCENTAGE">{{ t('payments.depositTypes.PERCENTAGE') }}</option><option value="FIXED_AMOUNT">{{ t('payments.depositTypes.FIXED_AMOUNT') }}</option></select></label><label class="grid gap-1.5" for="cod-value"><span class="admin-label">{{ t('payments.depositValue') }}</span><div class="relative"><input id="cod-value" v-model="form.cod_deposit_value" :disabled="form.cod_deposit_type === 'NONE'" inputmode="decimal" class="admin-control w-full px-3 pr-10 font-mono text-sm disabled:bg-admin-canvas disabled:text-admin-neutral" /><span v-if="form.cod_deposit_type === 'PERCENTAGE'" class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-admin-neutral">%</span></div></label></div><FieldError :message="settingsError" /><p class="mt-3 text-xs text-admin-neutral">{{ form.cod_deposit_type === 'PERCENTAGE' ? t('payments.percentageHelp') : form.cod_deposit_type === 'FIXED_AMOUNT' ? t('payments.fixedHelp') : t('payments.noneHelp') }}</p></section>

                <aside class="rounded-md border border-admin-info-border bg-admin-info-bg p-4"><p class="text-sm font-semibold text-admin-text">{{ t('payments.snapshotTitle') }}</p><p class="mt-1 text-xs leading-5 text-admin-text-secondary">{{ t('payments.snapshotHelp') }}</p></aside>
                <div class="flex justify-end"><button :disabled="form.processing || !form.isDirty" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">{{ form.processing ? t('common.saving') : t('payments.saveSettings') }}</button></div>
            </form>
        </section>
    </AdminLayout>
</template>
