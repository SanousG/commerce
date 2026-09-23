<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps } from '../types';

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const currentTenant = computed(() => page.props.currentTenant);
const currentStore = computed(() => page.props.currentStore);
const canCreateStore = computed(() => page.props.currentTenantMembership?.permissions.includes('stores.create') ?? false);
const canViewProducts = computed(() => page.props.currentTenantMembership?.permissions.includes('products.view') ?? false);
const canViewCategories = computed(() => page.props.currentTenantMembership?.permissions.includes('categories.view') ?? false);
const canViewPricing = computed(() => page.props.currentTenantMembership?.permissions.includes('pricing.view') ?? false);
const canViewPromotions = computed(() => page.props.currentTenantMembership?.permissions.includes('promotions.view') ?? false);
const canViewInventory = computed(() => page.props.currentTenantMembership?.permissions.includes('inventory.view') ?? false);
const canViewListings = computed(() => page.props.currentTenantMembership?.permissions.includes('listings.view') ?? false);
const canViewCustomers = computed(() => page.props.currentTenantMembership?.permissions.includes('customers.view') ?? false);
const canViewCarts = computed(() => page.props.currentTenantMembership?.permissions.includes('carts.view') ?? false);
const canViewCheckouts = computed(() => page.props.currentTenantMembership?.permissions.includes('checkouts.view') ?? false);
const canViewOrders = computed(() => page.props.currentTenantMembership?.permissions.includes('orders.view') ?? false);
</script>

<template>
    <div class="min-h-screen bg-[#f1eee6] text-[#17211b]">
        <header class="border-b border-[#17211b]/15 bg-[#17211b] text-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-5 px-5 py-5 sm:px-8">
                <div>
                    <p class="text-xs font-semibold tracking-[0.24em] text-[#d8f36a] uppercase">
                        Commerce / Admin
                    </p>
                    <p class="mt-1 text-sm text-white/60">
                        {{ currentTenant?.name ?? 'Choose your workspace' }}
                        <template v-if="currentStore"> / {{ currentStore.name }}</template>
                    </p>
                </div>

                <nav class="flex flex-wrap items-center gap-3">
                    <Link href="/admin/tenants" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Change company
                    </Link>
                    <Link href="/admin/tenants/create" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        New company
                    </Link>
                    <Link href="/admin/stores" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Change store
                    </Link>
                    <Link v-if="canCreateStore" href="/admin/stores/create" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        New store
                    </Link>
                    <Link v-if="canViewProducts" href="/admin/products" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Products
                    </Link>
                    <Link v-if="canViewCategories" href="/admin/categories" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Categories
                    </Link>
                    <Link v-if="canViewPricing" href="/admin/pricing" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Pricing
                    </Link>
                    <Link v-if="canViewPromotions" href="/admin/promotions" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Promotions
                    </Link>
                    <Link v-if="canViewInventory" href="/admin/inventory" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Inventory
                    </Link>
                    <Link v-if="canViewListings" href="/admin/listings" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Listings
                    </Link>
                    <Link v-if="canViewCustomers" href="/admin/customers" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Customers
                    </Link>
                    <Link v-if="canViewCarts" href="/admin/carts" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Carts
                    </Link>
                    <Link v-if="canViewCheckouts" href="/admin/checkouts" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Checkouts
                    </Link>
                    <Link v-if="canViewOrders" href="/admin/orders" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Orders
                    </Link>
                    <Link href="/admin/access" class="px-3 py-2 text-sm font-semibold text-white/75 transition hover:text-[#d8f36a]">
                        Access
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="border border-white/25 px-4 py-2 text-sm font-semibold transition hover:border-[#d8f36a] hover:text-[#d8f36a]"
                    >
                        Log out
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-20">
            <slot :user="user" :current-tenant="currentTenant" :current-store="currentStore" />
        </main>
    </div>
</template>
