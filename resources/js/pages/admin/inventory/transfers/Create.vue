<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../../components/FieldError.vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';

interface Location { id: string; name: string; code: string }
interface Variant { id: string; product_name: string; name: string | null; sku: string }
defineProps<{ locations: Location[]; variants: Variant[] }>();

const form = useForm({
    source_location_id: '',
    destination_location_id: '',
    notes: '',
    lines: [{ product_variant_id: '', quantity: 1 }],
});
const addLine = () => form.lines.push({ product_variant_id: '', quantity: 1 });
const removeLine = (index: number) => form.lines.splice(index, 1);
const submit = () => form.post('/admin/inventory/transfers');
</script>

<template>
    <Head title="Create inventory transfer" />
    <AdminLayout><section><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Stock movement</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">New transfer.</h1></div><Link href="/admin/inventory" class="font-semibold text-emerald-800">Back to inventory</Link></div>
        <form class="mt-10 grid gap-8" @submit.prevent="submit"><div class="grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-2 sm:p-8"><div><label class="mb-2 block font-semibold">From</label><select v-model="form.source_location_id" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="" disabled>Select source</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }} / {{ location.code }}</option></select><FieldError :message="form.errors.source_location_id" /></div><div><label class="mb-2 block font-semibold">To</label><select v-model="form.destination_location_id" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="" disabled>Select destination</option><option v-for="location in locations" :key="location.id" :value="location.id" :disabled="location.id === form.source_location_id">{{ location.name }} / {{ location.code }}</option></select><FieldError :message="form.errors.destination_location_id" /></div><div class="sm:col-span-2"><label class="mb-2 block font-semibold">Notes</label><textarea v-model="form.notes" rows="3" maxlength="2000" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.notes" /></div></div>
            <div><div class="flex flex-wrap items-center justify-between gap-4"><h2 class="text-3xl font-semibold">Transfer lines</h2><button type="button" class="border border-[#17211b] px-4 py-2 text-sm font-semibold" @click="addLine">Add line</button></div><FieldError :message="form.errors.lines" /><div class="mt-5 grid gap-3"><div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 border border-[#17211b]/15 bg-white p-4 sm:grid-cols-[1fr_10rem_auto] sm:items-start"><div><label :for="`variant-${index}`" class="mb-2 block text-sm font-semibold">Variant</label><select :id="`variant-${index}`" v-model="line.product_variant_id" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="" disabled>Select product variant</option><option v-for="variant in variants" :key="variant.id" :value="variant.id">{{ variant.product_name }} / {{ variant.sku }}<template v-if="variant.name"> / {{ variant.name }}</template></option></select><FieldError :message="form.errors[`lines.${index}.product_variant_id`]" /></div><div><label :for="`quantity-${index}`" class="mb-2 block text-sm font-semibold">Quantity</label><input :id="`quantity-${index}`" v-model.number="line.quantity" required type="number" min="1" step="1" class="w-full border border-[#17211b]/25 px-4 py-3" /><FieldError :message="form.errors[`lines.${index}.quantity`]" /></div><button v-if="form.lines.length > 1" type="button" class="mt-8 px-3 py-3 text-sm font-semibold text-red-800" @click="removeLine(index)">Remove</button></div></div></div>
            <div class="flex flex-wrap gap-4"><button :disabled="form.processing || locations.length < 2 || !variants.length" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Transferring...' : 'Create transfer' }}</button><Link href="/admin/inventory" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div>
        </form>
    </section></AdminLayout>
</template>
