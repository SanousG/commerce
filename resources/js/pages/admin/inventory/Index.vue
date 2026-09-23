<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface VariantRow {
    product_name: string;
    variant_id: string;
    sku: string;
    on_hand: number;
    reserved: number;
    available: number;
    locations_with_stock: number;
}

interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    variants: { data: VariantRow[]; links: PageLink[] };
    filters: { search: string; can_adjust: boolean };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/admin/inventory', { search: search.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Inventory" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Stock control</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Inventory.</h1>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link href="/admin/inventory/locations" class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Locations</Link>
                    <Link v-if="filters.can_adjust" href="/admin/inventory/transfers/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900">New transfer</Link>
                </div>
            </div>

            <form class="mt-8 flex max-w-xl gap-3" @submit.prevent="submitSearch">
                <input v-model="search" type="search" placeholder="Search product or SKU" class="min-w-0 flex-1 border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700" />
                <button class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Search</button>
            </form>

            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Product</th><th class="p-4">SKU</th><th class="p-4">On hand</th><th class="p-4">Reserved</th><th class="p-4">Available</th><th class="p-4">Locations</th></tr></thead>
                    <tbody>
                        <tr v-for="variant in variants.data" :key="variant.variant_id" class="border-b border-[#17211b]/10 last:border-0">
                            <td class="p-4"><Link :href="`/admin/inventory/variants/${variant.variant_id}`" class="font-semibold text-emerald-800 hover:underline">{{ variant.product_name }}</Link></td>
                            <td class="p-4 font-mono">{{ variant.sku }}</td>
                            <td class="p-4 tabular-nums">{{ variant.on_hand }}</td>
                            <td class="p-4 tabular-nums">{{ variant.reserved }}</td>
                            <td class="p-4 font-semibold tabular-nums" :class="variant.available < 0 ? 'text-red-800' : ''">{{ variant.available }}</td>
                            <td class="p-4 tabular-nums">{{ variant.locations_with_stock }}</td>
                        </tr>
                        <tr v-if="!variants.data.length"><td colspan="6" class="p-8 text-center text-[#637067]">No inventory variants found.</td></tr>
                    </tbody>
                </table>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in variants.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
        </section>
    </AdminLayout>
</template>
