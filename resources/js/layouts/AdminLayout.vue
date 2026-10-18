<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps } from '../types';

interface NavItem {
    label: string;
    href: string;
    permission?: string;
}

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const allowed = (item: NavItem) => !item.permission || permissions.value.includes(item.permission);
const active = (href: string) => page.url === href || (href !== '/admin/dashboard' && page.url.startsWith(`${href}/`));
const currentTenant = computed(() => page.props.currentTenant);
const currentStore = computed(() => page.props.currentStore);
const unreadNotificationsCount = computed(() => page.props.unreadNotificationsCount ?? 0);

const sections: Array<{ label: string; items: NavItem[] }> = [
    { label: 'Catalog', items: [
        { label: 'Products', href: '/admin/products', permission: 'products.view' },
        { label: 'Media', href: '/admin/media', permission: 'media.view' },
        { label: 'Categories', href: '/admin/categories', permission: 'categories.view' },
        { label: 'Listings', href: '/admin/listings', permission: 'listings.view' },
        { label: 'Promotions', href: '/admin/promotions', permission: 'promotions.view' },
        { label: 'Pricing', href: '/admin/pricing', permission: 'pricing.view' },
    ] },
    { label: 'Sales', items: [
        { label: 'Carts', href: '/admin/carts', permission: 'carts.view' },
        { label: 'Checkouts', href: '/admin/checkouts', permission: 'checkouts.view' },
        { label: 'Orders', href: '/admin/orders', permission: 'orders.view' },
        { label: 'Payments', href: '/admin/payments', permission: 'payments.view' },
    ] },
    { label: 'Operations', items: [
        { label: 'Fulfillment', href: '/admin/fulfillments', permission: 'fulfillments.view' },
        { label: 'Returns', href: '/admin/returns', permission: 'returns.view' },
        { label: 'Refunds', href: '/admin/refunds', permission: 'refunds.view' },
        { label: 'Exchanges', href: '/admin/exchanges', permission: 'exchanges.view' },
        { label: 'Customers', href: '/admin/customers', permission: 'customers.view' },
    ] },
    { label: 'Inventory', items: [
        { label: 'Stock', href: '/admin/inventory', permission: 'inventory.view' },
        { label: 'Valuation', href: '/admin/inventory/valuation', permission: 'inventory.cost.view' },
        { label: 'Locations', href: '/admin/inventory/locations', permission: 'inventory.view' },
    ] },
    { label: 'Procurement', items: [
        { label: 'Suppliers', href: '/admin/suppliers', permission: 'suppliers.view' },
        { label: 'Purchase orders', href: '/admin/purchase-orders', permission: 'procurement.view' },
    ] },
    { label: 'Fiscal', items: [
        { label: 'Documents', href: '/admin/fiscal-documents', permission: 'fiscal_documents.view' },
        { label: 'Taxes', href: '/admin/taxes', permission: 'taxes.view' },
    ] },
    { label: 'Workspace', items: [
        { label: 'Activity', href: '/admin/activity', permission: 'audit.view' },
    ] },
];
</script>

<template>
    <div class="min-h-screen bg-[#f1eee6] text-[#17211b] lg:grid lg:grid-cols-[17rem_1fr]">
        <aside class="bg-[#17211b] text-white lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
            <div class="border-b border-white/10 px-5 py-5">
                <div class="flex items-center justify-between gap-3">
                    <Link href="/admin/dashboard" class="text-xs font-semibold tracking-[0.24em] text-[#d8f36a] uppercase">Commerce / Admin</Link>
                    <Link href="/admin/notifications" class="relative text-white/70 transition hover:text-white" :aria-label="unreadNotificationsCount ? `Notificaciones, ${unreadNotificationsCount} sin leer` : 'Notificaciones'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17H9m9-2V11a6 6 0 1 0-12 0v4l-2 2h16l-2-2Zm-8 5h4" /></svg>
                        <span v-if="unreadNotificationsCount" class="absolute -right-2.5 -top-2.5 min-w-4 bg-[#d8f36a] px-1 text-center text-[9px] font-bold leading-4 text-[#17211b]">{{ unreadNotificationsCount > 99 ? '99+' : unreadNotificationsCount }}</span>
                    </Link>
                </div>
                <p class="mt-2 truncate text-sm text-white/60">{{ currentTenant?.name ?? 'Choose your workspace' }}</p>
                <p v-if="currentStore" class="truncate text-xs text-white/45">{{ currentStore.name }}</p>
            </div>

            <nav class="flex gap-6 overflow-x-auto px-5 py-5 lg:block lg:space-y-6" aria-label="Admin navigation">
                <div class="shrink-0">
                    <p class="mb-2 text-[10px] font-semibold tracking-[0.2em] text-white/35 uppercase">Home</p>
                    <Link href="/admin/dashboard" :class="active('/admin/dashboard') ? 'text-[#d8f36a]' : 'text-white/70 hover:text-white'" class="block py-1.5 text-sm font-semibold">Dashboard</Link>
                </div>
                <div v-for="section in sections" :key="section.label" class="shrink-0" v-show="section.items.some(allowed)">
                    <p class="mb-2 text-[10px] font-semibold tracking-[0.2em] text-white/35 uppercase">{{ section.label }}</p>
                    <Link v-for="item in section.items.filter(allowed)" :key="item.href" :href="item.href" :class="active(item.href) ? 'text-[#d8f36a]' : 'text-white/70 hover:text-white'" class="block py-1.5 text-sm font-semibold transition">{{ item.label }}</Link>
                </div>
                <div v-if="permissions.includes('settings.view')" class="shrink-0">
                    <p class="mb-2 text-[10px] font-semibold tracking-[0.2em] text-white/35 uppercase">Settings</p>
                    <Link href="/admin/settings" :class="active('/admin/settings') ? 'text-[#d8f36a]' : 'text-white/70 hover:text-white'" class="block py-1.5 text-sm font-semibold">General</Link>
                    <Link v-if="permissions.includes('stores.view')" href="/admin/stores" :class="active('/admin/stores') ? 'text-[#d8f36a]' : 'text-white/70 hover:text-white'" class="block py-1.5 text-sm font-semibold">Stores</Link>
                    <Link v-if="permissions.includes('stores.view') && currentStore" :href="`/admin/stores/${currentStore.id}/settings`" :class="page.url.includes('/stores/') && page.url.endsWith('/settings') ? 'text-[#d8f36a]' : 'text-white/70 hover:text-white'" class="block py-1.5 text-sm font-semibold">Current store settings</Link>
                    <Link v-if="permissions.includes('members.view')" href="/admin/settings/team" :class="active('/admin/settings/team') ? 'text-[#d8f36a]' : 'text-white/70 hover:text-white'" class="block py-1.5 text-sm font-semibold">Team &amp; Access</Link>
                </div>
            </nav>

            <div class="flex flex-wrap gap-2 border-t border-white/10 px-5 py-5 text-xs">
                <Link href="/admin/tenants" class="text-white/60 hover:text-white">Companies</Link>
                <Link href="/admin/stores" class="text-white/60 hover:text-white">Stores</Link>
                <Link href="/logout" method="post" as="button" class="ml-auto text-white/60 hover:text-white">Log out</Link>
            </div>
        </aside>

        <main class="min-w-0 px-5 py-10 sm:px-8 sm:py-14 xl:px-12">
            <slot :user="user" :current-tenant="currentTenant" :current-store="currentStore" />
        </main>
    </div>
</template>
