<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface TaxCategory { id: string; code: string; name: string; status: string }
interface TaxRate { id: string; tax_category_id: string; name: string; rate: string; valid_from: string | null; valid_until: string | null; status: string }

const props = defineProps<{ categories: TaxCategory[]; rates: TaxRate[]; categoryCodes: string[]; statuses: string[] }>();
const page = usePage<PageProps>();
const { t, te } = useI18n();
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('taxes.manage') ?? false);
const notice = computed(() => {
    const messages: Record<string, string> = {
        'Tax category created.': 'taxes.notices.categoryCreated',
        'Tax category updated.': 'taxes.notices.categoryUpdated',
        'Tax rate created.': 'taxes.notices.rateCreated',
        'Tax rate updated.': 'taxes.notices.rateUpdated',
    };
    return page.props.status ? t(messages[page.props.status] ?? 'taxes.notices.completed') : undefined;
});

const categoryForm = useForm({ code: props.categoryCodes[0] ?? '', name: '', status: props.statuses[0] ?? '' });
const categoryForms = props.categories.map((category) => ({ id: category.id, form: useForm({ code: category.code, name: category.name, status: category.status }) }));
const rateForm = useForm({ tax_category_id: props.categories[0]?.id ?? '', name: '', rate: '', valid_from: '', valid_until: '', status: props.statuses[0] ?? '' });
const rateForms = props.rates.map((rate) => ({ id: rate.id, form: useForm({ tax_category_id: rate.tax_category_id, name: rate.name, rate: rate.rate, valid_from: rate.valid_from ?? '', valid_until: rate.valid_until ?? '', status: rate.status }) }));

const codeLabel = (code: string) => te(`taxes.codes.${code}`) ? t(`taxes.codes.${code}`) : code;
const fieldError = (errors: Partial<Record<string, string>>, field: string, fallback: string) => {
    if (!errors[field]) return undefined;
    if (field === 'rate' && errors[field]?.includes('zero rate')) return t('taxes.errors.zeroRate');
    if (field === 'valid_from' && errors[field]?.includes('overlap')) return t('taxes.errors.overlap');
    return t(fallback);
};
const createCategory = () => categoryForm.post('/admin/tax-categories', {
    preserveScroll: true,
    onSuccess: () => categoryForm.reset('name'),
});
const updateCategory = (entry: (typeof categoryForms)[number]) => entry.form.patch(`/admin/tax-categories/${entry.id}`, { preserveScroll: true });
const createRate = () => rateForm.post('/admin/tax-rates', {
    preserveScroll: true,
    onSuccess: () => rateForm.reset('name', 'rate', 'valid_from', 'valid_until'),
});
const updateRate = (entry: (typeof rateForms)[number]) => entry.form.patch(`/admin/tax-rates/${entry.id}`, { preserveScroll: true });
</script>

<template>
    <Head :title="t('taxes.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.compliance') }, { label: t('taxes.title') }]" />
            <PageHeader :title="t('taxes.title')" :eyebrow="t('common.allCompany')" :description="t('taxes.description')" />
            <p v-if="notice" class="rounded-md border border-admin-success-border bg-admin-success-bg p-3 text-sm font-medium text-admin-success" role="status">{{ notice }}</p>
            <aside v-if="!canManage" class="rounded-md border border-admin-info-border bg-admin-info-bg p-4">
                <p class="text-sm font-semibold text-admin-text">{{ t('taxes.readOnlyTitle') }}</p>
                <p class="mt-1 text-xs leading-5 text-admin-text-secondary">{{ t('taxes.readOnlyHelp') }}</p>
            </aside>

            <section aria-labelledby="tax-categories-heading" class="grid gap-3">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="admin-label text-admin-secondary">{{ t('taxes.categoriesSection') }}</p><h2 id="tax-categories-heading" class="mt-1 text-lg font-semibold">{{ t('taxes.categories') }}</h2><p class="mt-1 max-w-3xl text-sm text-admin-neutral">{{ t('taxes.categoriesHelp') }}</p></div>
                    <p class="font-mono text-xs text-admin-neutral">{{ t('taxes.configuredCount', { count: categories.length }) }}</p>
                </div>

                <template v-if="categoryForms.length">
                    <form v-for="entry in categoryForms" :id="`category-form-${entry.id}`" :key="`category-form-${entry.id}`" class="hidden" @submit.prevent="updateCategory(entry)" />
                    <div class="admin-card hidden overflow-hidden md:block">
                        <table class="admin-table w-full table-fixed text-left text-xs">
                            <thead><tr><th class="w-[18%] p-3">{{ t('taxes.code') }}</th><th class="w-[42%] p-3">{{ t('taxes.name') }}</th><th class="w-[20%] p-3">{{ t('common.status') }}</th><th class="w-[20%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead>
                            <tbody class="divide-y divide-admin-border">
                                <template v-for="entry in categoryForms" :key="entry.id">
                                    <tr>
                                        <td class="p-3"><label :for="`category-code-${entry.id}`" class="sr-only">{{ t('taxes.code') }}</label><select :id="`category-code-${entry.id}`" v-model="entry.form.code" :form="`category-form-${entry.id}`" :disabled="!canManage" required class="admin-control w-full px-2 font-mono text-xs disabled:bg-admin-canvas"><option v-for="code in categoryCodes" :key="code" :value="code">{{ codeLabel(code) }}</option></select></td>
                                        <td class="p-3"><label :for="`category-name-${entry.id}`" class="sr-only">{{ t('taxes.name') }}</label><input :id="`category-name-${entry.id}`" v-model="entry.form.name" :form="`category-form-${entry.id}`" :disabled="!canManage" required maxlength="120" class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas" /></td>
                                        <td class="p-3"><label :for="`category-status-${entry.id}`" class="sr-only">{{ t('common.status') }}</label><select :id="`category-status-${entry.id}`" v-model="entry.form.status" :form="`category-form-${entry.id}`" :disabled="!canManage" required class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas"><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select></td>
                                        <td class="p-3 text-right"><button v-if="canManage" :form="`category-form-${entry.id}`" :disabled="entry.form.processing || !entry.form.isDirty" class="min-h-9 rounded-md bg-admin-primary px-3 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{{ entry.form.processing ? t('common.saving') : t('common.save') }}</button><StatusBadge v-else :status="entry.form.status" /></td>
                                    </tr>
                                    <tr v-if="entry.form.hasErrors"><td colspan="4" class="bg-admin-danger-bg px-3 py-2"><FieldError :message="fieldError(entry.form.errors, 'code', 'taxes.errors.code') ?? fieldError(entry.form.errors, 'name', 'taxes.errors.name') ?? fieldError(entry.form.errors, 'status', 'taxes.errors.status')" /></td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 md:hidden">
                        <form v-for="entry in categoryForms" :key="`mobile-${entry.id}`" class="admin-card grid min-w-0 gap-3 p-4" @submit.prevent="updateCategory(entry)">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="admin-label">{{ t('taxes.category') }}</p><p class="mt-1 truncate font-semibold">{{ entry.form.name }}</p></div><StatusBadge :status="entry.form.status" /></div>
                            <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.code') }}</span><select v-model="entry.form.code" :disabled="!canManage" required class="admin-control w-full px-3 font-mono text-sm disabled:bg-admin-canvas"><option v-for="code in categoryCodes" :key="code" :value="code">{{ codeLabel(code) }}</option></select><FieldError :message="fieldError(entry.form.errors, 'code', 'taxes.errors.code')" /></label>
                            <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.name') }}</span><input v-model="entry.form.name" :disabled="!canManage" required maxlength="120" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="fieldError(entry.form.errors, 'name', 'taxes.errors.name')" /></label>
                            <label class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select v-model="entry.form.status" :disabled="!canManage" required class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas"><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select><FieldError :message="fieldError(entry.form.errors, 'status', 'taxes.errors.status')" /></label>
                            <button v-if="canManage" :disabled="entry.form.processing || !entry.form.isDirty" class="min-h-10 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{{ entry.form.processing ? t('common.saving') : t('taxes.saveCategory') }}</button>
                        </form>
                    </div>
                </template>
                <EmptyState v-else :title="t('taxes.noCategories')" :description="t('taxes.noCategoriesHelp')" />

                <form v-if="canManage" class="admin-card grid gap-4 border-dashed p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[12rem_minmax(0,1fr)_12rem_auto] lg:items-start" @submit.prevent="createCategory">
                    <div class="sm:col-span-2 lg:col-span-4"><p class="admin-label text-admin-secondary">{{ t('taxes.newCategorySection') }}</p><h3 class="mt-1 font-semibold">{{ t('taxes.addCategory') }}</h3><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('taxes.addCategoryHelp') }}</p></div>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.code') }}</span><select v-model="categoryForm.code" required class="admin-control w-full px-3 font-mono text-sm"><option v-for="code in categoryCodes" :key="code" :value="code">{{ codeLabel(code) }}</option></select><FieldError :message="fieldError(categoryForm.errors, 'code', 'taxes.errors.code')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.name') }}</span><input v-model="categoryForm.name" required maxlength="120" autocomplete="off" class="admin-control w-full px-3 text-sm" /><FieldError :message="fieldError(categoryForm.errors, 'name', 'taxes.errors.name')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select v-model="categoryForm.status" required class="admin-control w-full px-3 text-sm"><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select><FieldError :message="fieldError(categoryForm.errors, 'status', 'taxes.errors.status')" /></label>
                    <button :disabled="categoryForm.processing" class="min-h-10 w-full rounded-md bg-admin-secondary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 lg:mt-[1.375rem] lg:w-auto">{{ categoryForm.processing ? t('common.creating') : t('taxes.addCategory') }}</button>
                </form>
            </section>

            <section aria-labelledby="tax-rates-heading" class="mt-2 grid gap-3 border-t border-admin-border pt-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><p class="admin-label text-admin-secondary">{{ t('taxes.ratesSection') }}</p><h2 id="tax-rates-heading" class="mt-1 text-lg font-semibold">{{ t('taxes.rates') }}</h2><p class="mt-1 max-w-3xl text-sm text-admin-neutral">{{ t('taxes.ratesHelp') }}</p></div><p class="font-mono text-xs text-admin-neutral">{{ t('taxes.configuredCount', { count: rates.length }) }}</p></div>

                <template v-if="rateForms.length">
                    <form v-for="entry in rateForms" :id="`rate-form-${entry.id}`" :key="`rate-form-${entry.id}`" class="hidden" @submit.prevent="updateRate(entry)" />
                    <div class="admin-card hidden overflow-hidden lg:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[18%] p-3">{{ t('taxes.category') }}</th><th class="w-[18%] p-3">{{ t('taxes.name') }}</th><th class="w-[11%] p-3">{{ t('taxes.rate') }}</th><th class="w-[13%] p-3">{{ t('taxes.validFrom') }}</th><th class="w-[13%] p-3">{{ t('taxes.validUntil') }}</th><th class="w-[13%] p-3">{{ t('common.status') }}</th><th class="w-[14%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><template v-for="entry in rateForms" :key="entry.id"><tr>
                        <td class="p-2"><label :for="`rate-category-${entry.id}`" class="sr-only">{{ t('taxes.category') }}</label><select :id="`rate-category-${entry.id}`" v-model="entry.form.tax_category_id" :form="`rate-form-${entry.id}`" :disabled="!canManage" required class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas"><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></td>
                        <td class="p-2"><label :for="`rate-name-${entry.id}`" class="sr-only">{{ t('taxes.name') }}</label><input :id="`rate-name-${entry.id}`" v-model="entry.form.name" :form="`rate-form-${entry.id}`" :disabled="!canManage" required maxlength="120" class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas" /></td>
                        <td class="p-2"><label :for="`rate-value-${entry.id}`" class="sr-only">{{ t('taxes.rate') }}</label><div class="relative"><input :id="`rate-value-${entry.id}`" v-model="entry.form.rate" :form="`rate-form-${entry.id}`" :disabled="!canManage" required inputmode="decimal" placeholder="18.000000" class="admin-control w-full px-2 pr-6 font-mono text-xs tabular-nums disabled:bg-admin-canvas" /><span class="pointer-events-none absolute inset-y-0 right-2 grid place-items-center text-admin-neutral">%</span></div></td>
                        <td class="p-2"><label :for="`rate-from-${entry.id}`" class="sr-only">{{ t('taxes.validFrom') }}</label><input :id="`rate-from-${entry.id}`" v-model="entry.form.valid_from" :form="`rate-form-${entry.id}`" :disabled="!canManage" type="date" class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas" /></td>
                        <td class="p-2"><label :for="`rate-until-${entry.id}`" class="sr-only">{{ t('taxes.validUntil') }}</label><input :id="`rate-until-${entry.id}`" v-model="entry.form.valid_until" :form="`rate-form-${entry.id}`" :disabled="!canManage" type="date" class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas" /></td>
                        <td class="p-2"><label :for="`rate-status-${entry.id}`" class="sr-only">{{ t('common.status') }}</label><select :id="`rate-status-${entry.id}`" v-model="entry.form.status" :form="`rate-form-${entry.id}`" :disabled="!canManage" required class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas"><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select></td>
                        <td class="p-2 text-right"><button v-if="canManage" :form="`rate-form-${entry.id}`" :disabled="entry.form.processing || !entry.form.isDirty" class="min-h-9 rounded-md bg-admin-primary px-3 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{{ entry.form.processing ? t('common.saving') : t('common.save') }}</button><StatusBadge v-else :status="entry.form.status" /></td>
                    </tr><tr v-if="entry.form.hasErrors"><td colspan="7" class="bg-admin-danger-bg px-3 py-2"><FieldError :message="fieldError(entry.form.errors, 'tax_category_id', 'taxes.errors.category') ?? fieldError(entry.form.errors, 'name', 'taxes.errors.name') ?? fieldError(entry.form.errors, 'rate', 'taxes.errors.rate') ?? fieldError(entry.form.errors, 'valid_from', 'taxes.errors.validFrom') ?? fieldError(entry.form.errors, 'valid_until', 'taxes.errors.validUntil') ?? fieldError(entry.form.errors, 'status', 'taxes.errors.status')" /></td></tr></template></tbody></table></div>

                    <div class="grid gap-3 lg:hidden"><form v-for="entry in rateForms" :key="`mobile-${entry.id}`" class="admin-card grid min-w-0 gap-3 p-4 sm:grid-cols-2" @submit.prevent="updateRate(entry)"><div class="flex items-start justify-between gap-3 sm:col-span-2"><div class="min-w-0"><p class="admin-label">{{ t('taxes.taxRate') }}</p><p class="mt-1 truncate font-semibold">{{ entry.form.name }}</p></div><StatusBadge :status="entry.form.status" /></div>
                        <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('taxes.category') }}</span><select v-model="entry.form.tax_category_id" :disabled="!canManage" required class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas"><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }} · {{ codeLabel(category.code) }}</option></select><FieldError :message="fieldError(entry.form.errors, 'tax_category_id', 'taxes.errors.category')" /></label>
                        <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.name') }}</span><input v-model="entry.form.name" :disabled="!canManage" required maxlength="120" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="fieldError(entry.form.errors, 'name', 'taxes.errors.name')" /></label>
                        <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.rate') }}</span><div class="relative"><input v-model="entry.form.rate" :disabled="!canManage" required inputmode="decimal" placeholder="18.000000" class="admin-control w-full px-3 pr-8 font-mono text-sm tabular-nums disabled:bg-admin-canvas" /><span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-admin-neutral">%</span></div><FieldError :message="fieldError(entry.form.errors, 'rate', 'taxes.errors.rate')" /></label>
                        <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.validFrom') }}</span><input v-model="entry.form.valid_from" :disabled="!canManage" type="date" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="fieldError(entry.form.errors, 'valid_from', 'taxes.errors.validFrom')" /></label>
                        <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.validUntil') }}</span><input v-model="entry.form.valid_until" :disabled="!canManage" type="date" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="fieldError(entry.form.errors, 'valid_until', 'taxes.errors.validUntil')" /></label>
                        <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('common.status') }}</span><select v-model="entry.form.status" :disabled="!canManage" required class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas"><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select><FieldError :message="fieldError(entry.form.errors, 'status', 'taxes.errors.status')" /></label>
                        <button v-if="canManage" :disabled="entry.form.processing || !entry.form.isDirty" class="min-h-10 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-2">{{ entry.form.processing ? t('common.saving') : t('taxes.saveRate') }}</button>
                    </form></div>
                </template>
                <EmptyState v-else :title="t('taxes.noRates')" :description="categories.length ? t('taxes.noRatesHelp') : t('taxes.noRatesWithoutCategories')" />

                <form v-if="canManage" class="admin-card grid gap-4 border-dashed p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-6" @submit.prevent="createRate"><div class="sm:col-span-2 lg:col-span-6"><p class="admin-label text-admin-secondary">{{ t('taxes.newRateSection') }}</p><h3 class="mt-1 font-semibold">{{ t('taxes.addRate') }}</h3><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('taxes.addRateHelp') }}</p></div>
                    <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('taxes.category') }}</span><select v-model="rateForm.tax_category_id" required class="admin-control w-full px-3 text-sm"><option value="" disabled>{{ t('taxes.selectCategory') }}</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }} · {{ codeLabel(category.code) }}</option></select><FieldError :message="fieldError(rateForm.errors, 'tax_category_id', 'taxes.errors.category')" /></label>
                    <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('taxes.name') }}</span><input v-model="rateForm.name" required maxlength="120" autocomplete="off" class="admin-control w-full px-3 text-sm" /><FieldError :message="fieldError(rateForm.errors, 'name', 'taxes.errors.name')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.rate') }}</span><div class="relative"><input v-model="rateForm.rate" required inputmode="decimal" placeholder="18.000000" class="admin-control w-full px-3 pr-8 font-mono text-sm tabular-nums" /><span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-admin-neutral">%</span></div><FieldError :message="fieldError(rateForm.errors, 'rate', 'taxes.errors.rate')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select v-model="rateForm.status" required class="admin-control w-full px-3 text-sm"><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select><FieldError :message="fieldError(rateForm.errors, 'status', 'taxes.errors.status')" /></label>
                    <label class="grid gap-1.5 sm:col-span-1 lg:col-start-3"><span class="admin-label">{{ t('taxes.validFrom') }} <span class="normal-case text-admin-neutral">({{ t('common.optional') }})</span></span><input v-model="rateForm.valid_from" type="date" class="admin-control w-full px-3 text-sm" /><FieldError :message="fieldError(rateForm.errors, 'valid_from', 'taxes.errors.validFrom')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('taxes.validUntil') }} <span class="normal-case text-admin-neutral">({{ t('common.optional') }})</span></span><input v-model="rateForm.valid_until" type="date" class="admin-control w-full px-3 text-sm" /><FieldError :message="fieldError(rateForm.errors, 'valid_until', 'taxes.errors.validUntil')" /></label>
                    <div class="flex items-end sm:col-span-2"><button :disabled="rateForm.processing || !categories.length" class="min-h-10 w-full rounded-md bg-admin-secondary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">{{ rateForm.processing ? t('common.creating') : t('taxes.addRate') }}</button></div>
                </form>
                <aside class="rounded-md border border-admin-info-border bg-admin-info-bg p-4"><p class="text-sm font-semibold text-admin-text">{{ t('taxes.calculationTitle') }}</p><p class="mt-1 text-xs leading-5 text-admin-text-secondary">{{ t('taxes.calculationHelp') }}</p></aside>
            </section>
        </section>
    </AdminLayout>
</template>
