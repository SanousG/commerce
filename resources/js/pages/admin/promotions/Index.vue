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

interface PromotionRow {
    id: string; name: string; internal_code: string | null; status: 'DRAFT' | 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    discount_type: 'PERCENTAGE' | 'FIXED_AMOUNT'; discount_value: string; currency: string | null;
    starts_at: string | null; ends_at: string | null; priority: number; scope: 'ALL' | 'SELECTED';
    is_currently_eligible: boolean; products_count: number; categories_count: number; variants_count: number;
}
interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{ promotions: { data: PromotionRow[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }; filters: { search: string } }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('promotions.create'));
const canUpdate = computed(() => permissions.value.includes('promotions.update'));
const canArchive = computed(() => permissions.value.includes('promotions.archive'));
const search = ref(props.filters.search);
const searching = ref(false);
const activeArchive = ref<string | null>(null);
const lifecycle = useForm({});
const { t, locale } = useI18n();
const hasSearch = computed(() => props.filters.search !== '');
const localizedStatus = computed(() => {
    const keys: Record<string, string> = {
        'Promotion archived.': 'promotions.notices.archived',
        'Promotion created.': 'promotions.notices.created',
    };
    return page.props.status ? t(keys[page.props.status] ?? 'promotions.notices.completed') : undefined;
});

watch(() => props.filters.search, (value) => { search.value = value; });
const visitIndex = (value: string) => router.get('/admin/promotions', value.trim() ? { search: value.trim() } : {}, {
    preserveState: true, replace: true,
    onStart: () => { searching.value = true; },
    onFinish: () => { searching.value = false; },
});
const submitSearch = () => visitIndex(search.value);
const clearSearch = () => { search.value = ''; visitIndex(''); };
const archive = (promotion: PromotionRow) => {
    if (lifecycle.processing || !window.confirm(t('promotions.archiveConfirm', { name: promotion.name }))) return;
    activeArchive.value = promotion.id;
    lifecycle.patch(`/admin/promotions/${promotion.id}/archive`, { onFinish: () => { activeArchive.value = null; } });
};
const discount = (promotion: PromotionRow) => promotion.discount_type === 'PERCENTAGE'
    ? t('promotions.percentageValue', { value: promotion.discount_value })
    : `${promotion.currency ?? ''} ${promotion.discount_value}`.trim();
const targets = (promotion: PromotionRow) => promotion.scope === 'ALL'
    ? t('promotions.allCatalog')
    : t('promotions.targetSummary', { products: promotion.products_count, categories: promotion.categories_count, variants: promotion.variants_count });
const formatDate = (value: string | null, emptyKey: 'noStart' | 'noEnd') => value
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }).format(new Date(`${value}Z`))
    : t(`promotions.${emptyKey}`);
</script>

<template>
    <Head :title="t('promotions.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.merchandising') }, { label: t('promotions.title') }]" />
            <PageHeader :title="t('promotions.title')" :eyebrow="t('common.allCompany')" :description="t('promotions.description')">
                <template #actions><Link v-if="canCreate" href="/admin/promotions/create" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>{{ t('promotions.create') }}</Link></template>
            </PageHeader>
            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <form class="admin-card flex flex-col gap-3 p-3 sm:flex-row sm:items-end sm:p-4" role="search" @submit.prevent="submitSearch">
                <label class="grid min-w-0 flex-1 gap-1.5" for="promotion-search"><span class="admin-label">{{ t('promotions.search') }}</span><span class="relative"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-admin-neutral" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="m16 16 4 4" /></svg><input id="promotion-search" v-model="search" type="search" autocomplete="off" :placeholder="t('promotions.searchPlaceholder')" class="admin-control w-full pl-9 pr-3 text-sm" /></span></label>
                <div class="flex gap-2"><button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('common.search') }}</button><button v-if="hasSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearSearch">{{ t('common.clear') }}</button></div>
            </form>

            <template v-if="promotions.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[22%] p-3">{{ t('promotions.promotion') }}</th><th class="w-[13%] p-3">{{ t('promotions.discount') }}</th><th class="w-[14%] p-3">{{ t('common.status') }}</th><th class="w-[19%] p-3">{{ t('promotions.windowUtc') }}</th><th class="w-[8%] p-3 text-right">{{ t('promotions.priority') }}</th><th class="w-[15%] p-3">{{ t('promotions.scope') }}</th><th class="w-[9%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-admin-border"><tr v-for="promotion in promotions.data" :key="promotion.id"><td class="p-3"><p class="truncate text-sm font-semibold text-admin-text" :title="promotion.name">{{ promotion.name }}</p><p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral">{{ promotion.internal_code ?? t('promotions.noCode') }}</p></td><td class="p-3 font-mono font-semibold tabular-nums">{{ discount(promotion) }}</td><td class="p-3"><StatusBadge :status="promotion.status" /><p class="mt-1 text-[10px]" :class="promotion.is_currently_eligible ? 'text-admin-success' : 'text-admin-neutral'">{{ promotion.is_currently_eligible ? t('promotions.eligibleNow') : t('promotions.notEligibleNow') }}</p></td><td class="p-3 text-[11px] text-admin-text-secondary"><p>{{ formatDate(promotion.starts_at, 'noStart') }}</p><p class="mt-1">{{ formatDate(promotion.ends_at, 'noEnd') }}</p></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ promotion.priority }}</td><td class="p-3 text-[11px] text-admin-text-secondary">{{ targets(promotion) }}</td><td class="p-3"><div class="flex flex-col items-end gap-2"><Link :href="`/admin/promotions/${promotion.id}/edit`" class="admin-link">{{ canUpdate && promotion.status !== 'ARCHIVED' ? t('common.edit') : t('common.view') }}</Link><button v-if="canArchive && promotion.status !== 'ARCHIVED'" type="button" :disabled="lifecycle.processing" class="font-semibold text-admin-danger disabled:opacity-50" @click="archive(promotion)">{{ activeArchive === promotion.id ? t('common.archiving') : t('common.archive') }}</button></div></td></tr></tbody>
                    </table>
                </div>
                <div class="grid gap-2.5 md:hidden"><article v-for="promotion in promotions.data" :key="`mobile-${promotion.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="text-sm font-semibold text-admin-text">{{ promotion.name }}</h2><p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral">{{ promotion.internal_code ?? t('promotions.noCode') }}</p></div><StatusBadge :status="promotion.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('promotions.discount') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ discount(promotion) }}</dd></div><div><dt class="admin-label">{{ t('promotions.priority') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ promotion.priority }}</dd></div><div class="col-span-2"><dt class="admin-label">{{ t('promotions.scope') }}</dt><dd class="mt-1 text-xs text-admin-text-secondary">{{ targets(promotion) }}</dd></div><div><dt class="admin-label">{{ t('common.from') }}</dt><dd class="mt-1 text-[11px] text-admin-text-secondary">{{ formatDate(promotion.starts_at, 'noStart') }}</dd></div><div><dt class="admin-label">{{ t('common.to') }}</dt><dd class="mt-1 text-[11px] text-admin-text-secondary">{{ formatDate(promotion.ends_at, 'noEnd') }}</dd></div></dl><p class="mt-2 text-[11px]" :class="promotion.is_currently_eligible ? 'text-admin-success' : 'text-admin-neutral'">{{ promotion.is_currently_eligible ? t('promotions.eligibleNow') : t('promotions.notEligibleNow') }}</p><div class="mt-3 flex items-center justify-end gap-4"><Link :href="`/admin/promotions/${promotion.id}/edit`" class="admin-link text-xs">{{ canUpdate && promotion.status !== 'ARCHIVED' ? t('common.edit') : t('common.view') }}</Link><button v-if="canArchive && promotion.status !== 'ARCHIVED'" type="button" :disabled="lifecycle.processing" class="text-xs font-semibold text-admin-danger disabled:opacity-50" @click="archive(promotion)">{{ activeArchive === promotion.id ? t('common.archiving') : t('common.archive') }}</button></div></article></div>
            </template>
            <EmptyState v-else :title="hasSearch ? t('promotions.noMatch') : t('promotions.empty')" :description="hasSearch ? t('promotions.noMatchHelp') : t('promotions.emptyHelp')"><template v-if="hasSearch" #actions><button type="button" class="admin-link text-sm" @click="clearSearch">{{ t('promotions.clearSearch') }}</button></template><template v-else-if="canCreate" #actions><Link href="/admin/promotions/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover">{{ t('promotions.create') }}</Link></template></EmptyState>
            <Pagination :links="promotions.links" :from="promotions.from" :to="promotions.to" :total="promotions.total" />
        </section>
    </AdminLayout>
</template>
