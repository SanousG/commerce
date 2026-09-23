<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PriceList { id: string; name: string; currency: string; is_default: boolean }
interface EffectivePrice { base_amount: string; final_amount: string; total_discount: string; applied_promotions: { id: string; name: string; internal_code: string | null }[] }
interface VariantRow { id: string; sku: string; name: string | null; status: string; configured: boolean; is_enabled: boolean; price: EffectivePrice | null }
interface Listing { id: string; product: string; status: 'DRAFT' | 'PUBLISHED' | 'UNPUBLISHED' | 'ARCHIVED'; storefront_slug: string; price_list_id: string | null; effective_price_list: { id: string; name: string; currency: string } | null; published_at: string | null }
interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    listing: Listing;
    variants: { data: VariantRow[]; links: PageLink[]; total?: number };
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
const variantSearch = ref(props.filters.variant_search);
const form = useForm({
    storefront_slug: props.listing.storefront_slug,
    price_list_id: props.listing.price_list_id ?? '',
    enabled_variant_ids: [...props.enabledVariantIds],
});
const transition = useForm({});
const actionErrors = computed(() => transition.errors as Record<string, string | undefined>);

const searchVariants = () => router.get(`/admin/listings/${props.listing.id}/edit`, { variant_search: variantSearch.value }, { replace: true });
const update = () => form.put(`/admin/listings/${props.listing.id}`, { preserveScroll: true });
const publish = () => transition.post(`/admin/listings/${props.listing.id}/publish`, { preserveScroll: true });
const unpublish = () => transition.post(`/admin/listings/${props.listing.id}/unpublish`, { preserveScroll: true });
const archive = () => {
    if (window.confirm(`Archive the ${props.listing.product} listing? It cannot be changed or published again.`)) transition.post(`/admin/listings/${props.listing.id}/archive`);
};
</script>

<template>
    <Head :title="listing.product" />
    <AdminLayout v-slot="{ currentStore }">
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Active store / {{ currentStore?.name }}</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ listing.product }}</h1><div class="mt-3 flex flex-wrap items-center gap-3 text-sm"><span class="border border-[#17211b]/25 px-2 py-1 font-mono text-xs">{{ listing.status }}</span><span class="text-[#637067]">{{ listing.published_at ? `First published ${new Date(listing.published_at).toLocaleString()}` : 'Never published' }}</span></div></div>
                <div class="flex flex-wrap gap-3"><Link href="/admin/listings" class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Back</Link><button v-if="canPublish && listing.status !== 'PUBLISHED' && !isArchived" class="bg-emerald-800 px-5 py-3 text-sm font-semibold text-white" @click="publish">Publish</button><button v-if="canPublish && listing.status === 'PUBLISHED'" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white" @click="unpublish">Unpublish</button><button v-if="canArchive && !isArchived" class="border border-red-800 px-5 py-3 text-sm font-semibold text-red-800" @click="archive">Archive</button></div>
            </div>

            <p v-if="page.props.status" class="mt-8 border-l-4 border-emerald-700 bg-white px-4 py-3 text-sm font-medium">{{ page.props.status }}</p>
            <p v-if="isArchived" class="mt-8 border-l-4 border-stone-500 bg-white px-4 py-3 text-sm">This listing is archived. Its settings and variants are permanently read-only.</p>
            <p v-else-if="!canUpdate" class="mt-8 border-l-4 border-stone-500 bg-white px-4 py-3 text-sm">You have view-only access to this listing.</p>
            <FieldError :message="actionErrors.listing" />
            <FieldError :message="actionErrors.price_list_id" />
            <FieldError :message="actionErrors.variants" />

            <div class="mt-8 grid gap-8">
                <section class="border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8">
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">General settings</p>
                    <div class="mt-5 grid gap-6 sm:grid-cols-2">
                        <div><label for="storefront_slug" class="mb-2 block text-sm font-semibold">Storefront slug</label><input id="storefront_slug" v-model="form.storefront_slug" :disabled="!canEdit" required maxlength="180" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 font-mono disabled:bg-stone-100 disabled:text-[#637067]" /><FieldError :message="form.errors.storefront_slug" /></div>
                        <div><label for="price_list_id" class="mb-2 block text-sm font-semibold">Price list override</label><select id="price_list_id" v-model="form.price_list_id" :disabled="!canEdit" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:bg-stone-100 disabled:text-[#637067]"><option value="">Use store default</option><option v-for="list in priceLists" :key="list.id" :value="list.id">{{ list.name }} ({{ list.currency }})</option></select><FieldError :message="form.errors.price_list_id" /><p class="mt-2 text-xs text-[#637067]">Effective now: {{ listing.effective_price_list ? `${listing.effective_price_list.name} (${listing.effective_price_list.currency})` : 'No active price list' }}</p></div>
                    </div>
                </section>

                <section class="border border-[#17211b]/15 bg-[#f8f5ed]">
                    <div class="flex flex-wrap items-end justify-between gap-5 p-6 sm:p-8"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Store availability</p><h2 class="mt-3 text-2xl font-semibold">Variants</h2><p class="mt-2 text-sm text-[#637067]">Only enabled, active variants with a base price can be sold. Promotion previews are calculated by the server.</p></div><form class="flex gap-3" @submit.prevent="searchVariants"><input v-model="variantSearch" type="search" placeholder="Search SKU or variant" class="min-w-0 border border-[#17211b]/25 bg-white px-4 py-3" /><button class="border border-[#17211b] px-4 py-3 text-sm font-semibold">Search</button></form></div>
                    <FieldError class="mx-6 sm:mx-8" :message="form.errors.enabled_variant_ids" />
                    <div class="overflow-x-auto border-t border-[#17211b]/15"><table class="w-full min-w-[900px] text-left text-sm"><thead class="border-b border-[#17211b]/15 bg-white font-mono text-xs uppercase"><tr><th class="p-4">Enabled</th><th class="p-4">Variant</th><th class="p-4">Configuration</th><th class="p-4">Base</th><th class="p-4">Promotion</th><th class="p-4">Effective</th></tr></thead><tbody>
                        <tr v-for="variant in variants.data" :key="variant.id" class="border-b border-[#17211b]/10 last:border-0" :class="variant.status !== 'ACTIVE' ? 'text-[#637067]' : ''"><td class="p-4"><input v-model="form.enabled_variant_ids" type="checkbox" :value="variant.id" :disabled="!canEdit || variant.status !== 'ACTIVE'" :aria-label="`Enable ${variant.sku}`" /></td><td class="p-4"><strong class="font-mono">{{ variant.sku }}</strong><div class="mt-1 text-xs">{{ variant.name ?? 'Default variant' }} / {{ variant.status }}</div></td><td class="p-4"><span :class="variant.configured ? 'text-emerald-800' : 'text-amber-800'">{{ variant.configured ? (variant.is_enabled ? 'Configured / enabled' : 'Configured / disabled') : 'Not yet configured' }}</span><div v-if="variant.status !== 'ACTIVE'" class="mt-1 text-xs text-red-800">Catalog inactive; cannot be enabled</div></td><td class="p-4 font-mono tabular-nums">{{ variant.price ? `${listing.effective_price_list?.currency ?? ''} ${variant.price.base_amount}` : 'Missing' }}</td><td class="p-4"><template v-if="variant.price"><span class="font-mono tabular-nums">-{{ listing.effective_price_list?.currency ?? '' }} {{ variant.price.total_discount }}</span><div class="mt-1 text-xs text-[#637067]">{{ variant.price.applied_promotions.map((promotion) => promotion.name).join(', ') || 'No promotion' }}</div></template><span v-else>-</span></td><td class="p-4 font-mono font-semibold tabular-nums">{{ variant.price ? `${listing.effective_price_list?.currency ?? ''} ${variant.price.final_amount}` : 'Unavailable' }}</td></tr>
                        <tr v-if="!variants.data.length"><td colspan="6" class="p-8 text-center text-[#637067]">No variants found.</td></tr>
                    </tbody></table></div>
                    <nav class="flex flex-wrap gap-2 border-t border-[#17211b]/15 p-6"><template v-for="link in variants.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
                </section>

                <button v-if="canEdit" :disabled="form.processing" class="w-fit bg-[#17211b] px-6 py-3 text-sm font-semibold text-white disabled:opacity-60" @click="update">{{ form.processing ? 'Saving...' : 'Save listing' }}</button>
            </div>
        </section>
    </AdminLayout>
</template>
