<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PriceList {
    id: string;
    name: string;
    currency: string;
    is_default: boolean;
}

interface AppliedPromotion {
    id: string;
    name: string;
    internal_code: string | null;
}

interface EffectivePrice {
    base_amount: string;
    final_amount: string;
    total_discount: string;
    applied_promotions: AppliedPromotion[];
}

interface VariantRow {
    id: string;
    sku: string;
    name: string | null;
    status: string;
    configured: boolean;
    is_enabled: boolean;
    price: EffectivePrice | null;
}

interface Listing {
    id: string;
    product: string;
    status: 'DRAFT' | 'PUBLISHED' | 'UNPUBLISHED' | 'ARCHIVED';
    storefront_slug: string;
    price_list_id: string | null;
    effective_price_list: { id: string; name: string; currency: string } | null;
    published_at: string | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    listing: Listing;
    variants: {
        data: VariantRow[];
        links: PageLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    filters: { variant_search: string };
    enabledVariantIds: string[];
    priceLists: PriceList[];
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('listings.update'));
const canPublish = computed(() => permissions.value.includes('listings.publish'));
const canArchive = computed(() => permissions.value.includes('listings.archive'));
const isArchived = computed(() => props.listing.status === 'ARCHIVED');
const canEdit = computed(() => canUpdate.value && !isArchived.value);
const hasVariantSearch = computed(() => props.filters.variant_search !== '');
const variantSearch = ref(props.filters.variant_search);
const searching = ref(false);
const activeTransition = ref<'publish' | 'unpublish' | 'archive' | null>(null);
const form = useForm({
    storefront_slug: props.listing.storefront_slug,
    price_list_id: props.listing.price_list_id ?? '',
    enabled_variant_ids: [...props.enabledVariantIds],
});
const transition = useForm({});
const { t, locale } = useI18n();

watch(() => props.filters.variant_search, (value) => {
    variantSearch.value = value;
});

const localizedStatus = computed(() => {
    const message = page.props.status;
    if (!message) return undefined;

    const keys: Record<string, string> = {
        'Listing created.': 'listings.notices.created',
        'Listing updated.': 'listings.notices.updated',
        'Listing published.': 'listings.notices.published',
        'Listing unpublished.': 'listings.notices.unpublished',
    };

    return t(keys[message] ?? 'listings.notices.completed');
});
const localizedActionError = computed(() => {
    const errors = transition.errors as Record<string, string | undefined>;
    const message = errors.listing ?? errors.price_list_id ?? errors.variants;
    if (!message) return undefined;

    const keys: Record<string, string> = {
        'Archived listings cannot be published.': 'listings.errors.archivedPublish',
        'Archived listings cannot be unpublished.': 'listings.errors.archivedUnpublish',
        'The current store must be active.': 'listings.errors.storeInactive',
        'The product must be active.': 'listings.errors.productInactive',
        'Enable at least one active variant.': 'listings.errors.activeVariant',
        'The effective price list must be active.': 'listings.errors.activePriceList',
    };
    if (keys[message]) return t(keys[message]);

    const missingPrefix = 'Missing prices for enabled SKUs: ';
    if (message.startsWith(missingPrefix)) {
        return t('listings.errors.missingPrices', { skus: message.slice(missingPrefix.length).replace(/\.$/u, '') });
    }

    return t('listings.errors.actionFailed');
});
const updateError = (field: 'listing' | 'storefront_slug' | 'price_list_id') => {
    const errors = form.errors as Record<string, string | undefined>;
    const message = errors[field];
    if (!message) return undefined;

    const known: Record<string, string> = {
        'Archived listings cannot be changed.': 'listings.editPage.errors.archived',
        'The storefront slug must contain letters or numbers.': 'listings.editPage.errors.slugCharacters',
        'This slug is already used in the current store.': 'listings.editPage.errors.slugUsed',
        'Select an active price list from the current company.': 'listings.errors.activeCompanyPriceList',
    };
    const fallback: Record<typeof field, string> = {
        listing: 'listings.editPage.errors.update',
        storefront_slug: 'listings.editPage.errors.slug',
        price_list_id: 'listings.errors.invalidDefaultPriceList',
    };

    return t(known[message] ?? fallback[field]);
};
const variantSelectionError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    const message = errors.enabled_variant_ids ?? Object.entries(errors).find(([key]) => key.startsWith('enabled_variant_ids.'))?.[1];
    if (!message) return undefined;

    return message === 'Every enabled variant must belong to the listing product.'
        ? t('listings.editPage.errors.foreignVariant')
        : t('listings.editPage.errors.variants');
});

const visitEdit = (value: string) => router.get(
    `/admin/listings/${props.listing.id}/edit`,
    value.trim() === '' ? {} : { variant_search: value.trim() },
    {
        preserveState: true,
        replace: true,
        onStart: () => { searching.value = true; },
        onFinish: () => { searching.value = false; },
    },
);
const searchVariants = () => visitEdit(variantSearch.value);
const clearVariantSearch = () => {
    variantSearch.value = '';
    visitEdit('');
};
const update = () => form.put(`/admin/listings/${props.listing.id}`, {
    preserveScroll: true,
    onSuccess: () => {
        form.storefront_slug = props.listing.storefront_slug;
        form.price_list_id = props.listing.price_list_id ?? '';
    },
});
const runTransition = (action: 'publish' | 'unpublish' | 'archive') => {
    if (transition.processing) return;
    activeTransition.value = action;
    transition.clearErrors();
    transition.post(`/admin/listings/${props.listing.id}/${action}`, {
        preserveScroll: true,
        onFinish: () => { activeTransition.value = null; },
    });
};
const archive = () => {
    if (!window.confirm(t('listings.archiveConfirm', { product: props.listing.product }))) return;
    runTransition('archive');
};
const isEnabled = (variant: VariantRow) => form.enabled_variant_ids.includes(variant.id);
const formatPublishedAt = (value: string | null) => value
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : t('listings.editPage.neverPublished');
const amount = (value: string) => `${props.listing.effective_price_list?.currency ?? ''} ${value}`.trim();
const promotionNames = (variant: VariantRow) => variant.price?.applied_promotions.map((promotion) => promotion.name).join(', ') || t('listings.editPage.noPromotion');
</script>

<template>
    <Head :title="`${t('listings.editPage.title')} · ${listing.product}`" />

    <AdminLayout v-slot="{ currentStore }">
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('listings.title'), href: '/admin/listings' }, { label: listing.product }]" />

            <PageHeader
                :title="listing.product"
                :eyebrow="t('listings.currentStore', { store: currentStore?.name ?? t('dashboard.noStore') })"
                :description="t('listings.editPage.description')"
            >
                <template #actions>
                    <Link href="/admin/listings" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50">{{ t('listings.back') }}</Link>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-admin-neutral">
                <StatusBadge :status="listing.status" />
                <span>{{ t('listings.editPage.slug') }} <strong class="ml-1 font-mono font-medium text-admin-text-secondary">{{ listing.storefront_slug }}</strong></span>
                <span>{{ t('listings.editPage.firstPublished') }} <strong class="ml-1 font-medium text-admin-text-secondary">{{ formatPublishedAt(listing.published_at) }}</strong></span>
            </div>

            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <aside v-if="isArchived" class="flex gap-3 rounded-md border border-admin-border-strong bg-admin-canvas px-4 py-3" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-neutral" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5 8h14v11H5V8Zm2-3h10v3H7V5Zm3 7h4" /></svg>
                <div><p class="text-sm font-semibold text-admin-text">{{ t('listings.editPage.archivedTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-neutral">{{ t('listings.editPage.archivedHelp') }}</p></div>
            </aside>
            <aside v-else-if="!canUpdate" class="flex gap-3 rounded-md border border-admin-border-strong bg-admin-canvas px-4 py-3" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-neutral" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 11V8a5 5 0 0 1 10 0v3m-11 0h12v9H6v-9Z" /></svg>
                <div><p class="text-sm font-semibold text-admin-text">{{ t('listings.editPage.readOnlyTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-neutral">{{ t('listings.editPage.readOnlyHelp') }}</p></div>
            </aside>

            <div v-if="localizedActionError || updateError('listing')" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3">
                <FieldError :message="localizedActionError ?? updateError('listing')" />
            </div>

            <section v-if="!isArchived && (canPublish || canArchive)" class="admin-card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div><p class="admin-label text-admin-secondary">{{ t('listings.editPage.lifecycleSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('listings.editPage.lifecycle') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('listings.editPage.lifecycleHelp') }}</p></div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                    <button v-if="canPublish && listing.status !== 'PUBLISHED'" type="button" :disabled="transition.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60" @click="runTransition('publish')">{{ activeTransition === 'publish' ? t('listings.publishing') : t('listings.publish') }}</button>
                    <button v-if="canPublish && listing.status === 'PUBLISHED'" type="button" :disabled="transition.processing" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60" @click="runTransition('unpublish')">{{ activeTransition === 'unpublish' ? t('listings.unpublishing') : t('listings.unpublish') }}</button>
                    <button v-if="canArchive" type="button" :disabled="transition.processing" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-4 text-sm font-semibold text-admin-danger hover:bg-admin-danger-bg disabled:cursor-wait disabled:opacity-60" @click="archive">{{ activeTransition === 'archive' ? t('common.archiving') : t('common.archive') }}</button>
                </div>
            </section>

            <section class="admin-card overflow-hidden">
                <header class="flex flex-col gap-2 border-b border-admin-border px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div><p class="admin-label text-admin-secondary">{{ t('listings.editPage.settingsSection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('listings.editPage.generalSettings') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('listings.editPage.generalSettingsHelp') }}</p></div>
                    <span v-if="!canEdit" class="rounded border border-admin-border bg-slate-50 px-2 py-1 text-xs font-medium text-admin-neutral">{{ t('common.readOnly') }}</span>
                </header>
                <fieldset :disabled="!canEdit" class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                    <div>
                        <div class="mb-1.5 flex items-end justify-between gap-3"><label for="listing-edit-slug" class="text-sm font-semibold text-admin-text">{{ t('listings.editPage.slug') }} <span class="text-admin-danger" aria-hidden="true">*</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ form.storefront_slug.length }} / 180</span></div>
                        <input id="listing-edit-slug" v-model="form.storefront_slug" required maxlength="180" autocomplete="off" spellcheck="false" class="admin-control w-full px-3 py-2 font-mono text-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-admin-neutral" :class="form.errors.storefront_slug ? 'border-admin-danger' : ''" :aria-invalid="form.errors.storefront_slug ? 'true' : undefined" aria-describedby="listing-edit-slug-help listing-edit-slug-error" />
                        <p id="listing-edit-slug-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('listings.editPage.slugHelp') }}</p>
                        <FieldError id="listing-edit-slug-error" :message="updateError('storefront_slug')" />
                    </div>
                    <div>
                        <label for="listing-edit-price-list" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('listings.editPage.priceListOverride') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label>
                        <select id="listing-edit-price-list" v-model="form.price_list_id" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-admin-neutral" :class="form.errors.price_list_id ? 'border-admin-danger' : ''" :aria-invalid="form.errors.price_list_id ? 'true' : undefined" aria-describedby="listing-edit-price-list-help listing-edit-price-list-error">
                            <option value="">{{ t('listings.editPage.useStoreDefault') }}</option>
                            <option v-for="list in priceLists" :key="list.id" :value="list.id">{{ list.name }} ({{ list.currency }}){{ list.is_default ? ` · ${t('listings.companyDefault')}` : '' }}</option>
                        </select>
                        <p id="listing-edit-price-list-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('listings.editPage.effectiveNow', { list: listing.effective_price_list ? `${listing.effective_price_list.name} (${listing.effective_price_list.currency})` : t('listings.editPage.noActivePriceList') }) }}</p>
                        <FieldError id="listing-edit-price-list-error" :message="updateError('price_list_id')" />
                    </div>
                </fieldset>
            </section>

            <section class="admin-card overflow-hidden">
                <header class="grid gap-4 border-b border-admin-border px-5 py-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,32rem)] lg:items-end">
                    <div><p class="admin-label text-admin-secondary">{{ t('listings.editPage.variantsSection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('listings.editPage.variants') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('listings.editPage.variantsHelp') }}</p></div>
                    <form class="flex flex-col gap-2 sm:flex-row" role="search" @submit.prevent="searchVariants">
                        <label for="listing-variant-search" class="sr-only">{{ t('listings.editPage.searchVariants') }}</label>
                        <input id="listing-variant-search" v-model="variantSearch" type="search" autocomplete="off" :placeholder="t('listings.editPage.searchPlaceholder')" class="admin-control min-w-0 flex-1 px-3 text-sm" />
                        <div class="flex gap-2"><button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('common.search') }}</button><button v-if="hasVariantSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearVariantSearch">{{ t('common.clear') }}</button></div>
                    </form>
                </header>

                <FieldError v-if="variantSelectionError" class="mx-5 mt-4 sm:mx-6" :message="variantSelectionError" />

                <template v-if="variants.data.length">
                    <div class="hidden md:block">
                        <table class="admin-table w-full table-fixed text-left text-xs">
                            <thead><tr><th class="w-[11%] p-3">{{ t('listings.editPage.enabled') }}</th><th class="w-[22%] p-3">{{ t('listings.editPage.variant') }}</th><th class="w-[13%] p-3">{{ t('listings.editPage.catalogStatus') }}</th><th class="w-[16%] p-3 text-right">{{ t('listings.editPage.basePrice') }}</th><th class="w-[22%] p-3 text-right">{{ t('listings.editPage.promotion') }}</th><th class="w-[16%] p-3 text-right">{{ t('listings.editPage.effectivePrice') }}</th></tr></thead>
                            <tbody class="divide-y divide-admin-border">
                                <tr v-for="variant in variants.data" :key="variant.id" :class="variant.status !== 'ACTIVE' ? 'bg-slate-50/70' : ''">
                                    <td class="p-3"><label class="inline-flex min-h-8 items-center gap-2 font-medium"><input v-model="form.enabled_variant_ids" type="checkbox" :value="variant.id" :disabled="!canEdit || variant.status !== 'ACTIVE'" class="size-4 accent-admin-secondary disabled:cursor-not-allowed" :aria-label="t('listings.editPage.enableVariant', { sku: variant.sku })" /><span>{{ isEnabled(variant) ? t('listings.editPage.enabled') : t('listings.editPage.disabled') }}</span></label></td>
                                    <td class="p-3"><p class="truncate font-mono text-xs font-semibold text-admin-text" :title="variant.sku">{{ variant.sku }}</p><p class="mt-1 truncate text-[11px] text-admin-neutral">{{ variant.name ?? t('listings.editPage.defaultVariant') }}</p><p v-if="!variant.configured" class="mt-1 text-[10px] text-amber-700">{{ t('listings.editPage.notConfiguredYet') }}</p></td>
                                    <td class="p-3"><StatusBadge :status="variant.status" /><p v-if="variant.status !== 'ACTIVE'" class="mt-1.5 text-[10px] leading-4 text-admin-danger">{{ t('listings.editPage.inactiveCannotEnable') }}</p></td>
                                    <td class="p-3 text-right font-mono tabular-nums">{{ variant.price ? amount(variant.price.base_amount) : t('listings.editPage.missingPrice') }}</td>
                                    <td class="p-3 text-right"><template v-if="variant.price"><p class="font-mono tabular-nums">-{{ amount(variant.price.total_discount) }}</p><p class="mt-1 line-clamp-2 text-[10px] text-admin-neutral">{{ promotionNames(variant) }}</p></template><span v-else class="text-admin-neutral">—</span></td>
                                    <td class="p-3 text-right font-mono font-semibold tabular-nums">{{ variant.price ? amount(variant.price.final_amount) : t('common.unavailable') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-2.5 p-3 md:hidden">
                        <article v-for="variant in variants.data" :key="`mobile-${variant.id}`" class="rounded-md border border-admin-border bg-white p-3.5">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate font-mono text-xs font-semibold text-admin-text">{{ variant.sku }}</h3><p class="mt-1 truncate text-xs text-admin-neutral">{{ variant.name ?? t('listings.editPage.defaultVariant') }}</p></div><StatusBadge :status="variant.status" /></div>
                            <label class="mt-3 flex min-h-9 items-center justify-between gap-3 rounded-md border border-admin-border bg-admin-canvas px-3 text-xs font-semibold"><span>{{ isEnabled(variant) ? t('listings.editPage.enabled') : t('listings.editPage.disabled') }}</span><input v-model="form.enabled_variant_ids" type="checkbox" :value="variant.id" :disabled="!canEdit || variant.status !== 'ACTIVE'" class="size-4 accent-admin-secondary disabled:cursor-not-allowed" :aria-label="t('listings.editPage.enableVariant', { sku: variant.sku })" /></label>
                            <p v-if="variant.status !== 'ACTIVE'" class="mt-2 text-[10px] leading-4 text-admin-danger">{{ t('listings.editPage.inactiveCannotEnable') }}</p><p v-else-if="!variant.configured" class="mt-2 text-[10px] text-amber-700">{{ t('listings.editPage.notConfiguredYet') }}</p>
                            <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-admin-border pt-3 text-right"><div><dt class="admin-label">{{ t('listings.editPage.basePrice') }}</dt><dd class="mt-1 font-mono text-[11px]">{{ variant.price ? amount(variant.price.base_amount) : t('listings.editPage.missingPrice') }}</dd></div><div><dt class="admin-label">{{ t('listings.editPage.discount') }}</dt><dd class="mt-1 font-mono text-[11px]">{{ variant.price ? `-${amount(variant.price.total_discount)}` : '—' }}</dd></div><div><dt class="admin-label">{{ t('listings.editPage.effectivePrice') }}</dt><dd class="mt-1 font-mono text-[11px] font-semibold">{{ variant.price ? amount(variant.price.final_amount) : t('common.unavailable') }}</dd></div></dl>
                            <p v-if="variant.price" class="mt-2 text-right text-[10px] text-admin-neutral">{{ promotionNames(variant) }}</p>
                        </article>
                    </div>
                </template>

                <div v-else class="p-4 sm:p-6"><EmptyState :title="t('listings.editPage.noVariants')" :description="hasVariantSearch ? t('listings.editPage.noVariantsSearchHelp') : t('listings.editPage.noVariantsHelp')"><template v-if="hasVariantSearch" #actions><button type="button" class="admin-link text-sm" @click="clearVariantSearch">{{ t('listings.editPage.clearVariantSearch') }}</button></template></EmptyState></div>

                <footer class="grid gap-4 border-t border-admin-border bg-admin-canvas px-5 py-4 sm:px-6">
                    <Pagination :links="variants.links" :from="variants.from" :to="variants.to" :total="variants.total" preserve-state />
                    <div v-if="canEdit" class="flex justify-end"><button type="button" :disabled="form.processing" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto" @click="update">{{ form.processing ? t('common.saving') : t('listings.editPage.save') }}</button></div>
                </footer>
            </section>
        </section>
    </AdminLayout>
</template>
