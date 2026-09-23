<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface ProductChoice { id: string; name: string; slug: string; sku: string | null }
interface PriceList { id: string; name: string; currency: string; is_default: boolean }

const props = defineProps<{ products: ProductChoice[]; priceLists: PriceList[]; productSearch: string }>();
const form = useForm({ product_id: '', storefront_slug: '', price_list_id: '' });
const productSearch = ref(props.productSearch);
const slugWasEdited = ref(false);

const searchProducts = () => router.get('/admin/listings/create', { product_search: productSearch.value }, { preserveState: true, replace: true });
const selectProduct = (product: ProductChoice) => {
    form.product_id = product.id;
    if (!slugWasEdited.value) form.storefront_slug = product.slug;
};
const submit = () => form.post('/admin/listings');
</script>

<template>
    <Head title="Create listing" />
    <AdminLayout v-slot="{ currentStore }">
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Active store / {{ currentStore?.name }}</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create a listing.</h1><p class="mt-3 text-sm text-[#637067]">Select an active, unlisted product from the current company catalog.</p></div><Link href="/admin/listings" class="font-semibold text-emerald-800">Back to listings</Link></div>

            <form class="mt-8 flex max-w-xl gap-3" @submit.prevent="searchProducts"><input v-model="productSearch" type="search" placeholder="Search product name or SKU" class="min-w-0 flex-1 border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700" /><button class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Find products</button></form>

            <form class="mt-6 grid gap-8 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="submit">
                <fieldset><legend class="text-lg font-semibold">Product</legend><p class="mt-1 text-sm text-[#637067]">Up to 50 matching products are shown.</p><div class="mt-4 grid max-h-80 gap-2 overflow-y-auto sm:grid-cols-2"><label v-for="product in products" :key="product.id" class="flex cursor-pointer items-start gap-3 border p-4" :class="form.product_id === product.id ? 'border-emerald-800 bg-emerald-50' : 'border-[#17211b]/15 bg-white'"><input :checked="form.product_id === product.id" type="radio" name="product_id" :value="product.id" class="mt-1" @change="selectProduct(product)" /><span><strong>{{ product.name }}</strong><small class="mt-1 block font-mono text-[#637067]">{{ product.sku ?? 'No default SKU' }}</small></span></label><p v-if="!products.length" class="p-4 text-sm text-[#637067] sm:col-span-2">No available products match this search.</p></div><FieldError :message="form.errors.product_id" /></fieldset>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div><label for="storefront_slug" class="mb-2 block text-sm font-semibold">Storefront slug</label><input id="storefront_slug" v-model="form.storefront_slug" required maxlength="180" placeholder="product-url" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 font-mono" @input="slugWasEdited = true" /><p class="mt-2 text-xs text-[#637067]">Used in this store's product URL. It will be normalized when saved.</p><FieldError :message="form.errors.storefront_slug" /></div>
                    <div><label for="price_list_id" class="mb-2 block text-sm font-semibold">Price list override (optional)</label><select id="price_list_id" v-model="form.price_list_id" class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="">Use the store default</option><option v-for="list in priceLists" :key="list.id" :value="list.id">{{ list.name }} ({{ list.currency }}){{ list.is_default ? ' / company default' : '' }}</option></select><p class="mt-2 text-xs text-[#637067]">Leave blank to follow future changes to the store default.</p><FieldError :message="form.errors.price_list_id" /></div>
                </div>

                <div class="flex flex-wrap gap-4"><button :disabled="form.processing || !form.product_id" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Creating...' : 'Create listing' }}</button><Link href="/admin/listings" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div>
            </form>
        </section>
    </AdminLayout>
</template>
