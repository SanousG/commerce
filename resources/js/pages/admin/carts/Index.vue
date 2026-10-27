<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
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
interface Paginator { data: CartRow[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }

const props = defineProps<{ carts: Paginator; filters: { search: string } }>();
const page = usePage<PageProps>();
const { t, locale } = useI18n();
const search = ref(props.filters.search);
const searching = ref(false);
const createForm = useForm({});
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('carts.manage') ?? false);
const hasSearch = computed(() => props.filters.search !== '');
const localizedStatus = computed(() => page.props.status ? t('carts.notices.completed') : undefined);

watch(() => props.filters.search, (value) => { search.value = value; });
const visitIndex = (value: string) => router.get('/admin/carts', value.trim() ? { search: value.trim() } : {}, {
    preserveState: true,
    replace: true,
    onStart: () => { searching.value = true; },
    onFinish: () => { searching.value = false; },
});
const submitSearch = () => visitIndex(search.value);
const clearSearch = () => { search.value = ''; visitIndex(''); };
const createCart = () => createForm.post('/admin/carts');
const formatDate = (value: string | null) => value
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : t('carts.noExpiry');
const formatMoney = (currency: string | null, amount: string) => currency ? `${currency} ${amount}` : amount;
</script>

<template>
    <Head :title="t('carts.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('carts.title') }]" />
            <PageHeader :title="t('carts.title')" :eyebrow="t('common.currentStore')" :description="t('carts.description')">
                <template #actions>
                    <button v-if="canManage" type="button" :disabled="createForm.processing" class="inline-flex min-h-9 w-full items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:opacity-60 sm:w-auto" @click="createCart">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>
                        {{ createForm.processing ? t('common.creating') : t('carts.createGuest') }}
                    </button>
                </template>
            </PageHeader>
            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <form class="admin-card grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-4" role="search" @submit.prevent="submitSearch">
                <label class="grid min-w-0 gap-1.5" for="cart-search"><span class="admin-label">{{ t('carts.search') }}</span><span class="relative"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-admin-neutral" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="m16 16 4 4" /></svg><input id="cart-search" v-model="search" type="search" autocomplete="off" :placeholder="t('carts.searchPlaceholder')" class="admin-control w-full pl-9 pr-3 text-sm" /></span></label>
                <div class="flex gap-2"><button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('common.search') }}</button><button v-if="hasSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearSearch">{{ t('common.clear') }}</button></div>
            </form>

            <template v-if="carts.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[15%] p-3">{{ t('carts.cart') }}</th><th class="w-[17%] p-3">{{ t('carts.customer') }}</th><th class="w-[11%] p-3">{{ t('common.status') }}</th><th class="w-[8%] p-3 text-right">{{ t('carts.items') }}</th><th class="w-[14%] p-3 text-right">{{ t('carts.subtotal') }}</th><th class="w-[14%] p-3">{{ t('carts.expiry') }}</th><th class="w-[14%] p-3">{{ t('carts.updated') }}</th><th class="w-[7%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-admin-border"><tr v-for="cart in carts.data" :key="cart.id"><td class="p-3"><Link :href="`/admin/carts/${cart.id}`" class="admin-link font-mono text-xs">{{ cart.token_hint }}</Link><span class="mt-1 block text-[10px] font-semibold" :class="cart.valid ? 'text-admin-success' : 'text-admin-danger'">{{ cart.valid ? t('carts.valid') : t('carts.needsAttention') }}</span></td><td class="truncate p-3 text-admin-text-secondary" :title="cart.customer ?? undefined">{{ cart.customer ?? t('carts.guest') }}</td><td class="p-3"><StatusBadge :status="cart.status" /></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ cart.item_count }}</td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ formatMoney(cart.currency, cart.subtotal) }}</td><td class="p-3 text-admin-neutral">{{ formatDate(cart.expires_at) }}</td><td class="p-3 text-admin-neutral">{{ formatDate(cart.updated_at) }}</td><td class="p-3 text-right"><Link :href="`/admin/carts/${cart.id}`" class="admin-link">{{ t('common.view') }}</Link></td></tr></tbody>
                    </table>
                </div>
                <div class="grid gap-2.5 md:hidden"><article v-for="cart in carts.data" :key="`mobile-${cart.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="`/admin/carts/${cart.id}`" class="admin-link font-mono text-xs">{{ cart.token_hint }}</Link><p class="mt-1 truncate text-xs text-admin-text-secondary">{{ cart.customer ?? t('carts.guest') }}</p></div><StatusBadge :status="cart.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('carts.items') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ cart.item_count }}</dd></div><div><dt class="admin-label">{{ t('carts.subtotal') }}</dt><dd class="mt-1 text-right font-mono text-xs font-semibold">{{ formatMoney(cart.currency, cart.subtotal) }}</dd></div><div><dt class="admin-label">{{ t('carts.expiry') }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(cart.expires_at) }}</dd></div><div><dt class="admin-label">{{ t('carts.updated') }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(cart.updated_at) }}</dd></div></dl><div class="mt-3 flex items-center justify-between gap-3"><span class="text-[10px] font-semibold" :class="cart.valid ? 'text-admin-success' : 'text-admin-danger'">{{ cart.valid ? t('carts.valid') : t('carts.needsAttention') }}</span><Link :href="`/admin/carts/${cart.id}`" class="admin-link text-xs">{{ t('common.view') }}</Link></div></article></div>
            </template>
            <EmptyState v-else :title="hasSearch ? t('carts.noMatch') : t('carts.empty')" :description="hasSearch ? t('carts.noMatchHelp') : t('carts.emptyHelp')"><template v-if="hasSearch" #actions><button type="button" class="admin-link text-sm" @click="clearSearch">{{ t('carts.clearSearch') }}</button></template><template v-else-if="canManage" #actions><button type="button" :disabled="createForm.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60" @click="createCart">{{ t('carts.createGuest') }}</button></template></EmptyState>
            <Pagination :links="carts.links" :from="carts.from" :to="carts.to" :total="carts.total" preserve-state />
        </section>
    </AdminLayout>
</template>
