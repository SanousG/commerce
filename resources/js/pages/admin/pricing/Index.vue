<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PriceList {
    id: string;
    name: string;
    code: string;
    currency: string;
    status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    is_default: boolean;
    prices_count: number;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    priceLists: {
        data: PriceList[];
        links: PageLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    };
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('pricing.create'));
const canUpdate = computed(() => permissions.value.includes('pricing.update'));
const canArchive = computed(() => permissions.value.includes('pricing.archive'));
const action = useForm({});
const activeAction = ref<{ id: string; type: 'default' | 'archive' } | null>(null);
const { t } = useI18n();

const localizedStatus = computed(() => {
    const keys: Record<string, string> = {
        'Default price list changed.': 'pricing.notices.defaultChanged',
        'Price list archived.': 'pricing.notices.archived',
    };
    const message = page.props.status;
    return message ? t(keys[message] ?? 'pricing.notices.completed') : undefined;
});
const actionError = computed(() => {
    const message = (action.errors as Record<string, string | undefined>).price_list;
    if (!message) return undefined;

    const keys: Record<string, string> = {
        'Only an active price list can become default.': 'pricing.errors.onlyActiveDefault',
        'Choose another default before archiving this list.': 'pricing.errors.chooseAnotherDefault',
    };
    return t(keys[message] ?? 'pricing.errors.actionFailed');
});

const makeDefault = (list: PriceList) => {
    if (action.processing || !window.confirm(t('pricing.makeDefaultConfirm', { name: list.name }))) return;
    activeAction.value = { id: list.id, type: 'default' };
    action.clearErrors();
    action.post(`/admin/pricing/price-lists/${list.id}/make-default`, {
        preserveScroll: true,
        onFinish: () => { activeAction.value = null; },
    });
};
const archive = (list: PriceList) => {
    if (action.processing || !window.confirm(t('pricing.archiveConfirm', { name: list.name }))) return;
    activeAction.value = { id: list.id, type: 'archive' };
    action.clearErrors();
    action.patch(`/admin/pricing/price-lists/${list.id}/archive`, {
        preserveScroll: true,
        onFinish: () => { activeAction.value = null; },
    });
};
const isRunning = (list: PriceList, type: 'default' | 'archive') => activeAction.value?.id === list.id && activeAction.value.type === type;
</script>

<template>
    <Head :title="t('pricing.title')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.merchandising') }, { label: t('pricing.title') }]" />

            <PageHeader :title="t('pricing.title')" :eyebrow="t('common.allCompany')" :description="t('pricing.description')">
                <template #actions>
                    <Link v-if="canCreate" href="/admin/pricing/price-lists/create" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>
                        {{ t('pricing.create') }}
                    </Link>
                </template>
            </PageHeader>

            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />
            <div v-if="actionError" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3"><FieldError :message="actionError" /></div>

            <template v-if="priceLists.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs">
                        <thead><tr><th class="w-[32%] p-3">{{ t('pricing.priceList') }}</th><th class="w-[12%] p-3">{{ t('pricing.currency') }}</th><th class="w-[14%] p-3">{{ t('common.status') }}</th><th class="w-[14%] p-3 text-right">{{ t('pricing.assignedPrices') }}</th><th class="w-[28%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-admin-border">
                            <tr v-for="list in priceLists.data" :key="list.id">
                                <td class="p-3"><div class="flex min-w-0 items-center gap-2"><p class="truncate text-sm font-semibold text-admin-text" :title="list.name">{{ list.name }}</p><span v-if="list.is_default" class="shrink-0 rounded border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-admin-secondary">{{ t('pricing.default') }}</span></div><p class="mt-0.5 font-mono text-[10px] text-admin-neutral">{{ list.code }}</p></td>
                                <td class="p-3 font-mono font-medium text-admin-text-secondary">{{ list.currency }}</td>
                                <td class="p-3"><StatusBadge :status="list.status" /></td>
                                <td class="p-3 text-right font-mono font-semibold tabular-nums">{{ list.prices_count }}</td>
                                <td class="p-3"><div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2"><Link :href="`/admin/pricing/price-lists/${list.id}/edit`" class="admin-link">{{ canUpdate && list.status !== 'ARCHIVED' ? t('common.edit') : t('common.view') }}</Link><button v-if="canUpdate && !list.is_default && list.status === 'ACTIVE'" type="button" :disabled="action.processing" class="font-semibold text-admin-secondary hover:underline disabled:cursor-wait disabled:opacity-50" @click="makeDefault(list)">{{ isRunning(list, 'default') ? t('pricing.makingDefault') : t('pricing.makeDefault') }}</button><button v-if="canArchive && !list.is_default && list.status !== 'ARCHIVED'" type="button" :disabled="action.processing" class="font-semibold text-admin-danger hover:underline disabled:cursor-wait disabled:opacity-50" @click="archive(list)">{{ isRunning(list, 'archive') ? t('common.archiving') : t('common.archive') }}</button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-2.5 md:hidden">
                    <article v-for="list in priceLists.data" :key="`mobile-${list.id}`" class="admin-card p-3.5">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="text-sm font-semibold text-admin-text">{{ list.name }}</h2><span v-if="list.is_default" class="rounded border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-admin-secondary">{{ t('pricing.default') }}</span></div><p class="mt-1 font-mono text-[10px] text-admin-neutral">{{ list.code }}</p></div><StatusBadge :status="list.status" /></div>
                        <dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('pricing.currency') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ list.currency }}</dd></div><div><dt class="admin-label">{{ t('pricing.assignedPrices') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ list.prices_count }}</dd></div></dl>
                        <div class="mt-3 flex flex-wrap items-center justify-end gap-x-4 gap-y-2"><Link :href="`/admin/pricing/price-lists/${list.id}/edit`" class="admin-link text-xs">{{ canUpdate && list.status !== 'ARCHIVED' ? t('common.edit') : t('common.view') }}</Link><button v-if="canUpdate && !list.is_default && list.status === 'ACTIVE'" type="button" :disabled="action.processing" class="text-xs font-semibold text-admin-secondary disabled:cursor-wait disabled:opacity-50" @click="makeDefault(list)">{{ isRunning(list, 'default') ? t('pricing.makingDefault') : t('pricing.makeDefault') }}</button><button v-if="canArchive && !list.is_default && list.status !== 'ARCHIVED'" type="button" :disabled="action.processing" class="text-xs font-semibold text-admin-danger disabled:cursor-wait disabled:opacity-50" @click="archive(list)">{{ isRunning(list, 'archive') ? t('common.archiving') : t('common.archive') }}</button></div>
                    </article>
                </div>
            </template>

            <EmptyState v-else :title="t('pricing.empty')" :description="t('pricing.emptyHelp')">
                <template v-if="canCreate" #actions><Link href="/admin/pricing/price-lists/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover">{{ t('pricing.create') }}</Link></template>
            </EmptyState>

            <Pagination :links="priceLists.links" :from="priceLists.from" :to="priceLists.to" :total="priceLists.total" />
        </section>
    </AdminLayout>
</template>
