<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FieldError from '../../../components/FieldError.vue';
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
interface PriceList { id: string; name: string; currency: string; is_default: boolean }
interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    listings: { data: ListingRow[]; links: PageLink[] };
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
const settings = useForm({ default_price_list_id: props.storeSettings.default_price_list_id ?? '' });
const transition = useForm({});
const transitionErrors = computed(() => transition.errors as Record<string, string | undefined>);

const submitSearch = () => router.get('/admin/listings', { search: search.value }, { preserveState: true, replace: true });
const saveSettings = () => settings.put('/admin/listings/settings/price-list', { preserveScroll: true });
const publish = (listing: ListingRow) => transition.post(`/admin/listings/${listing.id}/publish`, { preserveScroll: true });
const unpublish = (listing: ListingRow) => transition.post(`/admin/listings/${listing.id}/unpublish`, { preserveScroll: true });
const archive = (listing: ListingRow) => {
    if (window.confirm(`Archive the ${listing.product} listing? It cannot be changed or published again.`)) {
        transition.post(`/admin/listings/${listing.id}/archive`);
    }
};
</script>

<template>
    <Head title="Listings" />
    <AdminLayout v-slot="{ currentStore }">
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Active store / {{ currentStore?.name }}</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Listings.</h1>
                    <p class="mt-3 text-sm text-[#637067]">Choose which catalog products and variants are available in this store.</p>
                </div>
                <Link v-if="canCreate" href="/admin/listings/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900">Create listing</Link>
            </div>

            <p v-if="page.props.status" class="mt-8 border-l-4 border-emerald-700 bg-white px-4 py-3 text-sm font-medium">{{ page.props.status }}</p>
            <div v-if="transitionErrors.listing || transitionErrors.price_list_id || transitionErrors.variants" class="mt-8 border-l-4 border-red-700 bg-white px-4 py-3"><FieldError :message="transitionErrors.listing ?? transitionErrors.price_list_id ?? transitionErrors.variants" /></div>

            <section class="mt-8 border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div><h2 class="text-lg font-semibold">Store default price list</h2><p class="mt-1 text-sm text-[#637067]">Listings without an override use this list. Company default is used when none is selected.</p></div>
                    <form v-if="canUpdate" class="flex flex-wrap items-start gap-3" @submit.prevent="saveSettings">
                        <div><select v-model="settings.default_price_list_id" aria-label="Store default price list" class="min-w-64 border border-[#17211b]/25 bg-white px-4 py-3"><option value="">Use company default</option><option v-for="list in priceLists" :key="list.id" :value="list.id">{{ list.name }} ({{ list.currency }}){{ list.is_default ? ' / company default' : '' }}</option></select><FieldError :message="settings.errors.default_price_list_id" /></div>
                        <button :disabled="settings.processing" class="border border-[#17211b] px-5 py-3 text-sm font-semibold disabled:opacity-60">{{ settings.processing ? 'Saving...' : 'Save default' }}</button>
                    </form>
                    <p v-else class="font-mono text-sm">{{ priceLists.find((list) => list.id === storeSettings.default_price_list_id)?.name ?? 'Company default' }}</p>
                </div>
            </section>

            <form class="mt-8 flex max-w-xl gap-3" @submit.prevent="submitSearch"><input v-model="search" type="search" placeholder="Search product, slug, or SKU" class="min-w-0 flex-1 border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700" /><button class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Search</button></form>

            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                <table class="w-full min-w-[920px] text-left text-sm">
                    <thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Product</th><th class="p-4">Status</th><th class="p-4">Enabled variants</th><th class="p-4">Effective price list</th><th class="p-4">Published</th><th class="p-4">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="listing in listings.data" :key="listing.id" class="border-b border-[#17211b]/10 last:border-0">
                            <td class="p-4"><strong>{{ listing.product }}</strong><div class="mt-1 font-mono text-xs text-[#637067]">/{{ listing.storefront_slug }}</div></td>
                            <td class="p-4"><span class="border px-2 py-1 font-mono text-xs">{{ listing.status }}</span></td>
                            <td class="p-4 tabular-nums">{{ listing.enabled_variants_count }}</td>
                            <td class="p-4">{{ listing.effective_price_list ?? 'Not configured' }}</td>
                            <td class="p-4 text-xs text-[#637067]">{{ listing.published_at ? new Date(listing.published_at).toLocaleString() : 'Never' }}</td>
                            <td class="p-4"><div class="flex flex-wrap gap-3"><Link :href="`/admin/listings/${listing.id}/edit`" class="font-semibold text-emerald-800">{{ canUpdate && listing.status !== 'ARCHIVED' ? 'Edit' : 'View' }}</Link><button v-if="canPublish && listing.status !== 'PUBLISHED' && listing.status !== 'ARCHIVED'" class="font-semibold" @click="publish(listing)">Publish</button><button v-if="canPublish && listing.status === 'PUBLISHED'" class="font-semibold" @click="unpublish(listing)">Unpublish</button><button v-if="canArchive && listing.status !== 'ARCHIVED'" class="font-semibold text-red-800" @click="archive(listing)">Archive</button></div></td>
                        </tr>
                        <tr v-if="!listings.data.length"><td colspan="6" class="p-8 text-center text-[#637067]">No listings found for this store.</td></tr>
                    </tbody>
                </table>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in listings.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
        </section>
    </AdminLayout>
</template>
