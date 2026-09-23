<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface CartRow {
    id: string;
    token_hint: string;
    customer: string | null;
    status: string;
    item_count: number;
    currency: string | null;
    subtotal: string;
    valid: boolean;
    expires_at: string | null;
    updated_at: string;
}

interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    carts: { data: CartRow[]; links: PageLink[] };
    filters: { search: string };
}>();

const page = usePage<PageProps>();
const search = ref(props.filters.search);
const createForm = useForm({});
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('carts.manage') ?? false);

const submitSearch = () => router.get('/admin/carts', { search: search.value }, { preserveState: true, replace: true });
const createCart = () => createForm.post('/admin/carts');
const formatDate = (value: string | null) => value ? new Date(value).toLocaleString() : 'No expiry';
const formatMoney = (currency: string | null, amount: string) => currency ? `${currency} ${amount}` : amount;
</script>

<template>
    <Head title="Carts" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Store activity</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Carts.</h1>
                </div>
                <button v-if="canManage" :disabled="createForm.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-60" @click="createCart">
                    {{ createForm.processing ? 'Creating...' : 'Create guest cart' }}
                </button>
            </div>

            <form class="mt-8 flex max-w-xl gap-3" @submit.prevent="submitSearch">
                <input v-model="search" type="search" placeholder="Search token prefix or customer" class="min-w-0 flex-1 border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700" />
                <button class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Search</button>
            </form>

            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                <table class="w-full min-w-[940px] text-left text-sm">
                    <thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase">
                        <tr><th class="p-4">Cart</th><th class="p-4">Customer</th><th class="p-4">Status</th><th class="p-4">Items</th><th class="p-4">Subtotal</th><th class="p-4">Expiry</th><th class="p-4">Updated</th><th class="p-4">Action</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="cart in carts.data" :key="cart.id" class="border-b border-[#17211b]/10 last:border-0">
                            <td class="p-4"><span class="font-mono font-semibold">{{ cart.token_hint }}</span><div class="mt-1 text-xs" :class="cart.valid ? 'text-emerald-800' : 'text-red-800'">{{ cart.valid ? 'Valid' : 'Needs attention' }}</div></td>
                            <td class="p-4">{{ cart.customer ?? 'Guest' }}</td>
                            <td class="p-4 font-mono text-xs">{{ cart.status }}</td>
                            <td class="p-4 tabular-nums">{{ cart.item_count }}</td>
                            <td class="p-4 font-mono font-semibold tabular-nums">{{ formatMoney(cart.currency, cart.subtotal) }}</td>
                            <td class="p-4 text-xs">{{ formatDate(cart.expires_at) }}</td>
                            <td class="p-4 text-xs">{{ formatDate(cart.updated_at) }}</td>
                            <td class="p-4"><Link :href="`/admin/carts/${cart.id}`" class="font-semibold text-emerald-800 hover:underline">View</Link></td>
                        </tr>
                        <tr v-if="!carts.data.length"><td colspan="8" class="p-8 text-center text-[#637067]">No carts found.</td></tr>
                    </tbody>
                </table>
            </div>

            <nav class="mt-6 flex flex-wrap gap-2">
                <template v-for="link in carts.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" />
                    <span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" />
                </template>
            </nav>
        </section>
    </AdminLayout>
</template>
