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

interface ListingRow {
    id: string;
    product: string;
    status: 'DRAFT' | 'PUBLISHED' | 'UNPUBLISHED' | 'ARCHIVED';
    storefront_slug: string;
    enabled_variants_count: number;
    effective_price_list: string | null;
    published_at: string | null;
}

interface PriceList {
    id: string;
    name: string;
    currency: string;
    is_default: boolean;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    listings: {
        data: ListingRow[];
        links: PageLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    filters: { search: string };
    storeSettings: { default_price_list_id: string | null };
    priceLists: PriceList[];
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('listings.create'));
const canUpdate = computed(() => permissions.value.includes('listings.update'));
const canPublish = computed(() => permissions.value.includes('listings.publish'));
const canArchive = computed(() => permissions.value.includes('listings.archive'));
const search = ref(props.filters.search);
const searching = ref(false);
const activeTransition = ref<{ id: string; action: 'publish' | 'unpublish' | 'archive' } | null>(null);
const settings = useForm({ default_price_list_id: props.storeSettings.default_price_list_id ?? '' });
const transition = useForm({});
const { t, locale } = useI18n();

const hasSearch = computed(() => props.filters.search !== '');
const selectedDefaultPriceList = computed(() => props.priceLists.find(
    (list) => list.id === props.storeSettings.default_price_list_id,
));
const localizedStatus = computed(() => {
    const message = page.props.status;
    if (!message) return undefined;

    const keys: Record<string, string> = {
        'Listing published.': 'listings.notices.published',
        'Listing unpublished.': 'listings.notices.unpublished',
        'Listing archived.': 'listings.notices.archived',
        'Store listing settings updated.': 'listings.notices.settingsUpdated',
    };

    return keys[message] ? t(keys[message]) : t('listings.notices.completed');
});
const settingsError = computed(() => {
    const message = settings.errors.default_price_list_id;
    if (!message) return undefined;

    return message === 'Select an active price list from the current company.'
        ? t('listings.errors.activeCompanyPriceList')
        : t('listings.errors.invalidDefaultPriceList');
});
const transitionError = computed(() => {
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

watch(() => props.filters.search, (value) => {
    search.value = value;
});

const visitIndex = (value: string) => router.get(
    '/admin/listings',
    value.trim() === '' ? {} : { search: value.trim() },
    {
        preserveState: true,
        replace: true,
        onStart: () => { searching.value = true; },
        onFinish: () => { searching.value = false; },
    },
);
const submitSearch = () => visitIndex(search.value);
const clearSearch = () => {
    search.value = '';
    visitIndex('');
};
const saveSettings = () => settings.put('/admin/listings/settings/price-list', { preserveScroll: true });
const runTransition = (listing: ListingRow, action: 'publish' | 'unpublish' | 'archive') => {
    if (transition.processing) return;
    activeTransition.value = { id: listing.id, action };
    transition.clearErrors();
    transition.post(`/admin/listings/${listing.id}/${action}`, {
        preserveScroll: true,
        onFinish: () => { activeTransition.value = null; },
    });
};
const publish = (listing: ListingRow) => runTransition(listing, 'publish');
const unpublish = (listing: ListingRow) => runTransition(listing, 'unpublish');
const archive = (listing: ListingRow) => {
    if (!window.confirm(t('listings.archiveConfirm', { product: listing.product }))) return;
    runTransition(listing, 'archive');
};
const isTransitioning = (listing: ListingRow, action: 'publish' | 'unpublish' | 'archive') => (
    activeTransition.value?.id === listing.id && activeTransition.value.action === action
);
const formatPublishedAt = (value: string | null) => value
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : t('listings.never');
</script>

<template>
    <Head :title="t('listings.title')" />

    <AdminLayout v-slot="{ currentStore }">
        <div class="mx-auto w-full max-w-[90rem] space-y-5 sm:space-y-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('listings.title') }]" />

            <PageHeader
                :title="t('listings.title')"
                :eyebrow="t('listings.currentStore', { store: currentStore?.name ?? t('dashboard.noStore') })"
                :description="t('listings.description')"
            >
                <template #actions>
                    <Link v-if="canCreate" href="/admin/listings/create" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>
                        {{ t('listings.create') }}
                    </Link>
                </template>
            </PageHeader>

            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <div v-if="transitionError" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3">
                <FieldError :message="transitionError" />
            </div>

            <section class="admin-card p-4 sm:p-5" :aria-labelledby="canUpdate ? 'listing-price-list-heading' : undefined">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="admin-label text-admin-secondary">{{ t('common.currentStore') }}</p>
                        <h2 id="listing-price-list-heading" class="mt-1 text-base font-semibold text-admin-text">{{ t('listings.defaultPriceList') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('listings.defaultPriceHelp') }}</p>
                    </div>
                    <form v-if="canUpdate" class="flex flex-col gap-2 sm:flex-row sm:items-start" @submit.prevent="saveSettings">
                        <div class="grid gap-1.5">
                            <label for="listing-default-price-list" class="sr-only">{{ t('listings.defaultPriceList') }}</label>
                            <select
                                id="listing-default-price-list"
                                v-model="settings.default_price_list_id"
                                :aria-invalid="Boolean(settingsError)"
                                :aria-describedby="settingsError ? 'listing-default-price-list-error' : undefined"
                                class="admin-control min-w-0 px-3 text-sm sm:min-w-72"
                            >
                                <option value="">{{ t('listings.useCompanyDefault') }}</option>
                                <option v-for="list in priceLists" :key="list.id" :value="list.id">{{ list.name }} ({{ list.currency }}){{ list.is_default ? ` · ${t('listings.companyDefault')}` : '' }}</option>
                            </select>
                            <FieldError id="listing-default-price-list-error" :message="settingsError" />
                        </div>
                        <button type="submit" :disabled="settings.processing" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">
                            {{ settings.processing ? t('listings.savingDefault') : t('listings.saveDefault') }}
                        </button>
                    </form>
                    <p v-else class="font-mono text-sm font-medium text-admin-text-secondary">{{ selectedDefaultPriceList?.name ?? t('listings.companyDefault') }}</p>
                </div>
            </section>

            <form class="admin-card flex flex-col gap-3 p-3 sm:flex-row sm:items-end sm:p-4" role="search" @submit.prevent="submitSearch">
                <label class="grid min-w-0 flex-1 gap-1.5" for="listing-search">
                    <span class="admin-label">{{ t('listings.search') }}</span>
                    <span class="relative">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-admin-neutral" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="m16 16 4 4" /></svg>
                        <input id="listing-search" v-model="search" type="search" autocomplete="off" :placeholder="t('listings.searchPlaceholder')" class="admin-control w-full pl-9 pr-3 text-sm" />
                    </span>
                </label>
                <div class="flex gap-2">
                    <button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-primary bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('common.search') }}</button>
                    <button v-if="hasSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearSearch">{{ t('common.clear') }}</button>
                </div>
            </form>

            <template v-if="listings.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs">
                        <thead>
                            <tr>
                                <th class="w-[28%] p-3">{{ t('listings.product') }}</th>
                                <th class="w-[12%] p-3">{{ t('common.status') }}</th>
                                <th class="w-[12%] p-3 text-right">{{ t('listings.enabledVariants') }}</th>
                                <th class="w-[20%] p-3">{{ t('listings.effectivePriceList') }}</th>
                                <th class="w-[16%] p-3">{{ t('listings.publishedAt') }}</th>
                                <th class="w-[12%] p-3 text-right">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-admin-border">
                            <tr v-for="listing in listings.data" :key="listing.id">
                                <td class="p-3"><p class="truncate text-sm font-semibold text-admin-text" :title="listing.product">{{ listing.product }}</p><p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral" :title="listing.storefront_slug">/{{ listing.storefront_slug }}</p></td>
                                <td class="p-3"><StatusBadge :status="listing.status" /></td>
                                <td class="p-3 text-right font-mono font-semibold tabular-nums">{{ listing.enabled_variants_count }}</td>
                                <td class="p-3 text-admin-text-secondary"><span class="line-clamp-2">{{ listing.effective_price_list ?? t('common.notConfigured') }}</span></td>
                                <td class="p-3 text-[11px] text-admin-neutral">{{ formatPublishedAt(listing.published_at) }}</td>
                                <td class="p-3">
                                    <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
                                        <Link :href="`/admin/listings/${listing.id}/edit`" class="admin-link">{{ canUpdate && listing.status !== 'ARCHIVED' ? t('common.edit') : t('common.view') }}</Link>
                                        <button v-if="canPublish && listing.status !== 'PUBLISHED' && listing.status !== 'ARCHIVED'" type="button" :disabled="transition.processing" class="font-semibold text-admin-secondary hover:underline disabled:cursor-wait disabled:opacity-50" @click="publish(listing)">{{ isTransitioning(listing, 'publish') ? t('listings.publishing') : t('listings.publish') }}</button>
                                        <button v-if="canPublish && listing.status === 'PUBLISHED'" type="button" :disabled="transition.processing" class="font-semibold text-admin-secondary hover:underline disabled:cursor-wait disabled:opacity-50" @click="unpublish(listing)">{{ isTransitioning(listing, 'unpublish') ? t('listings.unpublishing') : t('listings.unpublish') }}</button>
                                        <button v-if="canArchive && listing.status !== 'ARCHIVED'" type="button" :disabled="transition.processing" class="font-semibold text-admin-danger hover:underline disabled:cursor-wait disabled:opacity-50" @click="archive(listing)">{{ isTransitioning(listing, 'archive') ? t('common.archiving') : t('common.archive') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-2.5 md:hidden">
                    <article v-for="listing in listings.data" :key="`mobile-${listing.id}`" class="admin-card p-3.5">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="text-sm font-semibold leading-5 text-admin-text">{{ listing.product }}</h2><p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral">/{{ listing.storefront_slug }}</p></div><StatusBadge :status="listing.status" /></div>
                        <dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('listings.enabledVariants') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ listing.enabled_variants_count }}</dd></div><div><dt class="admin-label">{{ t('listings.publishedAt') }}</dt><dd class="mt-1 text-[11px] text-admin-text-secondary">{{ formatPublishedAt(listing.published_at) }}</dd></div><div class="col-span-2"><dt class="admin-label">{{ t('listings.effectivePriceList') }}</dt><dd class="mt-1 text-xs text-admin-text-secondary">{{ listing.effective_price_list ?? t('common.notConfigured') }}</dd></div></dl>
                        <div class="mt-3 flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                            <Link :href="`/admin/listings/${listing.id}/edit`" class="admin-link text-xs">{{ canUpdate && listing.status !== 'ARCHIVED' ? t('common.edit') : t('common.view') }}</Link>
                            <button v-if="canPublish && listing.status !== 'PUBLISHED' && listing.status !== 'ARCHIVED'" type="button" :disabled="transition.processing" class="text-xs font-semibold text-admin-secondary disabled:cursor-wait disabled:opacity-50" @click="publish(listing)">{{ isTransitioning(listing, 'publish') ? t('listings.publishing') : t('listings.publish') }}</button>
                            <button v-if="canPublish && listing.status === 'PUBLISHED'" type="button" :disabled="transition.processing" class="text-xs font-semibold text-admin-secondary disabled:cursor-wait disabled:opacity-50" @click="unpublish(listing)">{{ isTransitioning(listing, 'unpublish') ? t('listings.unpublishing') : t('listings.unpublish') }}</button>
                            <button v-if="canArchive && listing.status !== 'ARCHIVED'" type="button" :disabled="transition.processing" class="text-xs font-semibold text-admin-danger disabled:cursor-wait disabled:opacity-50" @click="archive(listing)">{{ isTransitioning(listing, 'archive') ? t('common.archiving') : t('common.archive') }}</button>
                        </div>
                    </article>
                </div>
            </template>

            <EmptyState v-else :title="hasSearch ? t('listings.noMatch') : t('listings.noListings')" :description="hasSearch ? t('listings.noMatchHelp') : t('listings.noListingsHelp')">
                <template v-if="hasSearch" #actions><button type="button" class="admin-link text-sm" @click="clearSearch">{{ t('listings.clearSearch') }}</button></template>
                <template v-else-if="canCreate" #actions><Link href="/admin/listings/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover">{{ t('listings.create') }}</Link></template>
            </EmptyState>

            <Pagination :links="listings.links" :from="listings.from" :to="listings.to" :total="listings.total" />
        </div>
    </AdminLayout>
</template>
