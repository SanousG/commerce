<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import FieldError from '../../../../components/FieldError.vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../../types';
interface PriceList { id: string; name: string; code: string; currency: string; status: string; is_default: boolean; prices_count: number }
interface Variant { id: string; product: string; name: string | null; sku: string; status: string; amount: string | null }
interface PageLink { url: string | null; label: string; active: boolean }
const props = defineProps<{ priceList: PriceList; variants: { data: Variant[]; links: PageLink[] }; filters: { search: string }; currencies: string[]; statuses: string[] }>();
const page = usePage<PageProps>();
const canUpdate = computed(() => page.props.currentTenantMembership?.permissions.includes('pricing.update') ?? false);
const canEditList = computed(() => canUpdate.value && props.priceList.status !== 'ARCHIVED');
const form = useForm({ name: props.priceList.name, currency: props.priceList.currency, status: props.priceList.status });
const search = ref(props.filters.search);
const amounts = reactive(Object.fromEntries(props.variants.data.map((variant) => [variant.id, variant.amount ?? ''])) as Record<string, string>);
const updateList = () => form.put(`/admin/pricing/price-lists/${props.priceList.id}`);
const submitSearch = () => router.get(`/admin/pricing/price-lists/${props.priceList.id}/edit`, { search: search.value }, { preserveState: false, replace: true });
const setPrice = (variant: Variant) => router.put(`/admin/pricing/price-lists/${props.priceList.id}/variants/${variant.id}`, { amount: amounts[variant.id] }, { preserveScroll: true });
const removePrice = (variant: Variant) => router.delete(`/admin/pricing/price-lists/${props.priceList.id}/variants/${variant.id}`, { preserveScroll: true, onSuccess: () => { amounts[variant.id] = ''; } });
const formatted = (amount: string) => {
    const [whole, fraction = ''] = amount.split('.');
    return `${props.priceList.currency} ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(4, '0')}`;
};
</script>
<template><Head :title="priceList.name" /><AdminLayout><section><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ priceList.code }} / {{ priceList.currency }}</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ priceList.name }}</h1></div><Link href="/admin/pricing" class="font-semibold text-emerald-800">Back to pricing</Link></div>
    <form class="mt-8 grid gap-4 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-3" @submit.prevent="updateList"><div><label class="mb-2 block font-semibold">Name</label><input v-model="form.name" :disabled="!canEditList" required maxlength="120" class="w-full border bg-white px-4 py-3 disabled:bg-stone-100" /><FieldError :message="form.errors.name" /></div><div><label class="mb-2 block font-semibold">Currency</label><select v-model="form.currency" :disabled="!canEditList" class="w-full border bg-white px-4 py-3 disabled:bg-stone-100"><option v-for="currency in currencies" :key="currency">{{ currency }}</option></select><FieldError :message="form.errors.currency" /></div><div><label class="mb-2 block font-semibold">Status</label><select v-model="form.status" :disabled="!canEditList" class="w-full border bg-white px-4 py-3 disabled:bg-stone-100"><option v-for="status in statuses" :key="status">{{ status }}</option></select><FieldError :message="form.errors.status" /></div><button v-if="canEditList" class="w-fit bg-[#17211b] px-5 py-3 text-sm font-semibold text-white sm:col-span-3">Save list</button></form>
    <div class="mt-10"><div class="flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-3xl font-semibold">Variant prices</h2><p class="mt-2 text-sm text-[#637067]">Amounts use {{ priceList.currency }} with up to four decimals.</p></div><form class="flex gap-2" @submit.prevent="submitSearch"><input v-model="search" type="search" placeholder="Product or SKU" class="border bg-white px-4 py-3" /><button class="border border-[#17211b] px-4 py-3 font-semibold">Search</button></form></div><div class="mt-6 grid gap-3"><form v-for="variant in variants.data" :key="variant.id" class="flex flex-wrap items-center justify-between gap-4 border border-[#17211b]/15 bg-white p-5" @submit.prevent="setPrice(variant)"><div><strong>{{ variant.product }}</strong><p class="mt-1 font-mono text-sm">{{ variant.sku }}<template v-if="variant.name"> / {{ variant.name }}</template></p><p class="mt-1 text-sm text-[#637067]">{{ variant.amount === null ? 'Not priced' : formatted(variant.amount) }}</p></div><div v-if="canUpdate && priceList.status !== 'ARCHIVED'" class="flex gap-2"><input v-model="amounts[variant.id]" required inputmode="decimal" placeholder="0.0000" class="w-36 border px-3 py-2 font-mono" /><button class="bg-[#17211b] px-4 py-2 text-sm font-semibold text-white">Save</button><button v-if="variant.amount !== null" type="button" class="px-3 py-2 text-sm font-semibold text-red-800" @click="removePrice(variant)">Remove</button></div></form><p v-if="!variants.data.length" class="border border-dashed p-8 text-[#637067]">No variants found.</p></div><nav class="mt-6 flex gap-2"><template v-for="link in variants.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : ''" v-html="link.label" /></template></nav></div>
    </section></AdminLayout></template>
