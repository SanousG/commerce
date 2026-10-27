<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface SetupState { status: string; detail: string }
interface HubCard { key: string; setupKey?: string; href: string; permission?: string; contextual?: boolean }

const props = defineProps<{
    setup: Record<string, SetupState>;
    store: { id: string; name: string; currency: string; timezone: string; locale: string } | null;
}>();
const page = usePage<PageProps>();
const { t, te } = useI18n();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const cards: HubCard[] = [
    { key: 'company', href: '/admin/settings/general' },
    { key: 'store', href: '/admin/stores', permission: 'stores.view' },
    { key: 'localization', href: '/admin/stores', permission: 'stores.view', contextual: true },
    { key: 'inventory', href: '/admin/inventory/locations', permission: 'inventory.view' },
    { key: 'pricing', href: '/admin/pricing', permission: 'pricing.view' },
    { key: 'payments', href: '/admin/settings/payments', permission: 'payments.settings', contextual: true },
    { key: 'fiscal', href: '/admin/settings/fiscal', permission: 'fiscal_settings.view', contextual: true },
    { key: 'taxes', setupKey: 'fiscal', href: '/admin/taxes', permission: 'taxes.view' },
    { key: 'sunat', href: '/admin/settings/fiscal', permission: 'fiscal_settings.view' },
    { key: 'team', href: '/admin/settings/team', permission: 'members.view' },
];
const visibleCards = computed(() => cards.filter((card) => !card.permission || permissions.value.includes(card.permission)));
const setupFor = (card: HubCard) => props.setup[card.setupKey ?? card.key];
const setupLabel = (status: string) => {
    const key = `settings.setupStatuses.${status}`;
    return te(key) ? t(key) : status;
};
const isReady = (status: string) => status === 'Configured' || status === 'Beta configured';
const description = (card: HubCard) => card.contextual && !props.store
    ? t('settings.hub.noCurrentStore')
    : card.key === 'localization' && props.store
        ? t('settings.hub.cards.localization.current', { store: props.store.name, currency: props.store.currency, timezone: props.store.timezone, locale: props.store.locale })
        : t(`settings.hub.cards.${card.key}.description`);
</script>

<template>
    <Head :title="t('settings.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-7xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('settings.title') }]" />
            <PageHeader :title="t('settings.title')" :eyebrow="t('settings.hub.eyebrow')" :description="t('settings.hub.description')" />

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3 text-xs text-admin-info">
                <p>{{ t('settings.hub.derivedHelp') }}</p>
                <p v-if="store" class="font-mono font-medium">{{ t('settings.hub.currentStore', { store: store.name }) }}</p>
                <p v-else class="font-medium">{{ t('settings.hub.noStore') }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="card in visibleCards" :key="card.key" class="admin-card flex min-h-48 min-w-0 flex-col p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="admin-label text-admin-secondary">{{ t(`settings.hub.cards.${card.key}.section`) }}</p><h2 class="mt-1 text-base font-semibold">{{ t(`settings.hub.cards.${card.key}.title`) }}</h2></div>
                        <span v-if="setupFor(card)" class="shrink-0 rounded border px-1.5 py-0.5 font-mono text-[10px] font-medium" :class="isReady(setupFor(card).status) ? 'border-admin-success-border bg-admin-success-bg text-admin-success' : 'border-admin-warning-border bg-admin-warning-bg text-amber-700'">{{ setupLabel(setupFor(card).status) }}</span>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-admin-text-secondary">{{ description(card) }}</p>
                    <p v-if="setupFor(card)" class="mt-2 text-[11px] leading-4 text-admin-neutral">{{ t(`settings.setupDetails.${card.setupKey ?? card.key}`) }}</p>
                    <Link :href="card.href" class="mt-auto inline-flex min-h-9 items-end pt-5 text-sm font-semibold text-admin-secondary hover:underline">{{ t('settings.hub.open') }}</Link>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
