<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface FiscalDocumentRow { id: string; full_number: string; document_type: string; status: string; order_number: string; customer_name: string; currency: string; grand_total: string; issue_date: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: FiscalDocumentRow[]; links: PageLink[]; total: number; from: number | null; to: number | null }
interface Filters { search: string; type: string; status: string; from: string | null; until: string | null }

const props = defineProps<{ documents: Paginator; filters: Filters; documentTypes: string[]; statuses: string[] }>();
const { t, te, locale } = useI18n();
const search = ref(props.filters.search);
const type = ref(props.filters.type);
const status = ref(props.filters.status);
const from = ref(props.filters.from ?? '');
const until = ref(props.filters.until ?? '');
const hasFilters = () => Boolean(search.value || type.value || status.value || from.value || until.value);
const typeLabel = (value: string) => te(`fiscal.types.${value}`) ? t(`fiscal.types.${value}`) : value;
const decimal = (value: string) => { const [integer, fraction = ''] = value.split('.'); return `${BigInt(integer).toLocaleString(locale.value)}${fraction ? `${locale.value.startsWith('es') ? ',' : '.'}${fraction}` : ''}`; };
const applyFilters = () => router.get('/admin/fiscal-documents', { search: search.value, type: type.value, status: status.value, from: from.value, until: until.value }, { preserveState: true, replace: true });
const clearFilters = () => { search.value = ''; type.value = ''; status.value = ''; from.value = ''; until.value = ''; applyFilters(); };
</script>

<template>
    <Head :title="t('fiscal.documentsTitle')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.compliance') }, { label: t('fiscal.documentsTitle') }]" />
            <PageHeader :title="t('fiscal.documentsTitle')" :eyebrow="t('common.currentStore')" :description="t('fiscal.documentsDescription')" />
            <form class="admin-card grid gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-6" @submit.prevent="applyFilters">
                <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('common.search') }}</span><input v-model="search" class="admin-control w-full px-3 text-sm" :placeholder="t('fiscal.searchPlaceholder')" /></label>
                <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.documentType') }}</span><select v-model="type" class="admin-control w-full px-3 text-sm"><option value="">{{ t('fiscal.allTypes') }}</option><option v-for="value in documentTypes" :key="value" :value="value">{{ typeLabel(value) }}</option></select></label>
                <label class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select v-model="status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('fiscal.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select></label>
                <label class="grid gap-1.5"><span class="admin-label">{{ t('common.from') }}</span><input v-model="from" type="date" class="admin-control w-full px-3 text-sm" /></label>
                <label class="grid gap-1.5"><span class="admin-label">{{ t('common.to') }}</span><input v-model="until" type="date" class="admin-control w-full px-3 text-sm" /></label>
                <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row lg:col-span-6"><button class="min-h-10 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('fiscal.filter') }}</button><button type="button" class="min-h-10 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold" @click="clearFilters">{{ t('fiscal.clearFilters') }}</button></div>
            </form>

            <template v-if="documents.data.length">
                <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[18%] p-3">{{ t('fiscal.document') }}</th><th class="w-[16%] p-3">{{ t('fiscal.typeAndStatus') }}</th><th class="w-[14%] p-3">{{ t('fiscal.order') }}</th><th class="w-[22%] p-3">{{ t('fiscal.customer') }}</th><th class="w-[16%] p-3 text-right">{{ t('fiscal.total') }}</th><th class="w-[14%] p-3">{{ t('fiscal.issueDate') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="document in documents.data" :key="document.id" class="hover:bg-slate-50"><td class="p-3"><Link :href="`/admin/fiscal-documents/${document.id}`" class="break-all font-mono font-semibold text-admin-secondary hover:underline">{{ document.full_number }}</Link></td><td class="p-3"><p class="font-medium">{{ typeLabel(document.document_type) }}</p><StatusBadge class="mt-1" :status="document.status" /></td><td class="p-3 font-mono">{{ document.order_number }}</td><td class="truncate p-3">{{ document.customer_name }}</td><td class="p-3 text-right font-mono tabular-nums">{{ document.currency }} {{ decimal(document.grand_total) }}</td><td class="p-3 font-mono">{{ document.issue_date }}</td></tr></tbody></table></div>
                <div class="grid gap-3 md:hidden"><article v-for="document in documents.data" :key="`mobile-${document.id}`" class="admin-card min-w-0 p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="admin-label">{{ typeLabel(document.document_type) }}</p><Link :href="`/admin/fiscal-documents/${document.id}`" class="mt-1 block break-all font-mono text-sm font-semibold text-admin-secondary">{{ document.full_number }}</Link></div><StatusBadge :status="document.status" /></div><dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs"><div><dt class="admin-label">{{ t('fiscal.order') }}</dt><dd class="mt-1 font-mono">{{ document.order_number }}</dd></div><div><dt class="admin-label">{{ t('fiscal.issueDate') }}</dt><dd class="mt-1 font-mono">{{ document.issue_date }}</dd></div><div class="col-span-2"><dt class="admin-label">{{ t('fiscal.customer') }}</dt><dd class="mt-1 break-words">{{ document.customer_name }}</dd></div><div class="col-span-2 border-t border-admin-border pt-3"><dt class="admin-label">{{ t('fiscal.total') }}</dt><dd class="mt-1 font-mono text-base font-semibold tabular-nums">{{ document.currency }} {{ decimal(document.grand_total) }}</dd></div></dl><Link :href="`/admin/fiscal-documents/${document.id}`" class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('fiscal.openDocument') }}</Link></article></div>
                <Pagination :links="documents.links" :from="documents.from" :to="documents.to" :total="documents.total" preserve-state />
            </template>
            <EmptyState v-else :title="hasFilters() ? t('fiscal.noMatch') : t('fiscal.noDocuments')" :description="hasFilters() ? t('fiscal.noMatchHelp') : t('fiscal.noDocumentsHelp')"><template v-if="hasFilters()" #actions><button type="button" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-3 text-xs font-semibold" @click="clearFilters">{{ t('fiscal.clearFilters') }}</button></template></EmptyState>
        </section>
    </AdminLayout>
</template>
