<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface FiscalDocumentRow { id: string; full_number: string; document_type: string; status: string; order_number: string; customer_name: string; currency: string; grand_total: string; issue_date: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: FiscalDocumentRow[]; links: PageLink[]; total: number; from: number | null; to: number | null }
interface Filters { search: string; type: string; status: string; from: string | null; until: string | null }

const props = defineProps<{ documents: Paginator; filters: Filters; documentTypes: string[]; statuses: string[] }>();
const search = ref(props.filters.search);
const type = ref(props.filters.type);
const status = ref(props.filters.status);
const from = ref(props.filters.from ?? '');
const until = ref(props.filters.until ?? '');
const applyFilters = () => router.get('/admin/fiscal-documents', { search: search.value, type: type.value, status: status.value, from: from.value, until: until.value }, { preserveState: true, replace: true });
const clearFilters = () => { search.value = ''; type.value = ''; status.value = ''; from.value = ''; until.value = ''; applyFilters(); };
</script>

<template>
    <Head title="Fiscal documents" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Issued records</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Fiscal documents.</h1></div><p class="max-w-md text-sm text-[#637067]">Read-only snapshots generated from orders for the active store.</p></div>
            <form class="mt-8 grid gap-3 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="applyFilters">
                <label class="text-xs font-semibold uppercase sm:col-span-2">Search<input v-model="search" class="mt-2 w-full border px-3 py-2 text-sm font-normal normal-case" placeholder="Number, customer, document or order" /></label>
                <label class="text-xs font-semibold uppercase">Type<select v-model="type" class="mt-2 w-full border bg-white px-3 py-2 text-sm font-normal"><option value="">All types</option><option v-for="value in documentTypes" :key="value" :value="value">{{ value }}</option></select></label>
                <label class="text-xs font-semibold uppercase">Status<select v-model="status" class="mt-2 w-full border bg-white px-3 py-2 text-sm font-normal"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                <label class="text-xs font-semibold uppercase">From<input v-model="from" type="date" class="mt-2 w-full border px-3 py-2 text-sm font-normal" /></label>
                <label class="text-xs font-semibold uppercase">Until<input v-model="until" type="date" class="mt-2 w-full border px-3 py-2 text-sm font-normal" /></label>
                <div class="flex gap-3 sm:col-span-2 lg:col-span-6"><button class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Filter</button><button type="button" class="border border-[#17211b] px-5 py-3 text-sm font-semibold" @click="clearFilters">Clear</button></div>
            </form>
            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-white">
                <table class="min-w-[820px] w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Document</th><th class="p-4">Type / status</th><th class="p-4">Order</th><th class="p-4">Customer</th><th class="p-4">Total</th><th class="p-4">Issue date</th></tr></thead><tbody>
                    <tr v-for="document in documents.data" :key="document.id" class="border-t border-[#17211b]/10"><td class="p-4"><Link :href="`/admin/fiscal-documents/${document.id}`" class="font-mono font-semibold text-emerald-800 underline">{{ document.full_number }}</Link></td><td class="p-4"><p class="font-semibold">{{ document.document_type }}</p><p class="mt-1 font-mono text-xs text-[#637067]">{{ document.status }}</p></td><td class="p-4 font-mono">{{ document.order_number }}</td><td class="p-4">{{ document.customer_name }}</td><td class="p-4 font-mono">{{ document.currency }} {{ document.grand_total }}</td><td class="p-4 font-mono">{{ document.issue_date }}</td></tr>
                    <tr v-if="!documents.data.length"><td colspan="6" class="p-10 text-center text-[#637067]">No fiscal documents match these filters.</td></tr>
                </tbody></table>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4"><p class="text-sm text-[#637067]">Showing {{ documents.from ?? 0 }}–{{ documents.to ?? 0 }} of {{ documents.total }}</p><nav class="flex flex-wrap gap-2"><template v-for="link in documents.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : 'bg-white'" v-html="link.label" /><span v-else class="border px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav></div>
        </section>
    </AdminLayout>
</template>
