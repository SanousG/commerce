<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../../components/admin/EmptyState.vue';
import PageHeader from '../../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../../components/admin/StatusBadge.vue';
import StatusNotice from '../../../../components/StatusNotice.vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../../types';

interface Location { id: string; name: string; code: string; status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED'; is_default: boolean }
defineProps<{ locations: Location[] }>();
const page = usePage<PageProps>();
const { t } = useI18n();
const canAdjust = computed(() => page.props.currentTenantMembership?.permissions.includes('inventory.adjust') ?? false);
const changingDefault = ref<string | null>(null);
const notice = computed(() => {
    if (page.props.status === 'Inventory location created.') return t('inventory.notices.locationCreated');
    if (page.props.status === 'Default inventory location changed.') return t('inventory.notices.defaultChanged');
    return undefined;
});
const makeDefault = (location: Location) => {
    if (changingDefault.value || !window.confirm(t('inventory.locations.makeDefaultConfirm', { name: location.name }))) return;
    router.post(`/admin/inventory/locations/${location.id}/make-default`, {}, { preserveScroll: true, onStart: () => { changingDefault.value = location.id; }, onFinish: () => { changingDefault.value = null; } });
};
</script>

<template>
    <Head :title="t('inventory.locations.title')" />
    <AdminLayout><section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
        <Breadcrumbs :items="[{ label: t('nav.operations') }, { label: t('inventory.title'), href: '/admin/inventory' }, { label: t('inventory.locations.title') }]" />
        <PageHeader :title="t('inventory.locations.title')" :eyebrow="t('common.allCompany')" :description="t('inventory.locations.description')"><template #actions><div class="flex flex-col gap-2 sm:flex-row"><Link href="/admin/inventory" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50">{{ t('inventory.back') }}</Link><Link v-if="canAdjust" href="/admin/inventory/locations/create" class="inline-flex min-h-9 items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover">{{ t('inventory.locations.create') }}</Link></div></template></PageHeader>
        <StatusNotice :message="notice" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />
        <aside class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3"><p class="text-sm font-semibold text-admin-text">{{ t('inventory.locations.defaultTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('inventory.locations.defaultHelp') }}</p></aside>
        <template v-if="locations.length"><div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[36%] p-3">{{ t('inventory.locations.location') }}</th><th class="w-[20%] p-3">{{ t('inventory.locations.code') }}</th><th class="w-[18%] p-3">{{ t('common.status') }}</th><th class="w-[26%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="location in locations" :key="location.id"><td class="p-3"><div class="flex items-center gap-2"><span class="truncate text-sm font-semibold text-admin-text">{{ location.name }}</span><span v-if="location.is_default" class="rounded border border-admin-info-border bg-admin-info-bg px-1.5 py-0.5 text-[10px] font-semibold text-admin-secondary">{{ t('inventory.locations.default') }}</span></div></td><td class="p-3 font-mono text-[11px] text-admin-text-secondary">{{ location.code }}</td><td class="p-3"><StatusBadge :status="location.status" /></td><td class="p-3"><div class="flex justify-end gap-3"><button v-if="canAdjust && location.status === 'ACTIVE' && !location.is_default" type="button" :disabled="changingDefault !== null" class="admin-link disabled:opacity-50" @click="makeDefault(location)">{{ changingDefault === location.id ? t('inventory.locations.makingDefault') : t('inventory.locations.makeDefault') }}</button><Link v-if="canAdjust" :href="`/admin/inventory/locations/${location.id}/edit`" class="admin-link">{{ t('common.edit') }}</Link></div></td></tr></tbody></table></div>
            <div class="grid gap-2.5 md:hidden"><article v-for="location in locations" :key="`mobile-${location.id}`" class="admin-card p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate text-sm font-semibold text-admin-text">{{ location.name }}</h2><p class="mt-1 font-mono text-[10px] text-admin-neutral">{{ location.code }}</p></div><StatusBadge :status="location.status" /></div><div v-if="location.is_default" class="mt-3 rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2 text-xs font-semibold text-admin-secondary">{{ t('inventory.locations.companyDefault') }}</div><div v-if="canAdjust" class="mt-3 flex flex-col gap-2 border-t border-admin-border pt-3 sm:flex-row"><button v-if="location.status === 'ACTIVE' && !location.is_default" type="button" :disabled="changingDefault !== null" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-3 text-xs font-semibold text-admin-text-secondary disabled:opacity-50" @click="makeDefault(location)">{{ changingDefault === location.id ? t('inventory.locations.makingDefault') : t('inventory.locations.makeDefault') }}</button><Link :href="`/admin/inventory/locations/${location.id}/edit`" class="inline-flex min-h-9 items-center justify-center rounded-md bg-admin-primary px-3 text-xs font-semibold text-white">{{ t('inventory.locations.edit') }}</Link></div></article></div></template>
        <EmptyState v-else :title="t('inventory.locations.empty')" :description="t('inventory.locations.emptyHelp')"><template v-if="canAdjust" #actions><Link href="/admin/inventory/locations/create" class="admin-link text-sm">{{ t('inventory.locations.create') }}</Link></template></EmptyState>
    </section></AdminLayout>
</template>
