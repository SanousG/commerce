<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Choice { id: string; name: string }
interface TaxCategoryChoice extends Choice { code: string }
interface OptionValue { id: string; value: string }
interface ProductOption { id: string; name: string; values: OptionValue[] }
interface Variant { id: string; name: string | null; sku: string; barcode: string | null; status: string; is_default: boolean; option_value_ids: string[]; display_options: string[]; default_price: string | null }
interface Product { id: string; name: string; slug: string; description: string | null; status: string; tax_category_id: string | null; category_ids: string[]; options: ProductOption[]; variants: Variant[] }
interface Pricing { price_list_id: string; name: string; currency: string; can_update: boolean }

const props = defineProps<{ product: Product; pricing: Pricing | null; categories: Choice[]; taxCategories: TaxCategoryChoice[]; productStatuses: string[]; variantStatuses: string[] }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('products.update'));
const canManageTaxes = computed(() => permissions.value.includes('taxes.manage'));
const productForm = useForm({ name: props.product.name, description: props.product.description ?? '', status: props.product.status, tax_category_id: props.product.tax_category_id, categories: [...props.product.category_ids] });
const optionValuesText = ref('');
const optionForm = useForm({ name: '', values: [] as string[] });
const variantForm = useForm({ name: '', sku: '', barcode: '', status: 'ACTIVE', is_default: false, option_value_ids: [] as string[] });
const variantEdits = reactive(Object.fromEntries(props.product.variants.map((variant) => [variant.id, { ...variant, option_value_ids: [...variant.option_value_ids] }])) as Record<string, Variant>);
const priceInputs = reactive(Object.fromEntries(props.product.variants.map((variant) => [variant.id, variant.default_price ?? ''])) as Record<string, string>);

const updateProduct = () => productForm
    .transform((data) => canManageTaxes.value ? data : {
        name: data.name, description: data.description, status: data.status, categories: data.categories,
    })
    .put(`/admin/products/${props.product.id}`);
const createOption = () => { optionForm.values = optionValuesText.value.split(',').map((value) => value.trim()).filter(Boolean); optionForm.post(`/admin/products/${props.product.id}/options`, { onSuccess: () => { optionForm.reset(); optionValuesText.value = ''; } }); };
const createVariant = () => variantForm.post(`/admin/products/${props.product.id}/variants`, { onSuccess: () => {
    variantForm.reset();
    props.product.variants.forEach((variant) => {
        if (!variantEdits[variant.id]) variantEdits[variant.id] = { ...variant, option_value_ids: [...variant.option_value_ids] };
        if (!(variant.id in priceInputs)) priceInputs[variant.id] = variant.default_price ?? '';
    });
} });
const updateVariant = (variant: Variant) => router.put(`/admin/products/${props.product.id}/variants/${variant.id}`, {
    name: variant.name,
    sku: variant.sku,
    barcode: variant.barcode,
    status: variant.status,
    is_default: variant.is_default,
    option_value_ids: variant.option_value_ids,
}, { preserveScroll: true });
const setPrice = (variant: Variant) => {
    if (props.pricing) router.put(`/admin/pricing/price-lists/${props.pricing.price_list_id}/variants/${variant.id}`, { amount: priceInputs[variant.id] }, { preserveScroll: true });
};
const removePrice = (variant: Variant) => {
    if (props.pricing) router.delete(`/admin/pricing/price-lists/${props.pricing.price_list_id}/variants/${variant.id}`, { preserveScroll: true, onSuccess: () => { priceInputs[variant.id] = ''; } });
};
const formatPrice = (amount: string | null) => {
    if (amount === null || !props.pricing) return 'Not priced';
    const [whole, fraction = ''] = amount.split('.');
    return `${props.pricing.currency} ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(4, '0')}`;
};
</script>

<template>
    <Head :title="`Edit ${product.name}`" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ product.slug }}</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ product.name }}</h1></div><Link href="/admin/products" class="text-sm font-semibold text-emerald-800">Back to products</Link></div>
            <form class="mt-10 grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="updateProduct"><h2 class="text-2xl font-semibold">Product details</h2><input v-model="productForm.name" required maxlength="160" class="border border-[#17211b]/25 bg-white px-4 py-3" /><textarea v-model="productForm.description" rows="4" maxlength="10000" class="border border-[#17211b]/25 bg-white px-4 py-3" /><select v-model="productForm.status" class="border border-[#17211b]/25 bg-white px-4 py-3"><option v-for="status in productStatuses" :key="status">{{ status }}</option></select><label v-if="taxCategories.length" class="grid gap-2 text-sm font-semibold">Tax category<select v-model="productForm.tax_category_id" :disabled="!canManageTaxes" class="border border-[#17211b]/25 bg-white px-4 py-3 disabled:opacity-60"><option :value="null">Not configured</option><option v-for="taxCategory in taxCategories" :key="taxCategory.id" :value="taxCategory.id">{{ taxCategory.name }} / {{ taxCategory.code }}</option></select><FieldError :message="productForm.errors.tax_category_id" /></label><div class="grid gap-2 sm:grid-cols-3"><label v-for="category in categories" :key="category.id" class="flex gap-2"><input v-model="productForm.categories" type="checkbox" :value="category.id" />{{ category.name }}</label></div><button v-if="canUpdate" :disabled="productForm.processing" class="w-fit bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Save product</button></form>

            <div class="mt-8 grid gap-8 lg:grid-cols-2">
                <form v-if="canUpdate" class="border border-[#17211b]/15 bg-white p-6" @submit.prevent="createOption"><h2 class="text-2xl font-semibold">Add option</h2><p class="mt-2 text-sm text-[#637067]">Example: Color with Negro, Blanco.</p><input v-model="optionForm.name" required maxlength="80" placeholder="Option name" class="mt-5 w-full border px-4 py-3" /><input v-model="optionValuesText" required placeholder="Comma-separated values" class="mt-3 w-full border px-4 py-3" /><FieldError :message="optionForm.errors.values" /><button :disabled="optionForm.processing" class="mt-4 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Add option</button></form>
                <div class="border border-[#17211b]/15 bg-[#f8f5ed] p-6"><h2 class="text-2xl font-semibold">Options</h2><div v-for="option in product.options" :key="option.id" class="mt-4"><strong>{{ option.name }}</strong><p class="text-sm text-[#637067]">{{ option.values.map((value) => value.value).join(', ') }}</p></div><p v-if="!product.options.length" class="mt-4 text-sm text-[#637067]">No structured options yet.</p></div>
            </div>

            <form v-if="canUpdate" class="mt-8 border border-[#17211b]/15 bg-white p-6" @submit.prevent="createVariant"><h2 class="text-2xl font-semibold">Create variant</h2><div class="mt-5 grid gap-3 sm:grid-cols-3"><input v-model="variantForm.name" placeholder="Display name" class="border px-4 py-3" /><input v-model="variantForm.sku" required placeholder="SKU" class="border px-4 py-3 font-mono" /><input v-model="variantForm.barcode" placeholder="Barcode" class="border px-4 py-3 font-mono" /></div><div class="mt-4 grid gap-4 sm:grid-cols-3"><fieldset v-for="option in product.options" :key="option.id"><legend class="font-semibold">{{ option.name }}</legend><label v-for="value in option.values" :key="value.id" class="mt-2 flex gap-2"><input v-model="variantForm.option_value_ids" type="checkbox" :value="value.id" />{{ value.value }}</label></fieldset></div><label class="mt-4 flex gap-2"><input v-model="variantForm.is_default" type="checkbox" />Make default</label><select v-model="variantForm.status" class="mt-4 border px-4 py-3"><option v-for="status in variantStatuses" :key="status">{{ status }}</option></select><FieldError :message="variantForm.errors.option_value_ids" /><button :disabled="variantForm.processing" class="mt-4 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Create variant</button></form>

            <div class="mt-8"><h2 class="text-3xl font-semibold">Variants</h2><div class="mt-5 grid gap-4"><form v-for="variant in variantEdits" :key="variant.id" class="grid gap-3 border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:grid-cols-4" @submit.prevent="updateVariant(variant)"><input v-model="variant.name" placeholder="Name" class="border bg-white px-3 py-2" /><input v-model="variant.sku" required class="border bg-white px-3 py-2 font-mono" /><input v-model="variant.barcode" placeholder="Barcode" class="border bg-white px-3 py-2 font-mono" /><select v-model="variant.status" class="border bg-white px-3 py-2"><option v-for="status in variantStatuses" :key="status">{{ status }}</option></select><div class="sm:col-span-3"><p class="text-sm">{{ variant.display_options.join(' / ') || 'No option values' }}</p><div class="mt-2 grid gap-3 sm:grid-cols-3"><fieldset v-for="option in product.options" :key="option.id"><legend class="font-semibold">{{ option.name }}</legend><label v-for="value in option.values" :key="value.id" class="mt-1 flex gap-2 text-sm"><input v-model="variant.option_value_ids" type="checkbox" :value="value.id" />{{ value.value }}</label></fieldset></div></div><div class="flex items-center justify-between gap-3"><label class="text-sm"><input v-model="variant.is_default" type="checkbox" /> Default</label><button v-if="canUpdate" class="bg-[#17211b] px-4 py-2 text-sm font-semibold text-white">Save</button></div></form></div></div>

            <section v-if="pricing" class="mt-8 border border-[#17211b]/15 bg-white p-6"><p class="font-mono text-xs tracking-[0.18em] text-emerald-800 uppercase">Default pricing / {{ pricing.currency }}</p><h2 class="mt-3 text-3xl font-semibold">{{ pricing.name }}</h2><div class="mt-5 grid gap-3"><form v-for="variant in product.variants" :key="variant.id" class="flex flex-wrap items-center justify-between gap-4 border-t border-[#17211b]/10 pt-4" @submit.prevent="setPrice(variant)"><div><strong class="font-mono">{{ variant.sku }}</strong><p class="mt-1 text-sm text-[#637067]">{{ formatPrice(variant.default_price) }}</p></div><div v-if="pricing.can_update" class="flex gap-2"><input v-model="priceInputs[variant.id]" required inputmode="decimal" placeholder="0.0000" class="w-36 border px-3 py-2 font-mono" /><button class="bg-[#17211b] px-4 py-2 text-sm font-semibold text-white">Save</button><button v-if="variant.default_price !== null" type="button" class="px-3 py-2 text-sm font-semibold text-red-800" @click="removePrice(variant)">Remove</button></div></form></div></section>
        </section>
    </AdminLayout>
</template>
