<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface SupplierRow {
    id: string;
    name: string;
    code: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    status: string;
    products_count: number;
}
interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    suppliers: { data: SupplierRow[]; links: PageLink[] };
    filters: { search?: string; status?: string };
    statuses: string[];
}>();
const page = usePage<PageProps>();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('suppliers.create'));
const applyFilters = () => router.get('/admin/suppliers', { search: search.value, status: status.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Suppliers" />
    <AdminLayout><section>
        <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Procurement directory</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Suppliers.</h1></div><Link v-if="canCreate" href="/admin/suppliers/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Create supplier</Link></div>
        <form class="mt-8 grid gap-3 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-[minmax(0,1fr)_14rem_auto]" @submit.prevent="applyFilters"><input v-model="search" type="search" placeholder="Name, code, email, or phone" class="border border-[#17211b]/25 px-4 py-3" /><select v-model="status" class="border border-[#17211b]/25 px-4 py-3"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select><button class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Filter</button></form>
        <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]"><table class="w-full min-w-[800px] text-left text-sm"><thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Supplier</th><th class="p-4">Contact</th><th class="p-4">Status</th><th class="p-4">Products</th><th class="p-4">Action</th></tr></thead><tbody><tr v-for="supplier in suppliers.data" :key="supplier.id" class="border-b border-[#17211b]/10 last:border-0"><td class="p-4"><p class="font-semibold">{{ supplier.name }}</p><p class="font-mono text-xs text-[#637067]">{{ supplier.code }}</p></td><td class="p-4"><p>{{ supplier.contact_name ?? 'No contact' }}</p><p class="text-xs text-[#637067]">{{ supplier.email ?? supplier.phone ?? 'No details' }}</p></td><td class="p-4 font-mono text-xs">{{ supplier.status }}</td><td class="p-4">{{ supplier.products_count }}</td><td class="p-4"><Link :href="`/admin/suppliers/${supplier.id}/edit`" class="font-semibold text-emerald-800">View and edit</Link></td></tr><tr v-if="!suppliers.data.length"><td colspan="5" class="p-10 text-center text-[#637067]">No suppliers found.</td></tr></tbody></table></div>
        <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in suppliers.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
    </section></AdminLayout>
</template>
