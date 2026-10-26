<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface ProductRow {
    id: string;
    name: string;
    slug: string;
    status: 'DRAFT' | 'ACTIVE' | 'ARCHIVED';
    default_sku: string | null;
    variants_count: number;
    categories: string[];
}

interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    products: {
        data: ProductRow[];
        links: PageLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    filters: { search: string };
}>();

const page = usePage<PageProps>();
const search = ref(props.filters.search);
const searching = ref(false);
const archivingId = ref<string | null>(null);
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('products.create'));
const canUpdate = computed(() => permissions.value.includes('products.update'));
const canArchive = computed(() => permissions.value.includes('products.archive'));
const hasSearch = computed(() => props.filters.search !== '');
const { t } = useI18n();

watch(() => props.filters.search, (value) => { search.value = value; });

const visitIndex = (value: string) => router.get(
    '/admin/products',
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
const archive = (product: ProductRow) => {
    if (!window.confirm(t('products.archiveConfirm', { name: product.name }))) return;

    archivingId.value = product.id;
    router.patch(`/admin/products/${product.id}/archive`, {}, {
        preserveScroll: true,
        onFinish: () => { archivingId.value = null; },
    });
};
</script>

<template>
    <Head :title="t('products.title')" />

    <AdminLayout>
        <div class="mx-auto w-full max-w-[90rem] space-y-5 sm:space-y-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('products.title') }]" />

            <PageHeader :title="t('products.title')" :eyebrow="t('common.allCompany')" :description="t('products.indexDescription')">
                <template #actions>
                    <Link v-if="canCreate" href="/admin/products/create" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>
                        {{ t('products.create') }}
                    </Link>
                </template>
            </PageHeader>

            <StatusNotice :message="page.props.status" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <form class="admin-card flex flex-col gap-3 p-3 sm:flex-row sm:items-end sm:p-4" role="search" @submit.prevent="submitSearch">
                <label class="grid min-w-0 flex-1 gap-1.5" for="product-search">
                    <span class="admin-label">{{ t('products.searchCatalog') }}</span>
                    <span class="relative">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-admin-neutral" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="m16 16 4 4" /></svg>
                        <input id="product-search" v-model="search" type="search" autocomplete="off" :placeholder="t('products.searchPlaceholder')" class="admin-control w-full pl-9 pr-3 text-sm" />
                    </span>
                </label>
                <div class="flex gap-2">
                    <button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-primary bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:flex-none">
                        {{ searching ? t('common.searching') : t('common.search') }}
                    </button>
                    <button v-if="hasSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearSearch">{{ t('common.clear') }}</button>
                </div>
            </form>

            <template v-if="products.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs">
                        <thead><tr><th class="w-[30%] p-3">{{ t('products.product') }}</th><th class="w-[11%] p-3">{{ t('common.status') }}</th><th class="w-[16%] p-3">{{ t('products.defaultSku') }}</th><th class="w-[10%] p-3 text-right">{{ t('products.variants') }}</th><th class="w-[21%] p-3">{{ t('common.categories') }}</th><th class="w-[12%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-admin-border">
                            <tr v-for="product in products.data" :key="product.id">
                                <td class="p-3"><p class="truncate text-sm font-semibold text-admin-text" :title="product.name">{{ product.name }}</p><p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral" :title="product.slug">{{ product.slug }}</p></td>
                                <td class="p-3"><StatusBadge :status="product.status" /></td>
                                <td class="p-3 font-mono text-[11px] text-admin-text-secondary">{{ product.default_sku || '—' }}</td>
                                <td class="p-3 text-right font-mono font-semibold tabular-nums">{{ product.variants_count }}</td>
                                <td class="p-3"><div v-if="product.categories.length" class="flex flex-wrap gap-1"><span v-for="category in product.categories" :key="category" class="rounded border border-admin-border bg-slate-50 px-1.5 py-0.5 text-[10px] text-admin-text-secondary">{{ category }}</span></div><span v-else class="text-admin-neutral">{{ t('common.none') }}</span></td>
                                <td class="p-3"><div class="flex items-center justify-end gap-3"><Link :href="`/admin/products/${product.id}/edit`" class="admin-link">{{ canUpdate ? t('common.edit') : t('common.view') }}</Link><button v-if="canArchive && product.status !== 'ARCHIVED'" type="button" :disabled="archivingId === product.id" class="font-semibold text-admin-danger hover:underline disabled:cursor-wait disabled:opacity-50" @click="archive(product)">{{ archivingId === product.id ? t('common.archiving') : t('common.archive') }}</button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-2.5 md:hidden">
                    <article v-for="product in products.data" :key="`mobile-${product.id}`" class="admin-card p-3.5">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="text-sm font-semibold leading-5 text-admin-text">{{ product.name }}</h2><p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral">{{ product.slug }}</p></div><StatusBadge :status="product.status" /></div>
                        <dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('products.defaultSku') }}</dt><dd class="mt-1 truncate font-mono text-xs text-admin-text-secondary">{{ product.default_sku || '—' }}</dd></div><div><dt class="admin-label">{{ t('products.variants') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ product.variants_count }}</dd></div></dl>
                        <div v-if="product.categories.length" class="mt-2.5 flex flex-wrap gap-1"><span v-for="category in product.categories" :key="category" class="rounded border border-admin-border bg-slate-50 px-1.5 py-0.5 text-[10px] text-admin-text-secondary">{{ category }}</span></div><p v-else class="mt-2.5 text-xs text-admin-neutral">{{ t('products.noCategories') }}</p>
                        <div class="mt-3 flex items-center justify-end gap-4 border-t border-admin-border pt-3"><Link :href="`/admin/products/${product.id}/edit`" class="admin-link text-xs">{{ canUpdate ? t('products.editProduct') : t('products.viewProduct') }}</Link><button v-if="canArchive && product.status !== 'ARCHIVED'" type="button" :disabled="archivingId === product.id" class="text-xs font-semibold text-admin-danger disabled:cursor-wait disabled:opacity-50" @click="archive(product)">{{ archivingId === product.id ? t('common.archiving') : t('common.archive') }}</button></div>
                    </article>
                </div>
            </template>

            <EmptyState v-else :title="hasSearch ? t('products.noMatch') : t('products.noProducts')" :description="hasSearch ? t('products.noMatchHelp') : t('products.noProductsHelp')">
                <template v-if="hasSearch" #actions><button type="button" class="admin-link text-sm" @click="clearSearch">{{ t('products.clearSearch') }}</button></template>
                <template v-else-if="canCreate" #actions><Link href="/admin/products/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover">{{ t('products.create') }}</Link></template>
            </EmptyState>

            <Pagination :links="products.links" :from="products.from" :to="products.to" :total="products.total" />
        </div>
    </AdminLayout>
</template>
