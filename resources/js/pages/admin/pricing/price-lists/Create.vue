<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../../components/admin/PageHeader.vue';
import FieldError from '../../../../components/FieldError.vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';

const props = defineProps<{ currencies: string[]; defaultCurrency: string }>();
const form = useForm({ name: '', code: '', currency: props.defaultCurrency });
const { t } = useI18n();

const localizedError = (field: 'name' | 'code' | 'currency') => {
    if (!form.errors[field]) return undefined;
    return t(`pricing.createPage.errors.${field}`);
};
const submit = () => {
    if (form.processing) return;
    form.post('/admin/pricing/price-lists');
};
</script>

<template>
    <Head :title="t('pricing.createPage.title')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-5xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.merchandising') }, { label: t('pricing.title'), href: '/admin/pricing' }, { label: t('common.create') }]" />
            <PageHeader :title="t('pricing.createPage.title')" :eyebrow="t('common.allCompany')" :description="t('pricing.createPage.description')">
                <template #actions><Link href="/admin/pricing" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50">{{ t('pricing.back') }}</Link></template>
            </PageHeader>

            <aside class="flex gap-3 rounded-lg border border-admin-info-border bg-admin-info-bg px-4 py-3" :aria-label="t('pricing.createPage.activeTitle')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-info" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="M12 11v5m0-8h.01" /></svg>
                <div><p class="text-xs font-semibold text-admin-text">{{ t('pricing.createPage.activeTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('pricing.createPage.activeHelp') }}</p></div>
            </aside>

            <form class="admin-card overflow-hidden" @submit.prevent="submit">
                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div><p class="admin-label text-admin-secondary">{{ t('pricing.createPage.section') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('pricing.createPage.settings') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('pricing.createPage.settingsHelp') }}</p></div>
                    <div class="grid min-w-0 gap-5 lg:grid-cols-2">
                        <div class="lg:col-span-2">
                            <div class="mb-1.5 flex items-end justify-between gap-3"><label for="price-list-name" class="text-sm font-semibold text-admin-text">{{ t('pricing.name') }} <span class="text-admin-danger" aria-hidden="true">*</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ form.name.length }} / 120</span></div>
                            <input id="price-list-name" v-model="form.name" required maxlength="120" autofocus autocomplete="off" class="admin-control w-full px-3 py-2 text-sm" :class="form.errors.name ? 'border-admin-danger' : ''" :aria-invalid="form.errors.name ? 'true' : undefined" aria-describedby="price-list-name-help price-list-name-error" />
                            <p id="price-list-name-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('pricing.createPage.nameHelp') }}</p><FieldError id="price-list-name-error" :message="localizedError('name')" />
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3"><label for="price-list-code" class="text-sm font-semibold text-admin-text">{{ t('pricing.code') }} <span class="text-admin-danger" aria-hidden="true">*</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ form.code.length }} / 40</span></div>
                            <input id="price-list-code" v-model="form.code" required maxlength="40" autocomplete="off" spellcheck="false" placeholder="WHOLESALE" class="admin-control w-full px-3 py-2 font-mono text-sm uppercase" :class="form.errors.code ? 'border-admin-danger' : ''" :aria-invalid="form.errors.code ? 'true' : undefined" aria-describedby="price-list-code-help price-list-code-error" />
                            <p id="price-list-code-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('pricing.createPage.codeHelp') }}</p><FieldError id="price-list-code-error" :message="localizedError('code')" />
                        </div>
                        <div>
                            <label for="price-list-currency" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('pricing.currency') }} <span class="text-admin-danger" aria-hidden="true">*</span></label>
                            <select id="price-list-currency" v-model="form.currency" required class="admin-control w-full px-3 py-2 text-sm" :class="form.errors.currency ? 'border-admin-danger' : ''" :aria-invalid="form.errors.currency ? 'true' : undefined" aria-describedby="price-list-currency-help price-list-currency-error"><option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option></select>
                            <p id="price-list-currency-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('pricing.createPage.currencyHelp') }}</p><FieldError id="price-list-currency-error" :message="localizedError('currency')" />
                        </div>
                    </div>
                </section>
                <footer class="flex flex-col-reverse gap-2 border-t border-admin-border bg-admin-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                    <Link href="/admin/pricing" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 sm:w-auto">{{ t('common.cancel') }}</Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">{{ form.processing ? t('pricing.createPage.creating') : t('pricing.create') }}</button>
                </footer>
            </form>
        </section>
    </AdminLayout>
</template>
