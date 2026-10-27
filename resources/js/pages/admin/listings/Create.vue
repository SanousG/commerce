<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface ProductChoice {
    id: string;
    name: string;
    slug: string;
    sku: string | null;
}

interface PriceList {
    id: string;
    name: string;
    currency: string;
    is_default: boolean;
}

const props = defineProps<{
    products: ProductChoice[];
    priceLists: PriceList[];
    productSearch: string;
}>();

const form = useForm({ product_id: '', storefront_slug: '', price_list_id: '' });
const productSearch = ref(props.productSearch);
const searching = ref(false);
const slugWasEdited = ref(false);
const { t } = useI18n();

const hasProductSearch = computed(() => props.productSearch !== '');
const selectedProduct = computed(() => props.products.find((product) => product.id === form.product_id));

watch(() => props.productSearch, (value) => {
    productSearch.value = value;
});

const visitCreate = (value: string) => router.get(
    '/admin/listings/create',
    value.trim() === '' ? {} : { product_search: value.trim() },
    {
        preserveState: true,
        replace: true,
        onStart: () => { searching.value = true; },
        onFinish: () => { searching.value = false; },
    },
);
const searchProducts = () => visitCreate(productSearch.value);
const clearProductSearch = () => {
    productSearch.value = '';
    visitCreate('');
};
const selectProduct = (product: ProductChoice) => {
    form.product_id = product.id;
    form.clearErrors('product_id');
    if (!slugWasEdited.value) form.storefront_slug = product.slug;
};
const localizedError = (field: 'store' | 'product_id' | 'storefront_slug' | 'price_list_id') => {
    const errors = form.errors as Record<string, string | undefined>;
    const message = errors[field];
    if (!message) return undefined;

    const known: Record<string, string> = {
        'The current store must be active.': 'listings.errors.storeInactive',
        'Only active products can be listed.': 'listings.createPage.errors.activeProduct',
        'This product already has a listing in the current store.': 'listings.createPage.errors.alreadyListed',
        'Select an active price list from the current company.': 'listings.errors.activeCompanyPriceList',
        'The storefront slug must contain letters or numbers.': 'listings.editPage.errors.slugCharacters',
    };
    const fallback: Record<typeof field, string> = {
        store: 'listings.errors.storeInactive',
        product_id: 'listings.createPage.errors.product',
        storefront_slug: 'listings.editPage.errors.slug',
        price_list_id: 'listings.errors.invalidDefaultPriceList',
    };

    return t(known[message] ?? fallback[field]);
};
const submit = () => form.post('/admin/listings');
</script>

<template>
    <Head :title="t('listings.createPage.title')" />

    <AdminLayout v-slot="{ currentStore }">
        <section class="mx-auto grid w-full max-w-6xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('listings.title'), href: '/admin/listings' }, { label: t('common.create') }]" />

            <PageHeader
                :title="t('listings.createPage.title')"
                :eyebrow="t('listings.currentStore', { store: currentStore?.name ?? t('dashboard.noStore') })"
                :description="t('listings.createPage.description')"
            >
                <template #actions>
                    <Link href="/admin/listings" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50">{{ t('listings.back') }}</Link>
                </template>
            </PageHeader>

            <aside class="flex gap-3 rounded-lg border border-admin-info-border bg-admin-info-bg px-4 py-3" :aria-label="t('listings.createPage.draftTitle')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-info" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="M12 11v5m0-8h.01" /></svg>
                <div><p class="text-xs font-semibold text-admin-text">{{ t('listings.createPage.draftTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('listings.createPage.draftHelp') }}</p></div>
            </aside>

            <div v-if="localizedError('store')" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3">
                <FieldError :message="localizedError('store')" />
            </div>

            <form class="admin-card flex flex-col gap-3 p-3 sm:flex-row sm:items-end sm:p-4" role="search" @submit.prevent="searchProducts">
                <label for="listing-product-search" class="grid min-w-0 flex-1 gap-1.5">
                    <span class="admin-label">{{ t('listings.createPage.searchProducts') }}</span>
                    <span class="relative">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-admin-neutral" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="m16 16 4 4" /></svg>
                        <input id="listing-product-search" v-model="productSearch" type="search" autocomplete="off" :placeholder="t('listings.createPage.searchPlaceholder')" class="admin-control w-full pl-9 pr-3 text-sm" />
                    </span>
                </label>
                <div class="flex gap-2">
                    <button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('listings.createPage.findProducts') }}</button>
                    <button v-if="hasProductSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearProductSearch">{{ t('common.clear') }}</button>
                </div>
            </form>

            <form class="admin-card divide-y divide-admin-border overflow-hidden" @submit.prevent="submit">
                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                        <p class="admin-label text-admin-secondary">{{ t('listings.createPage.productSection') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('listings.createPage.chooseProduct') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('listings.createPage.productLimit') }}</p>
                    </div>

                    <fieldset class="min-w-0">
                        <legend class="sr-only">{{ t('listings.createPage.chooseProduct') }}</legend>
                        <div v-if="products.length" class="grid max-h-[26rem] gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                            <label
                                v-for="product in products"
                                :key="product.id"
                                class="flex min-w-0 cursor-pointer items-start gap-3 rounded-md border p-3.5 transition"
                                :class="form.product_id === product.id ? 'border-admin-secondary bg-blue-50 ring-1 ring-admin-secondary' : 'border-admin-border bg-white hover:border-admin-border-strong hover:bg-slate-50'"
                            >
                                <input :checked="form.product_id === product.id" type="radio" name="product_id" :value="product.id" class="mt-0.5 size-4 shrink-0 accent-admin-secondary" @change="selectProduct(product)" />
                                <span class="min-w-0"><strong class="block truncate text-sm text-admin-text" :title="product.name">{{ product.name }}</strong><small class="mt-1 block truncate font-mono text-[10px] text-admin-neutral">{{ product.sku ?? t('listings.createPage.noDefaultSku') }}</small></span>
                            </label>
                        </div>
                        <EmptyState v-else :title="t('listings.createPage.noProducts')" :description="hasProductSearch ? t('listings.createPage.noProductsSearchHelp') : t('listings.createPage.noProductsHelp')">
                            <template v-if="hasProductSearch" #actions><button type="button" class="admin-link text-sm" @click="clearProductSearch">{{ t('listings.createPage.clearProductSearch') }}</button></template>
                        </EmptyState>
                        <FieldError class="mt-2" :message="localizedError('product_id')" />
                    </fieldset>
                </section>

                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                        <p class="admin-label text-admin-secondary">{{ t('listings.createPage.settingsSection') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('listings.createPage.initialSettings') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('listings.createPage.initialSettingsHelp') }}</p>
                    </div>

                    <div class="grid min-w-0 gap-5 lg:grid-cols-2">
                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3"><label for="listing-create-slug" class="text-sm font-semibold text-admin-text">{{ t('listings.editPage.slug') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ form.storefront_slug.length }} / 180</span></div>
                            <input id="listing-create-slug" v-model="form.storefront_slug" maxlength="180" autocomplete="off" spellcheck="false" :placeholder="selectedProduct?.slug ?? t('listings.createPage.slugPlaceholder')" class="admin-control w-full px-3 py-2 font-mono text-sm" :class="form.errors.storefront_slug ? 'border-admin-danger' : ''" :aria-invalid="form.errors.storefront_slug ? 'true' : undefined" aria-describedby="listing-create-slug-help listing-create-slug-error" @input="slugWasEdited = true" />
                            <p id="listing-create-slug-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('listings.createPage.slugHelp') }}</p>
                            <FieldError id="listing-create-slug-error" :message="localizedError('storefront_slug')" />
                        </div>

                        <div>
                            <label for="listing-create-price-list" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('listings.editPage.priceListOverride') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label>
                            <select id="listing-create-price-list" v-model="form.price_list_id" class="admin-control w-full px-3 py-2 text-sm" :class="form.errors.price_list_id ? 'border-admin-danger' : ''" :aria-invalid="form.errors.price_list_id ? 'true' : undefined" aria-describedby="listing-create-price-list-help listing-create-price-list-error">
                                <option value="">{{ t('listings.editPage.useStoreDefault') }}</option>
                                <option v-for="list in priceLists" :key="list.id" :value="list.id">{{ list.name }} ({{ list.currency }}){{ list.is_default ? ` · ${t('listings.companyDefault')}` : '' }}</option>
                            </select>
                            <p id="listing-create-price-list-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('listings.createPage.priceListHelp') }}</p>
                            <FieldError id="listing-create-price-list-error" :message="localizedError('price_list_id')" />
                        </div>
                    </div>
                </section>

                <footer class="flex flex-col-reverse gap-2 bg-admin-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                    <Link href="/admin/listings" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 sm:w-auto">{{ t('common.cancel') }}</Link>
                    <button type="submit" :disabled="form.processing || !form.product_id" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                        {{ form.processing ? t('listings.createPage.creating') : t('listings.create') }}
                        <svg v-if="!form.processing" viewBox="0 0 20 20" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m7 4 6 6-6 6" /></svg>
                    </button>
                </footer>
            </form>
        </section>
    </AdminLayout>
</template>
