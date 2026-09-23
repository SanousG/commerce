<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PromotionRow {
    id: string;
    name: string;
    internal_code: string | null;
    status: 'DRAFT' | 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    discount_type: 'PERCENTAGE' | 'FIXED_AMOUNT';
    discount_value: string;
    currency: string | null;
    starts_at: string | null;
    ends_at: string | null;
    priority: number;
    scope: 'ALL' | 'SELECTED';
    is_currently_eligible: boolean;
    products_count: number;
    categories_count: number;
    variants_count: number;
}
interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{ promotions: { data: PromotionRow[]; links: PageLink[] }; filters: { search: string } }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('promotions.create'));
const canUpdate = computed(() => permissions.value.includes('promotions.update'));
const canArchive = computed(() => permissions.value.includes('promotions.archive'));
const search = ref(props.filters.search);
const submitSearch = () => router.get('/admin/promotions', { search: search.value }, { preserveState: true, replace: true });
const archive = (promotion: PromotionRow) => {
    if (window.confirm(`Archive ${promotion.name}? Its targets and configuration will be preserved.`)) {
        router.patch(`/admin/promotions/${promotion.id}/archive`);
    }
};
const discount = (promotion: PromotionRow) => promotion.discount_type === 'PERCENTAGE'
    ? `${promotion.discount_value}%`
    : `${promotion.currency} ${promotion.discount_value}`;
const targets = (promotion: PromotionRow) => promotion.scope === 'ALL'
    ? 'All catalog'
    : `${promotion.products_count} products / ${promotion.categories_count} categories / ${promotion.variants_count} variants`;
</script>

<template>
    <Head title="Promotions" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Commercial adjustments</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Promotions.</h1></div>
                <Link v-if="canCreate" href="/admin/promotions/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900">Create promotion</Link>
            </div>
            <form class="mt-8 flex max-w-xl gap-3" @submit.prevent="submitSearch"><input v-model="search" type="search" placeholder="Search name or internal code" class="min-w-0 flex-1 border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700" /><button class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Search</button></form>
            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]"><table class="w-full min-w-[980px] text-left text-sm"><thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Promotion</th><th class="p-4">Discount</th><th class="p-4">State</th><th class="p-4">Window (UTC)</th><th class="p-4">Priority</th><th class="p-4">Scope</th><th class="p-4">Actions</th></tr></thead><tbody>
                <tr v-for="promotion in promotions.data" :key="promotion.id" class="border-b border-[#17211b]/10 last:border-0"><td class="p-4"><strong>{{ promotion.name }}</strong><div class="mt-1 font-mono text-xs text-[#637067]">{{ promotion.internal_code ?? 'No internal code' }}</div></td><td class="p-4 font-mono">{{ discount(promotion) }}</td><td class="p-4"><span>{{ promotion.status }}</span><div class="mt-1 text-xs" :class="promotion.is_currently_eligible ? 'text-emerald-800' : 'text-[#637067]'">{{ promotion.is_currently_eligible ? 'Eligible now' : 'Not eligible now' }}</div></td><td class="p-4 text-xs"><div>{{ promotion.starts_at ?? 'No start' }}</div><div class="mt-1">{{ promotion.ends_at ?? 'No end' }}</div></td><td class="p-4 font-mono">{{ promotion.priority }}</td><td class="p-4">{{ targets(promotion) }}</td><td class="p-4"><div class="flex gap-3"><Link v-if="canUpdate || permissions.includes('promotions.view')" :href="`/admin/promotions/${promotion.id}/edit`" class="font-semibold text-emerald-800">{{ canUpdate ? 'Edit' : 'View' }}</Link><button v-if="canArchive && promotion.status !== 'ARCHIVED'" class="font-semibold text-red-800" @click="archive(promotion)">Archive</button></div></td></tr>
                <tr v-if="!promotions.data.length"><td colspan="7" class="p-8 text-center text-[#637067]">No promotions found.</td></tr>
            </tbody></table></div>
            <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in promotions.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
        </section>
    </AdminLayout>
</template>
