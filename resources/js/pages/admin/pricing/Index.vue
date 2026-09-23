<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PriceList { id: string; name: string; code: string; currency: string; status: string; is_default: boolean; prices_count: number }
interface PageLink { url: string | null; label: string; active: boolean }
defineProps<{ priceLists: { data: PriceList[]; links: PageLink[] } }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('pricing.create'));
const canUpdate = computed(() => permissions.value.includes('pricing.update'));
const canArchive = computed(() => permissions.value.includes('pricing.archive'));
const makeDefault = (list: PriceList) => router.post(`/admin/pricing/price-lists/${list.id}/make-default`);
const archive = (list: PriceList) => { if (window.confirm(`Archive ${list.name}?`)) router.patch(`/admin/pricing/price-lists/${list.id}/archive`); };
</script>

<template>
    <Head title="Pricing" />
    <AdminLayout><section><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Tenant pricing</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Price lists.</h1></div><Link v-if="canCreate" href="/admin/pricing/price-lists/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Create price list</Link></div>
        <div class="mt-8 grid gap-4"><article v-for="list in priceLists.data" :key="list.id" class="border border-[#17211b]/15 bg-[#f8f5ed] p-6"><div class="flex flex-wrap items-start justify-between gap-5"><div><div class="flex gap-2"><span v-if="list.is_default" class="bg-emerald-800 px-2 py-1 text-xs font-semibold text-white uppercase">Default</span><span class="border px-2 py-1 font-mono text-xs">{{ list.code }}</span></div><h2 class="mt-3 text-2xl font-semibold">{{ list.name }}</h2><p class="mt-2 text-sm text-[#637067]">{{ list.currency }} / {{ list.status }} / {{ list.prices_count }} prices</p></div><div class="flex flex-wrap gap-3"><Link :href="`/admin/pricing/price-lists/${list.id}/edit`" class="font-semibold text-emerald-800">Open</Link><button v-if="canUpdate && !list.is_default && list.status === 'ACTIVE'" class="font-semibold" @click="makeDefault(list)">Make default</button><button v-if="canArchive && !list.is_default && list.status !== 'ARCHIVED'" class="font-semibold text-red-800" @click="archive(list)">Archive</button></div></div></article><p v-if="!priceLists.data.length" class="border border-dashed p-8 text-[#637067]">No price lists.</p></div>
        <nav class="mt-6 flex gap-2"><template v-for="link in priceLists.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : ''" v-html="link.label" /></template></nav>
    </section></AdminLayout>
</template>
