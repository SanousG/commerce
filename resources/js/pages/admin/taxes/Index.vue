<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface TaxCategory { id: string; code: string; name: string; status: string }
interface TaxRate { id: string; tax_category_id: string; name: string; rate: string; valid_from: string | null; valid_until: string | null; status: string }

const props = defineProps<{ categories: TaxCategory[]; rates: TaxRate[]; categoryCodes: string[]; statuses: string[] }>();
const page = usePage<PageProps>();
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('taxes.manage') ?? false);
const status = computed(() => page.props.status);

const categoryForm = useForm({ code: props.categoryCodes[0] ?? '', name: '', status: props.statuses[0] ?? '' });
const categoryForms = props.categories.map((category) => ({ id: category.id, form: useForm({ code: category.code, name: category.name, status: category.status }) }));
const rateForm = useForm({ tax_category_id: props.categories[0]?.id ?? '', name: '', rate: '', valid_from: '', valid_until: '', status: props.statuses[0] ?? '' });
const rateForms = props.rates.map((rate) => ({ id: rate.id, form: useForm({ tax_category_id: rate.tax_category_id, name: rate.name, rate: rate.rate, valid_from: rate.valid_from ?? '', valid_until: rate.valid_until ?? '', status: rate.status }) }));

const createCategory = () => categoryForm.post('/admin/tax-categories', { preserveScroll: true, onSuccess: () => categoryForm.reset('name') });
const updateCategory = (entry: (typeof categoryForms)[number]) => entry.form.patch(`/admin/tax-categories/${entry.id}`, { preserveScroll: true });
const createRate = () => rateForm.post('/admin/tax-rates', { preserveScroll: true, onSuccess: () => rateForm.reset('name', 'rate', 'valid_from', 'valid_until') });
const updateRate = (entry: (typeof rateForms)[number]) => entry.form.patch(`/admin/tax-rates/${entry.id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Taxes" />
    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Tax configuration</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Taxes.</h1>
            <p class="mt-3 max-w-3xl text-[#637067]">Configure tenant tax categories and dated percentage rates. Rates remain decimal strings from entry through storage.</p>
            <p v-if="status" class="mt-6 border border-emerald-700 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ status }}</p>

            <div class="mt-10 flex items-baseline justify-between gap-4"><h2 class="text-3xl font-semibold">Categories</h2><span class="font-mono text-xs text-[#637067]">{{ categories.length }} configured</span></div>
            <div class="mt-5 grid gap-4">
                <form v-for="entry in categoryForms" :key="entry.id" class="grid gap-4 border border-[#17211b]/15 bg-white p-5 md:grid-cols-[9rem_minmax(0,1fr)_10rem_auto] md:items-end" @submit.prevent="updateCategory(entry)">
                    <label class="text-sm font-semibold">Code<select v-model="entry.form.code" :disabled="!canManage" required class="mt-2 w-full border bg-white px-3 py-2 font-mono font-normal disabled:bg-stone-100"><option v-for="code in categoryCodes" :key="code" :value="code">{{ code }}</option></select></label>
                    <label class="text-sm font-semibold">Name<input v-model="entry.form.name" :disabled="!canManage" required maxlength="120" class="mt-2 w-full border px-3 py-2 font-normal disabled:bg-stone-100" /></label>
                    <label class="text-sm font-semibold">Status<select v-model="entry.form.status" :disabled="!canManage" required class="mt-2 w-full border bg-white px-3 py-2 font-normal disabled:bg-stone-100"><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                    <button v-if="canManage" :disabled="entry.form.processing" class="bg-[#17211b] px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Save</button>
                    <FieldError class="md:col-span-4" :message="entry.form.errors.code ?? entry.form.errors.name ?? entry.form.errors.status" />
                </form>
                <p v-if="!categories.length" class="border border-dashed border-[#17211b]/25 p-8 text-[#637067]">No tax categories configured.</p>
            </div>
            <form v-if="canManage" class="mt-5 grid gap-4 border border-dashed border-[#17211b]/30 bg-[#f8f5ed] p-5 md:grid-cols-[9rem_minmax(0,1fr)_10rem_auto] md:items-end" @submit.prevent="createCategory">
                <label class="text-sm font-semibold">Code<select v-model="categoryForm.code" required class="mt-2 w-full border bg-white px-3 py-2 font-mono font-normal"><option v-for="code in categoryCodes" :key="code" :value="code">{{ code }}</option></select></label>
                <label class="text-sm font-semibold">New category<input v-model="categoryForm.name" required maxlength="120" class="mt-2 w-full border bg-white px-3 py-2 font-normal" /></label>
                <label class="text-sm font-semibold">Status<select v-model="categoryForm.status" required class="mt-2 w-full border bg-white px-3 py-2 font-normal"><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                <button :disabled="categoryForm.processing" class="bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Add category</button>
                <FieldError class="md:col-span-4" :message="categoryForm.errors.code ?? categoryForm.errors.name ?? categoryForm.errors.status" />
            </form>

            <div class="mt-14 flex items-baseline justify-between gap-4"><h2 class="text-3xl font-semibold">Rates</h2><span class="font-mono text-xs text-[#637067]">Percent</span></div>
            <div class="mt-5 grid gap-4">
                <form v-for="entry in rateForms" :key="entry.id" class="grid gap-4 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-2 lg:grid-cols-7 lg:items-end" @submit.prevent="updateRate(entry)">
                    <label class="text-sm font-semibold lg:col-span-2">Category<select v-model="entry.form.tax_category_id" :disabled="!canManage" required class="mt-2 w-full border bg-white px-3 py-2 font-normal disabled:bg-stone-100"><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }} / {{ category.code }}</option></select></label>
                    <label class="text-sm font-semibold lg:col-span-2">Name<input v-model="entry.form.name" :disabled="!canManage" required maxlength="120" class="mt-2 w-full border px-3 py-2 font-normal disabled:bg-stone-100" /></label>
                    <label class="text-sm font-semibold">Rate %<input v-model="entry.form.rate" :disabled="!canManage" required inputmode="decimal" placeholder="18.000000" class="mt-2 w-full border px-3 py-2 font-mono font-normal disabled:bg-stone-100" /></label>
                    <label class="text-sm font-semibold">Valid from<input v-model="entry.form.valid_from" :disabled="!canManage" type="date" class="mt-2 w-full border px-3 py-2 font-normal disabled:bg-stone-100" /></label>
                    <label class="text-sm font-semibold">Valid until<input v-model="entry.form.valid_until" :disabled="!canManage" type="date" class="mt-2 w-full border px-3 py-2 font-normal disabled:bg-stone-100" /></label>
                    <label class="text-sm font-semibold">Status<select v-model="entry.form.status" :disabled="!canManage" required class="mt-2 w-full border bg-white px-3 py-2 font-normal disabled:bg-stone-100"><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                    <div v-if="canManage" class="lg:col-span-6"><button :disabled="entry.form.processing" class="bg-[#17211b] px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Save rate</button></div>
                    <FieldError class="lg:col-span-7" :message="entry.form.errors.tax_category_id ?? entry.form.errors.name ?? entry.form.errors.rate ?? entry.form.errors.valid_from ?? entry.form.errors.valid_until ?? entry.form.errors.status" />
                </form>
                <p v-if="!rates.length" class="border border-dashed border-[#17211b]/25 p-8 text-[#637067]">No tax rates configured.</p>
            </div>
            <form v-if="canManage" class="mt-5 grid gap-4 border border-dashed border-[#17211b]/30 bg-[#f8f5ed] p-5 sm:grid-cols-2 lg:grid-cols-7 lg:items-end" @submit.prevent="createRate">
                <h3 class="text-xl font-semibold sm:col-span-2 lg:col-span-7">Add rate</h3>
                <label class="text-sm font-semibold lg:col-span-2">Category<select v-model="rateForm.tax_category_id" required class="mt-2 w-full border bg-white px-3 py-2 font-normal"><option value="" disabled>Select category</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }} / {{ category.code }}</option></select></label>
                <label class="text-sm font-semibold lg:col-span-2">Name<input v-model="rateForm.name" required maxlength="120" class="mt-2 w-full border bg-white px-3 py-2 font-normal" /></label>
                <label class="text-sm font-semibold">Rate %<input v-model="rateForm.rate" required inputmode="decimal" placeholder="18.000000" class="mt-2 w-full border bg-white px-3 py-2 font-mono font-normal" /></label>
                <label class="text-sm font-semibold">Valid from<input v-model="rateForm.valid_from" type="date" class="mt-2 w-full border bg-white px-3 py-2 font-normal" /></label>
                <label class="text-sm font-semibold">Valid until<input v-model="rateForm.valid_until" type="date" class="mt-2 w-full border bg-white px-3 py-2 font-normal" /></label>
                <label class="text-sm font-semibold">Status<select v-model="rateForm.status" required class="mt-2 w-full border bg-white px-3 py-2 font-normal"><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                <div class="lg:col-span-6"><button :disabled="rateForm.processing || !categories.length" class="bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Add rate</button></div>
                <FieldError class="lg:col-span-7" :message="rateForm.errors.tax_category_id ?? rateForm.errors.name ?? rateForm.errors.rate ?? rateForm.errors.valid_from ?? rateForm.errors.valid_until ?? rateForm.errors.status" />
            </form>
        </section>
    </AdminLayout>
</template>
