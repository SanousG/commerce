<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../../types';

interface Location { id: string; name: string; code: string; status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED'; is_default: boolean }
defineProps<{ locations: Location[] }>();
const page = usePage<PageProps>();
const canAdjust = computed(() => page.props.currentTenantMembership?.permissions.includes('inventory.adjust') ?? false);
const makeDefault = (location: Location) => router.post(`/admin/inventory/locations/${location.id}/make-default`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Inventory locations" />
    <AdminLayout><section><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Stock control</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Locations.</h1></div><div class="flex flex-wrap gap-3"><Link href="/admin/inventory" class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Inventory</Link><Link v-if="canAdjust" href="/admin/inventory/locations/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Create location</Link></div></div>
        <div class="mt-10 grid gap-4 sm:grid-cols-2"><article v-for="location in locations" :key="location.id" class="border border-[#17211b]/15 bg-[#f8f5ed] p-6"><div class="flex items-start justify-between gap-4"><div><p class="font-mono text-xs tracking-[0.16em] text-[#637067] uppercase">{{ location.code }}</p><h2 class="mt-2 text-2xl font-semibold">{{ location.name }}</h2></div><span v-if="location.is_default" class="bg-emerald-800 px-2 py-1 text-xs font-semibold text-white uppercase">Default</span></div><p class="mt-4 text-sm" :class="location.status === 'ACTIVE' ? 'text-emerald-800' : 'text-[#637067]'">{{ location.status }}</p><div v-if="canAdjust" class="mt-6 flex flex-wrap gap-4"><Link :href="`/admin/inventory/locations/${location.id}/edit`" class="font-semibold text-emerald-800">Edit</Link><button v-if="location.status === 'ACTIVE' && !location.is_default" class="font-semibold text-emerald-800" @click="makeDefault(location)">Make default</button></div></article><p v-if="!locations.length" class="border border-dashed border-[#17211b]/25 p-8 text-[#637067] sm:col-span-2">No inventory locations yet.</p></div>
    </section></AdminLayout>
</template>
