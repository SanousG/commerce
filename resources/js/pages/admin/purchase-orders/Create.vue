<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface SupplierProduct { id: string; product_name: string; variant_name: string | null; sku: string; supplier_sku: string | null; unit_cost: string; currency: string; minimum_order_quantity: number; order_multiple: number | null }
interface Supplier { id: string; name: string; code: string; supplier_products: SupplierProduct[] }
const props = defineProps<{ suppliers: Supplier[] }>();
const form = useForm({ supplier_id: '', currency: '', expected_at: '', notes: '', items: [{ supplier_product_id: '', quantity: 1, unit_cost: '' }] });
const selectedSupplier = computed(() => props.suppliers.find((supplier) => supplier.id === form.supplier_id));
const availableProducts = computed(() => selectedSupplier.value?.supplier_products ?? []);
const addLine = () => form.items.push({ supplier_product_id: '', quantity: 1, unit_cost: '' });
const removeLine = (index: number) => form.items.splice(index, 1);
const chooseProduct = (index: number) => {
    const line = form.items[index];
    const product = availableProducts.value.find((item) => item.id === line.supplier_product_id);
    if (product) { line.unit_cost = product.unit_cost; line.quantity = product.minimum_order_quantity; form.currency = product.currency; }
};
watch(() => form.supplier_id, () => { form.currency = ''; form.items = [{ supplier_product_id: '', quantity: 1, unit_cost: '' }]; });
const submit = () => form.post('/admin/purchase-orders');
</script>

<template>
    <Head title="Create purchase order" />
    <AdminLayout><section><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Inbound procurement</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">New purchase order.</h1></div><Link href="/admin/purchase-orders" class="font-semibold text-emerald-800">Back to purchase orders</Link></div>
        <form class="mt-10 grid gap-8" @submit.prevent="submit"><div class="grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-3 sm:p-8"><div><label class="mb-2 block font-semibold">Supplier</label><select v-model="form.supplier_id" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="" disabled>Select supplier</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }} / {{ supplier.code }}</option></select><FieldError :message="form.errors.supplier_id" /></div><div><label class="mb-2 block font-semibold">Currency</label><input v-model="form.currency" required maxlength="3" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 font-mono uppercase" /><FieldError :message="form.errors.currency" /></div><div><label class="mb-2 block font-semibold">Expected date</label><input v-model="form.expected_at" type="date" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.expected_at" /></div><div class="sm:col-span-3"><label class="mb-2 block font-semibold">Internal notes</label><textarea v-model="form.notes" rows="3" maxlength="10000" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.notes" /></div></div>
            <section><div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-3xl font-semibold">Draft items</h2><p class="mt-2 text-sm text-[#637067]">Costs remain strings and can be adjusted before approval.</p></div><button type="button" :disabled="!selectedSupplier" class="border border-[#17211b] px-4 py-2 text-sm font-semibold disabled:opacity-40" @click="addLine">Add line</button></div><FieldError :message="form.errors.items" /><div class="mt-5 grid gap-3"><div v-for="(line, index) in form.items" :key="index" class="grid gap-3 border border-[#17211b]/15 bg-white p-4 md:grid-cols-[minmax(0,1fr)_8rem_10rem_auto] md:items-start"><label class="text-sm font-semibold">Supplier product<select v-model="line.supplier_product_id" required :disabled="!selectedSupplier" class="mt-2 w-full border bg-white px-3 py-3 font-normal disabled:opacity-50" @change="chooseProduct(index)"><option value="" disabled>{{ selectedSupplier ? 'Select product' : 'Select a supplier first' }}</option><option v-for="product in availableProducts" :key="product.id" :value="product.id">{{ product.product_name }} / {{ product.sku }}<template v-if="product.variant_name"> / {{ product.variant_name }}</template><template v-if="product.supplier_sku"> / {{ product.supplier_sku }}</template> / MOQ {{ product.minimum_order_quantity }}<template v-if="product.order_multiple"> / multiple {{ product.order_multiple }}</template></option></select><FieldError :message="form.errors[`items.${index}.supplier_product_id`]" /></label><label class="text-sm font-semibold">Quantity<input v-model.number="line.quantity" required type="number" min="1" class="mt-2 w-full border px-3 py-3 font-mono font-normal" /><FieldError :message="form.errors[`items.${index}.quantity`]" /></label><label class="text-sm font-semibold">Unit cost<input v-model="line.unit_cost" inputmode="decimal" class="mt-2 w-full border px-3 py-3 font-mono font-normal" /><FieldError :message="form.errors[`items.${index}.unit_cost`]" /></label><button v-if="form.items.length > 1" type="button" class="mt-8 px-3 py-3 text-sm font-semibold text-red-800" @click="removeLine(index)">Remove</button></div></div><p v-if="selectedSupplier && !availableProducts.length" class="mt-4 border border-amber-700/30 bg-amber-50 p-4 text-sm">This supplier has no active products. Add products to the supplier before creating a purchase order.</p></section>
            <div class="flex flex-wrap gap-4"><button :disabled="form.processing || !availableProducts.length" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Creating...' : 'Create draft' }}</button><Link href="/admin/purchase-orders" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div>
        </form>
    </section></AdminLayout>
</template>
