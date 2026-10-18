<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

type CustomerStatus = 'ACTIVE' | 'INACTIVE' | 'BLOCKED' | 'ARCHIVED';

interface CustomerRow {
    id: string;
    display_name: string;
    email: string | null;
    phone: string | null;
    status: CustomerStatus;
    addresses_count: number;
    created_at: string;
}

interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    customers: { data: CustomerRow[]; links: PageLink[] };
    filters: { search: string; status: string };
    statuses: CustomerStatus[];
}>();
const page = usePage<PageProps>();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('customers.create'));
const canUpdate = computed(() => permissions.value.includes('customers.update'));
const canArchive = computed(() => permissions.value.includes('customers.archive'));
const applyFilters = () => router.get('/admin/customers', { search: search.value, status: status.value }, { preserveState: true, replace: true });
const clearFilters = () => {
    search.value = '';
    status.value = '';
    applyFilters();
};
const archive = (customer: CustomerRow) => {
    if (window.confirm(`Archive ${customer.display_name}?`)) router.post(`/admin/customers/${customer.id}/archive`);
};
</script>

<template>
    <Head title="Customers" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Tenant customers</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Customers.</h1></div>
                <Link v-if="canCreate" href="/admin/customers/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900">Create customer</Link>
            </div>
            <form class="mt-8 grid gap-3 border border-[#17211b]/15 bg-white p-5 md:grid-cols-[1fr_12rem_auto]" @submit.prevent="applyFilters">
                <input v-model="search" type="search" placeholder="Search name, email, or phone" class="min-w-0 border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700" />
                <select v-model="status" class="border border-[#17211b]/25 bg-white px-3 py-3"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select>
                <div class="flex gap-3"><button type="button" class="border border-[#17211b]/25 px-4 py-3 text-sm font-semibold" @click="clearFilters">Clear</button><button class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Filter</button></div>
            </form>
            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                <table class="w-full min-w-[850px] text-left text-sm"><thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Customer</th><th class="p-4">Email</th><th class="p-4">Phone</th><th class="p-4">Status</th><th class="p-4">Addresses</th><th class="p-4">Created</th><th class="p-4">Actions</th></tr></thead>
                    <tbody><tr v-for="customer in customers.data" :key="customer.id" class="border-b border-[#17211b]/10 last:border-0"><td class="p-4 font-semibold"><Link :href="`/admin/customers/${customer.id}`" class="text-emerald-800 underline">{{ customer.display_name }}</Link></td><td class="p-4">{{ customer.email ?? 'None' }}</td><td class="p-4">{{ customer.phone ?? 'None' }}</td><td class="p-4">{{ customer.status }}</td><td class="p-4">{{ customer.addresses_count }}</td><td class="p-4">{{ new Date(customer.created_at).toLocaleDateString() }}</td><td class="p-4"><div class="flex gap-3"><Link :href="`/admin/customers/${customer.id}`" class="font-semibold text-emerald-800">View</Link><Link v-if="canUpdate" :href="`/admin/customers/${customer.id}/edit`" class="font-semibold text-emerald-800">Edit</Link><button v-if="canArchive && customer.status !== 'ARCHIVED'" class="font-semibold text-red-800" @click="archive(customer)">Archive</button></div></td></tr><tr v-if="!customers.data.length"><td colspan="7" class="p-8 text-center text-[#637067]">No customers found.</td></tr></tbody>
                </table>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in customers.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
        </section>
    </AdminLayout>
</template>
