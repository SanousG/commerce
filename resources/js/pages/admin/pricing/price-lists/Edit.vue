<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../../components/admin/EmptyState.vue';
import PageHeader from '../../../../components/admin/PageHeader.vue';
import Pagination from '../../../../components/admin/Pagination.vue';
import StatusBadge from '../../../../components/admin/StatusBadge.vue';
import FieldError from '../../../../components/FieldError.vue';
import StatusNotice from '../../../../components/StatusNotice.vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../../types';

interface PriceList {
    id: string;
    name: string;
    code: string;
    currency: string;
    status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    is_default: boolean;
    prices_count: number;
}

interface Variant {
    id: string;
    product: string;
    name: string | null;
    sku: string;
    status: string;
    amount: string | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    priceList: PriceList;
    variants: {
        data: Variant[];
        links: PageLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    filters: { search: string };
    currencies: string[];
    statuses: string[];
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('pricing.update'));
const canArchive = computed(() => permissions.value.includes('pricing.archive'));
const isArchived = computed(() => props.priceList.status === 'ARCHIVED');
const canEdit = computed(() => canUpdate.value && !isArchived.value);
const hasSearch = computed(() => props.filters.search !== '');
const search = ref(props.filters.search);
const searching = ref(false);
const amounts = reactive<Record<string, string>>(Object.fromEntries(props.variants.data.map((variant) => [variant.id, variant.amount ?? ''])));
const activePriceId = ref<string | null>(null);
const activeLifecycle = ref<'default' | 'archive' | null>(null);
const form = useForm({ name: props.priceList.name, currency: props.priceList.currency, status: props.priceList.status });
const priceForm = useForm({ amount: '' });
const removeForm = useForm({});
const lifecycle = useForm({});
const { t, locale } = useI18n();

watch(() => props.filters.search, (value) => { search.value = value; });
watch(() => props.variants.data, (variants) => {
    for (const variant of variants) amounts[variant.id] = variant.amount ?? '';
});

const localizedStatus = computed(() => {
    const keys: Record<string, string> = {
        'Price list created.': 'pricing.notices.created',
        'Price list updated.': 'pricing.notices.updated',
        'Default price list changed.': 'pricing.notices.defaultChanged',
        'Price saved.': 'pricing.notices.priceSaved',
        'Price removed.': 'pricing.notices.priceRemoved',
    };
    const message = page.props.status;
    return message ? t(keys[message] ?? 'pricing.notices.completed') : undefined;
});
const listError = (field: 'name' | 'currency' | 'status') => {
    const message = form.errors[field];
    if (!message) return undefined;
    const known: Record<string, string> = {
        'Currency cannot change after prices have been assigned.': 'pricing.errors.currencyLocked',
        'Archived price lists cannot be edited.': 'pricing.errors.archivedEdit',
        'The default price list must remain active.': 'pricing.errors.defaultMustRemainActive',
    };
    return t(known[message] ?? `pricing.editPage.errors.${field}`);
};
const lifecycleError = computed(() => {
    const message = (lifecycle.errors as Record<string, string | undefined>).price_list;
    if (!message) return undefined;
    const known: Record<string, string> = {
        'Only an active price list can become default.': 'pricing.errors.onlyActiveDefault',
        'Choose another default before archiving this list.': 'pricing.errors.chooseAnotherDefault',
    };
    return t(known[message] ?? 'pricing.errors.actionFailed');
});
const priceError = computed(() => {
    const message = (priceForm.errors as Record<string, string | undefined>).amount
        ?? (removeForm.errors as Record<string, string | undefined>).amount;
    if (!message) return undefined;
    return message === 'Archived price lists cannot be changed.'
        ? t('pricing.errors.archivedPrices')
        : t('pricing.editPage.errors.amount');
});

const visitEdit = (value: string) => router.get(
    `/admin/pricing/price-lists/${props.priceList.id}/edit`,
    value.trim() === '' ? {} : { search: value.trim() },
    {
        preserveState: false,
        replace: true,
        onStart: () => { searching.value = true; },
        onFinish: () => { searching.value = false; },
    },
);
const submitSearch = () => visitEdit(search.value);
const clearSearch = () => { search.value = ''; visitEdit(''); };
const updateList = () => {
    if (form.processing) return;
    form.put(`/admin/pricing/price-lists/${props.priceList.id}`, { preserveScroll: true });
};
const makeDefault = () => {
    if (lifecycle.processing || !window.confirm(t('pricing.makeDefaultConfirm', { name: props.priceList.name }))) return;
    activeLifecycle.value = 'default';
    lifecycle.clearErrors();
    lifecycle.post(`/admin/pricing/price-lists/${props.priceList.id}/make-default`, {
        preserveScroll: true,
        onFinish: () => { activeLifecycle.value = null; },
    });
};
const archive = () => {
    if (lifecycle.processing || !window.confirm(t('pricing.archiveConfirm', { name: props.priceList.name }))) return;
    activeLifecycle.value = 'archive';
    lifecycle.clearErrors();
    lifecycle.patch(`/admin/pricing/price-lists/${props.priceList.id}/archive`, {
        onFinish: () => { activeLifecycle.value = null; },
    });
};
const setPrice = (variant: Variant) => {
    if (priceForm.processing || removeForm.processing) return;
    activePriceId.value = variant.id;
    priceForm.clearErrors();
    removeForm.clearErrors();
    priceForm.amount = amounts[variant.id] ?? '';
    priceForm.put(`/admin/pricing/price-lists/${props.priceList.id}/variants/${variant.id}`, {
        preserveScroll: true,
        onSuccess: () => { activePriceId.value = null; },
    });
};
const removePrice = (variant: Variant) => {
    if (priceForm.processing || removeForm.processing || !window.confirm(t('pricing.editPage.removeConfirm', { sku: variant.sku }))) return;
    activePriceId.value = variant.id;
    priceForm.clearErrors();
    removeForm.clearErrors();
    removeForm.delete(`/admin/pricing/price-lists/${props.priceList.id}/variants/${variant.id}`, {
        preserveScroll: true,
        onSuccess: () => { amounts[variant.id] = ''; activePriceId.value = null; },
    });
};
const isSavingPrice = (variant: Variant) => activePriceId.value === variant.id && priceForm.processing;
const isRemovingPrice = (variant: Variant) => activePriceId.value === variant.id && removeForm.processing;
const formatAmount = (amount: string) => {
    const parts = new Intl.NumberFormat(locale.value).formatToParts(1000.1);
    const group = parts.find((part) => part.type === 'group')?.value ?? ',';
    const decimal = parts.find((part) => part.type === 'decimal')?.value ?? '.';
    const [whole, fraction = ''] = amount.split('.');
    return `${props.priceList.currency} ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, group)}${decimal}${fraction.padEnd(4, '0')}`;
};
</script>

<template>
    <Head :title="`${t('pricing.editPage.title')} · ${priceList.name}`" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.merchandising') }, { label: t('pricing.title'), href: '/admin/pricing' }, { label: priceList.name }]" />
            <PageHeader :title="priceList.name" :eyebrow="t('common.allCompany')" :description="t('pricing.editPage.description')">
                <template #actions><Link href="/admin/pricing" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50">{{ t('pricing.back') }}</Link></template>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-admin-neutral"><StatusBadge :status="priceList.status" /><span>{{ t('pricing.code') }} <strong class="ml-1 font-mono font-medium text-admin-text-secondary">{{ priceList.code }}</strong></span><span>{{ t('pricing.currency') }} <strong class="ml-1 font-mono font-medium text-admin-text-secondary">{{ priceList.currency }}</strong></span><span v-if="priceList.is_default" class="rounded border border-blue-200 bg-blue-50 px-2 py-1 font-semibold text-admin-secondary">{{ t('pricing.default') }}</span></div>
            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <aside v-if="isArchived" class="flex gap-3 rounded-md border border-admin-border-strong bg-admin-canvas px-4 py-3" role="status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-neutral" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5 8h14v11H5V8Zm2-3h10v3H7V5Zm3 7h4" /></svg><div><p class="text-sm font-semibold text-admin-text">{{ t('pricing.editPage.archivedTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-neutral">{{ t('pricing.editPage.archivedHelp') }}</p></div></aside>
            <aside v-else-if="!canUpdate" class="flex gap-3 rounded-md border border-admin-border-strong bg-admin-canvas px-4 py-3" role="status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-neutral" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 11V8a5 5 0 0 1 10 0v3m-11 0h12v9H6v-9Z" /></svg><div><p class="text-sm font-semibold text-admin-text">{{ t('pricing.editPage.readOnlyTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-neutral">{{ t('pricing.editPage.readOnlyHelp') }}</p></div></aside>

            <div v-if="lifecycleError" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3"><FieldError :message="lifecycleError" /></div>

            <section v-if="!isArchived && ((canUpdate && !priceList.is_default && priceList.status === 'ACTIVE') || (canArchive && !priceList.is_default))" class="admin-card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div><p class="admin-label text-admin-secondary">{{ t('pricing.editPage.lifecycleSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('pricing.editPage.lifecycle') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('pricing.editPage.lifecycleHelp') }}</p></div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end"><button v-if="canUpdate && !priceList.is_default && priceList.status === 'ACTIVE'" type="button" :disabled="lifecycle.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60" @click="makeDefault">{{ activeLifecycle === 'default' ? t('pricing.makingDefault') : t('pricing.makeDefault') }}</button><button v-if="canArchive && !priceList.is_default" type="button" :disabled="lifecycle.processing" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-4 text-sm font-semibold text-admin-danger hover:bg-admin-danger-bg disabled:cursor-wait disabled:opacity-60" @click="archive">{{ activeLifecycle === 'archive' ? t('common.archiving') : t('common.archive') }}</button></div>
            </section>

            <form class="admin-card overflow-hidden" @submit.prevent="updateList">
                <header class="flex flex-col gap-2 border-b border-admin-border px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6"><div><p class="admin-label text-admin-secondary">{{ t('pricing.editPage.settingsSection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('pricing.editPage.generalSettings') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('pricing.editPage.generalSettingsHelp') }}</p></div><span v-if="!canEdit" class="rounded border border-admin-border bg-slate-50 px-2 py-1 text-xs font-medium text-admin-neutral">{{ t('common.readOnly') }}</span></header>
                <fieldset :disabled="!canEdit" class="grid gap-5 p-5 sm:p-6 lg:grid-cols-3">
                    <div><div class="mb-1.5 flex items-end justify-between gap-3"><label for="price-list-edit-name" class="text-sm font-semibold text-admin-text">{{ t('pricing.name') }} <span class="text-admin-danger" aria-hidden="true">*</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ form.name.length }} / 120</span></div><input id="price-list-edit-name" v-model="form.name" required maxlength="120" autocomplete="off" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-admin-neutral" :class="form.errors.name ? 'border-admin-danger' : ''" :aria-invalid="form.errors.name ? 'true' : undefined" aria-describedby="price-list-edit-name-error" /><FieldError id="price-list-edit-name-error" :message="listError('name')" /></div>
                    <div><label for="price-list-edit-currency" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('pricing.currency') }} <span class="text-admin-danger" aria-hidden="true">*</span></label><select id="price-list-edit-currency" v-model="form.currency" :disabled="!canEdit || priceList.prices_count > 0" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-admin-neutral" :class="form.errors.currency ? 'border-admin-danger' : ''" :aria-invalid="form.errors.currency ? 'true' : undefined" aria-describedby="price-list-edit-currency-help price-list-edit-currency-error"><option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option></select><p id="price-list-edit-currency-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ priceList.prices_count > 0 ? t('pricing.editPage.currencyLockedHelp') : t('pricing.editPage.currencyHelp') }}</p><FieldError id="price-list-edit-currency-error" :message="listError('currency')" /></div>
                    <div><label for="price-list-edit-status" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('common.status') }} <span class="text-admin-danger" aria-hidden="true">*</span></label><select id="price-list-edit-status" v-model="form.status" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-admin-neutral" :class="form.errors.status ? 'border-admin-danger' : ''" :aria-invalid="form.errors.status ? 'true' : undefined" aria-describedby="price-list-edit-status-help price-list-edit-status-error"><option v-for="status in statuses" :key="status" :value="status" :disabled="priceList.is_default && status !== 'ACTIVE'">{{ t(`status.${status}`) }}</option></select><p id="price-list-edit-status-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ priceList.is_default ? t('pricing.editPage.defaultStatusHelp') : t('pricing.editPage.statusHelp') }}</p><FieldError id="price-list-edit-status-error" :message="listError('status')" /></div>
                </fieldset>
                <footer v-if="canEdit" class="flex justify-end border-t border-admin-border bg-admin-canvas px-5 py-4 sm:px-6"><button type="submit" :disabled="form.processing" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">{{ form.processing ? t('common.saving') : t('pricing.editPage.saveList') }}</button></footer>
            </form>

            <section class="admin-card overflow-hidden">
                <header class="grid gap-4 border-b border-admin-border px-5 py-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,32rem)] lg:items-end">
                    <div><p class="admin-label text-admin-secondary">{{ t('pricing.editPage.pricesSection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('pricing.editPage.variantPrices') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('pricing.editPage.variantPricesHelp', { currency: priceList.currency }) }}</p></div>
                    <form class="flex flex-col gap-2 sm:flex-row" role="search" @submit.prevent="submitSearch"><label for="price-variant-search" class="sr-only">{{ t('pricing.editPage.searchVariants') }}</label><input id="price-variant-search" v-model="search" type="search" autocomplete="off" :placeholder="t('pricing.editPage.searchPlaceholder')" class="admin-control min-w-0 flex-1 px-3 text-sm" /><div class="flex gap-2"><button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('common.search') }}</button><button v-if="hasSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearSearch">{{ t('common.clear') }}</button></div></form>
                </header>

                <template v-if="variants.data.length">
                    <div class="hidden md:block">
                        <table class="admin-table w-full table-fixed text-left text-xs">
                            <thead><tr><th class="w-[31%] p-3">{{ t('pricing.editPage.productVariant') }}</th><th class="w-[13%] p-3">{{ t('pricing.editPage.catalogStatus') }}</th><th class="w-[18%] p-3 text-right">{{ t('pricing.editPage.currentPrice') }}</th><th class="w-[38%] p-3 text-right">{{ canEdit ? t('pricing.editPage.setPrice') : t('pricing.editPage.configuredPrice') }}</th></tr></thead>
                            <tbody class="divide-y divide-admin-border">
                                <tr v-for="variant in variants.data" :key="variant.id" :class="variant.status !== 'ACTIVE' ? 'bg-slate-50/70' : ''">
                                    <td class="p-3"><p class="truncate text-sm font-semibold text-admin-text" :title="variant.product">{{ variant.product }}</p><p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral" :title="variant.sku">{{ variant.sku }}<template v-if="variant.name"> · {{ variant.name }}</template></p></td>
                                    <td class="p-3"><StatusBadge :status="variant.status" /></td>
                                    <td class="p-3 text-right font-mono tabular-nums"><span v-if="variant.amount !== null" class="font-semibold text-admin-text">{{ formatAmount(variant.amount) }}</span><span v-else class="text-admin-neutral">{{ t('pricing.editPage.notPriced') }}</span></td>
                                    <td class="p-3"><form v-if="canEdit" class="ml-auto grid max-w-md grid-cols-[minmax(8rem,1fr)_auto_auto] items-start gap-2" @submit.prevent="setPrice(variant)"><div><label :for="`variant-price-${variant.id}`" class="sr-only">{{ t('pricing.editPage.amountFor', { sku: variant.sku }) }}</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-[10px] text-admin-neutral">{{ priceList.currency }}</span><input :id="`variant-price-${variant.id}`" v-model="amounts[variant.id]" required inputmode="decimal" maxlength="20" pattern="\d{1,15}([.]\d{1,4})?" placeholder="0.0000" class="admin-control w-full pl-12 pr-2 font-mono text-xs" :class="activePriceId === variant.id && priceError ? 'border-admin-danger' : ''" :aria-invalid="activePriceId === variant.id && priceError ? 'true' : undefined" :aria-describedby="activePriceId === variant.id && priceError ? `variant-price-error-${variant.id}` : undefined" /><FieldError v-if="activePriceId === variant.id" :id="`variant-price-error-${variant.id}`" :message="priceError" class="text-left" /></div></div><button type="submit" :disabled="priceForm.processing || removeForm.processing" class="min-h-9 rounded-md bg-admin-primary px-3 text-xs font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60">{{ isSavingPrice(variant) ? t('common.saving') : t('common.save') }}</button><button v-if="variant.amount !== null" type="button" :disabled="priceForm.processing || removeForm.processing" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-3 text-xs font-semibold text-admin-danger hover:bg-admin-danger-bg disabled:cursor-wait disabled:opacity-60" @click="removePrice(variant)">{{ isRemovingPrice(variant) ? t('pricing.editPage.removing') : t('common.remove') }}</button></form><span v-else class="block text-right text-admin-neutral">{{ t('common.readOnly') }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-2.5 p-3 md:hidden">
                        <article v-for="variant in variants.data" :key="`mobile-${variant.id}`" class="rounded-md border border-admin-border bg-white p-3.5">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="text-sm font-semibold text-admin-text">{{ variant.product }}</h3><p class="mt-1 truncate font-mono text-[10px] text-admin-neutral">{{ variant.sku }}<template v-if="variant.name"> · {{ variant.name }}</template></p></div><StatusBadge :status="variant.status" /></div>
                            <dl class="mt-3 border-y border-admin-border py-2.5"><div class="flex items-center justify-between gap-3"><dt class="admin-label">{{ t('pricing.editPage.currentPrice') }}</dt><dd class="font-mono text-xs font-semibold tabular-nums">{{ variant.amount !== null ? formatAmount(variant.amount) : t('pricing.editPage.notPriced') }}</dd></div></dl>
                            <form v-if="canEdit" class="mt-3 grid gap-2" @submit.prevent="setPrice(variant)"><label :for="`mobile-variant-price-${variant.id}`" class="text-xs font-semibold text-admin-text">{{ t('pricing.editPage.amount') }}</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-[10px] text-admin-neutral">{{ priceList.currency }}</span><input :id="`mobile-variant-price-${variant.id}`" v-model="amounts[variant.id]" required inputmode="decimal" maxlength="20" pattern="\d{1,15}([.]\d{1,4})?" placeholder="0.0000" class="admin-control w-full pl-12 pr-3 font-mono text-sm" :class="activePriceId === variant.id && priceError ? 'border-admin-danger' : ''" :aria-invalid="activePriceId === variant.id && priceError ? 'true' : undefined" :aria-describedby="activePriceId === variant.id && priceError ? `mobile-variant-price-error-${variant.id}` : undefined" /><FieldError v-if="activePriceId === variant.id" :id="`mobile-variant-price-error-${variant.id}`" :message="priceError" /></div><div class="grid gap-2" :class="variant.amount !== null ? 'grid-cols-2' : 'grid-cols-1'"><button type="submit" :disabled="priceForm.processing || removeForm.processing" class="min-h-10 rounded-md bg-admin-primary px-4 text-xs font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60">{{ isSavingPrice(variant) ? t('common.saving') : t('pricing.editPage.savePrice') }}</button><button v-if="variant.amount !== null" type="button" :disabled="priceForm.processing || removeForm.processing" class="min-h-10 rounded-md border border-admin-danger-border bg-white px-4 text-xs font-semibold text-admin-danger hover:bg-admin-danger-bg disabled:cursor-wait disabled:opacity-60" @click="removePrice(variant)">{{ isRemovingPrice(variant) ? t('pricing.editPage.removing') : t('common.remove') }}</button></div></form>
                        </article>
                    </div>
                </template>

                <div v-else class="p-4 sm:p-6"><EmptyState :title="t('pricing.editPage.noVariants')" :description="hasSearch ? t('pricing.editPage.noVariantsSearchHelp') : t('pricing.editPage.noVariantsHelp')"><template v-if="hasSearch" #actions><button type="button" class="admin-link text-sm" @click="clearSearch">{{ t('pricing.editPage.clearSearch') }}</button></template></EmptyState></div>
                <footer class="border-t border-admin-border bg-admin-canvas px-5 py-4 sm:px-6"><Pagination :links="variants.links" :from="variants.from" :to="variants.to" :total="variants.total" /></footer>
            </section>
        </section>
    </AdminLayout>
</template>
