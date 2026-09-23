<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface ProductRow {
    id: string;
    name: string;
    slug: string;
    status: 'DRAFT' | 'ACTIVE' | 'ARCHIVED';
    default_sku: string;
    variants_count: number;
    categories: string[];
}

interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    products: { data: ProductRow[]; links: PageLink[] };
    filters: { search: string };
}>();

const page = usePage<PageProps>();
const search = ref(props.filters.search);
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('products.create'));
const canUpdate = computed(() => permissions.value.includes('products.update'));
const canArchive = computed(() => permissions.value.includes('products.archive'));

const submitSearch = () => router.get('/admin/products', { search: search.value }, { preserveState: true, replace: true });
const archive = (product: ProductRow) => {
    if (window.confirm(`Archive ${product.name}?`)) router.patch(`/admin/products/${product.id}/archive`);
};
</script>

<template>
    <Head title="Products" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Tenant catalog</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Products.</h1>
                </div>
                <Link v-if="canCreate" href="/admin/products/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900">Create product</Link>
            </div>

            <form class="mt-8 flex max-w-xl gap-3" @submit.prevent="submitSearch">
                <input v-model="search" type="search" placeholder="Search name or SKU" class="min-w-0 flex-1 border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700" />
                <button class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Search</button>
            </form>

            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Product</th><th class="p-4">Status</th><th class="p-4">Default SKU</th><th class="p-4">Variants</th><th class="p-4">Categories</th><th class="p-4">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="product in products.data" :key="product.id" class="border-b border-[#17211b]/10 last:border-0">
                            <td class="p-4"><strong>{{ product.name }}</strong><div class="mt-1 text-xs text-[#637067]">{{ product.slug }}</div></td>
                            <td class="p-4">{{ product.status }}</td><td class="p-4 font-mono">{{ product.default_sku }}</td><td class="p-4">{{ product.variants_count }}</td><td class="p-4">{{ product.categories.join(', ') || 'None' }}</td>
                            <td class="p-4"><div class="flex gap-3"><Link v-if="canUpdate" :href="`/admin/products/${product.id}/edit`" class="font-semibold text-emerald-800">Edit</Link><button v-if="canArchive && product.status !== 'ARCHIVED'" class="font-semibold text-red-800" @click="archive(product)">Archive</button></div></td>
                        </tr>
                        <tr v-if="!products.data.length"><td colspan="6" class="p-8 text-center text-[#637067]">No products found.</td></tr>
                    </tbody>
                </table>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in products.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
        </section>
    </AdminLayout>
</template>
