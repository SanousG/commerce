<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface CheckoutRow { id: string; token_hint: string; customer: string; status: string; grand_total: string; currency: string | null; expires_at: string | null; created_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
const props = defineProps<{ checkouts: { data: CheckoutRow[]; links: PageLink[] }; filters: { search: string; status: string } }>();
const page = usePage<PageProps>();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('checkouts.manage') ?? false);
const submit = () => router.get('/admin/checkouts', { search: search.value, status: status.value }, { preserveState: true, replace: true });
const date = (value: string | null) => value ? new Date(value).toLocaleString() : 'Not set';
</script>

<template>
    <Head title="Checkouts" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Reserved intent</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Checkouts.</h1></div>
                <Link v-if="canManage" href="/admin/checkouts/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Start checkout</Link>
            </div>
            <form class="mt-8 grid max-w-2xl gap-3 sm:grid-cols-[1fr_12rem_auto]" @submit.prevent="submit">
                <input v-model="search" type="search" placeholder="Token prefix, name, or email" class="border border-[#17211b]/25 bg-white px-4 py-3" />
                <select v-model="status" class="border border-[#17211b]/25 bg-white px-4 py-3"><option value="">All statuses</option><option v-for="value in ['PENDING','READY','COMPLETED','EXPIRED','CANCELLED','FAILED']" :key="value">{{ value }}</option></select>
                <button class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Filter</button>
            </form>
            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                <table class="w-full min-w-[840px] text-left text-sm"><thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Checkout</th><th class="p-4">Customer</th><th class="p-4">Status</th><th class="p-4">Total</th><th class="p-4">Expires</th><th class="p-4">Created</th><th class="p-4"></th></tr></thead>
                    <tbody><tr v-for="checkout in checkouts.data" :key="checkout.id" class="border-b border-[#17211b]/10"><td class="p-4 font-mono font-semibold">{{ checkout.token_hint }}</td><td class="p-4">{{ checkout.customer }}</td><td class="p-4 font-mono text-xs">{{ checkout.status }}</td><td class="p-4 font-mono font-semibold">{{ checkout.currency }} {{ checkout.grand_total }}</td><td class="p-4 text-xs">{{ date(checkout.expires_at) }}</td><td class="p-4 text-xs">{{ date(checkout.created_at) }}</td><td class="p-4"><Link :href="`/admin/checkouts/${checkout.id}`" class="font-semibold text-emerald-800">View</Link></td></tr><tr v-if="!checkouts.data.length"><td colspan="7" class="p-8 text-center text-[#637067]">No checkouts found.</td></tr></tbody>
                </table>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in checkouts.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : ''" v-html="link.label" /><span v-else class="border px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
        </section>
    </AdminLayout>
</template>
