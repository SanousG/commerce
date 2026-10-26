<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PageProps } from '../../types';
import ContextSwitcher from './ContextSwitcher.vue';
import { adminNavigation } from './navigation';

defineProps<{ compact?: boolean }>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const currentTenant = computed(() => page.props.currentTenant);
const currentStore = computed(() => page.props.currentStore);
const unread = computed(() => page.props.unreadNotificationsCount ?? 0);
const user = computed(() => page.props.auth.user);
const allowed = (permission?: string) => !permission || permissions.value.includes(permission);
const active = (href: string) => page.url === href || (href !== '/admin/dashboard' && page.url.startsWith(`${href}/`));
const initials = computed(() => user.value?.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'U');
const { t } = useI18n();
const navKey: Record<string, string> = {
    Overview: 'overview', Dashboard: 'dashboard', Notifications: 'notifications', Catalog: 'catalog', Products: 'products', Media: 'media', Categories: 'categories', Listings: 'listings', Merchandising: 'merchandising', Pricing: 'pricing', Promotions: 'promotions', Sales: 'sales', Orders: 'orders', Carts: 'carts', Checkouts: 'checkouts', Payments: 'payments', Operations: 'operations', Fulfillment: 'fulfillment', Returns: 'returns', Refunds: 'refunds', Exchanges: 'exchanges', Customers: 'customers', Inventory: 'inventory', Stock: 'stock', Valuation: 'valuation', Locations: 'locations', Procurement: 'procurement', Suppliers: 'suppliers', 'Purchase orders': 'purchaseOrders', Compliance: 'compliance', 'Fiscal documents': 'fiscalDocuments', Taxes: 'taxes', Workspace: 'workspace', Activity: 'activity',
};
</script>

<template>
    <div class="flex h-full min-h-0 flex-col bg-white text-admin-text">
        <div class="border-b border-admin-border px-4 py-4">
            <Link href="/admin/dashboard" class="flex items-center gap-2.5 rounded-md focus-visible:outline-offset-4">
                <span class="grid size-8 place-items-center rounded-md bg-admin-primary text-white shadow-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5 5h6v6H5V5Zm8 0h6v6h-6V5Zm-8 8h6v6H5v-6Zm8 0h6v6h-6v-6Z" /></svg>
                </span>
                <span><strong class="block text-sm leading-4">Commerce</strong><span class="text-[10px] text-admin-neutral">{{ t('shell.executiveSuite') }}</span></span>
            </Link>

            <div v-if="currentTenant" class="mt-4 grid gap-2">
                <ContextSwitcher href="/admin/tenants" :label="t('shell.tenant')" :value="currentTenant.name" icon="tenant" />
                <ContextSwitcher v-if="currentStore" href="/admin/stores" :label="t('shell.store')" :value="currentStore.name" icon="store" />
            </div>
        </div>

        <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-4" :aria-label="t('shell.adminNavigation')">
            <section v-for="section in adminNavigation" :key="section.label" v-show="section.items.some((item) => allowed(item.permission))" class="mb-5">
                <h2 class="mb-1.5 px-2 text-[9px] font-semibold tracking-[0.12em] text-slate-400 uppercase">{{ t(`nav.${navKey[section.label]}`) }}</h2>
                <div class="grid gap-0.5">
                    <Link
                        v-for="item in section.items.filter((entry) => allowed(entry.permission))"
                        :key="item.href"
                        :href="item.href"
                        class="group flex min-h-8 items-center gap-2.5 rounded-md px-2 text-xs font-medium transition"
                        :class="active(item.href) ? 'bg-admin-primary text-white' : 'text-admin-text-secondary hover:bg-slate-100 hover:text-admin-primary'"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.65" :d="item.icon" /></svg>
                        <span class="truncate">{{ t(`nav.${navKey[item.label]}`) }}</span>
                        <span v-if="item.href === '/admin/notifications' && unread" class="ml-auto min-w-4 rounded-full px-1 text-center text-[9px] leading-4" :class="active(item.href) ? 'bg-white text-admin-primary' : 'bg-admin-primary text-white'">{{ unread > 99 ? '99+' : unread }}</span>
                    </Link>
                </div>
            </section>

            <section v-if="permissions.includes('settings.view')" class="mb-2">
                <h2 class="mb-1.5 px-2 text-[9px] font-semibold tracking-[0.12em] text-slate-400 uppercase">{{ t('shell.settings') }}</h2>
                <div class="grid gap-0.5">
                    <Link href="/admin/settings" class="flex min-h-8 items-center gap-2.5 rounded-md px-2 text-xs font-medium transition" :class="active('/admin/settings') && !page.url.startsWith('/admin/settings/') ? 'bg-admin-primary text-white' : 'text-admin-text-secondary hover:bg-slate-100'">{{ t('shell.general') }}</Link>
                    <Link v-if="permissions.includes('stores.view')" href="/admin/stores" class="flex min-h-8 items-center rounded-md px-2 text-xs font-medium text-admin-text-secondary hover:bg-slate-100">{{ t('shell.stores') }}</Link>
                    <Link v-if="permissions.includes('members.view')" href="/admin/settings/team" class="flex min-h-8 items-center rounded-md px-2 text-xs font-medium text-admin-text-secondary hover:bg-slate-100">{{ t('shell.teamAccess') }}</Link>
                    <Link href="/admin/settings/notifications" class="flex min-h-8 items-center rounded-md px-2 text-xs font-medium text-admin-text-secondary hover:bg-slate-100">{{ t('shell.notificationPreferences') }}</Link>
                </div>
            </section>
        </nav>

        <div class="border-t border-admin-border p-3">
            <div class="flex items-center gap-2 rounded-md border border-admin-border p-2">
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-admin-primary text-[10px] font-semibold text-white">{{ initials }}</span>
                <span class="min-w-0 flex-1"><strong class="block truncate text-xs">{{ user?.name }}</strong><span class="block truncate text-[10px] text-admin-neutral">{{ user?.email }}</span></span>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <Link href="/admin/tenants" class="rounded-md border border-admin-border px-2 py-2 text-center text-[11px] font-medium hover:bg-slate-50">{{ t('shell.companies') }}</Link>
                <Link href="/logout" method="post" as="button" class="rounded-md border border-admin-danger-border px-2 py-2 text-center text-[11px] font-medium text-admin-danger hover:bg-admin-danger-bg">{{ t('shell.logout') }}</Link>
            </div>
        </div>
    </div>
</template>
