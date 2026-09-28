<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface SetupState { status: string; detail: string }
const props = defineProps<{ setup: Record<string, SetupState>; store: { id: string; name: string; currency: string; timezone: string; locale: string } | null }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const cards = computed(() => [
    { key: 'company', title: 'Company', description: 'Company identity and operational status.', href: '/admin/settings/general', show: true },
    { key: 'store', title: 'Stores & localization', description: props.store ? `${props.store.name}: ${props.store.currency}, ${props.store.timezone}, ${props.store.locale}` : 'Create or select an operational store.', href: '/admin/stores', show: permissions.value.includes('stores.view') },
    { key: 'inventory', title: 'Inventory', description: 'Locations and the default fulfillment location.', href: '/admin/inventory/locations', show: permissions.value.includes('inventory.view') },
    { key: 'payments', title: 'Payments & COD', description: 'Methods, COD deposits and store payment behavior.', href: '/admin/settings/payments', show: permissions.value.includes('payments.settings') },
    { key: 'fiscal', title: 'Fiscal', description: 'Issuer, fiscal series, tax categories and rates.', href: '/admin/settings/fiscal', show: permissions.value.includes('fiscal_settings.view') },
    { key: 'sunat', title: 'SUNAT integration', description: props.setup.sunat.detail, href: '/admin/settings/fiscal', show: permissions.value.includes('fiscal_settings.view') },
    { key: 'team', title: 'Team & access', description: 'Members, invitations, roles and effective permissions.', href: '/admin/settings/team', show: permissions.value.includes('members.view') },
    { key: 'integrations', title: 'Integrations', description: 'No additional merchant integrations are available yet.', href: null, show: true },
]);
</script>

<template>
    <Head title="Settings" />
    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Administration</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Settings.</h1>
            <p class="mt-4 max-w-2xl text-[#516057]">Operational configuration is derived from the records that power each module. Warnings do not block daily work.</p>

            <div class="mt-10 grid gap-px overflow-hidden border border-[#17211b]/15 bg-[#17211b]/15 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="card in cards.filter((item) => item.show)" :key="card.key" class="flex min-h-52 flex-col bg-[#f8f5ed] p-6">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-xl font-semibold">{{ card.title }}</h2>
                        <span v-if="setup[card.key]" class="whitespace-nowrap text-xs font-semibold" :class="setup[card.key].status.includes('Configured') ? 'text-emerald-800' : 'text-amber-700'">{{ setup[card.key].status }}</span>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-[#516057]">{{ card.description }}</p>
                    <Link v-if="card.href" :href="card.href" class="mt-auto pt-8 text-sm font-semibold text-emerald-800 hover:underline">Open settings</Link>
                    <p v-else class="mt-auto pt-8 text-xs text-[#637067]">Informational only</p>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
