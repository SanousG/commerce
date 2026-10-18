<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Product { id: string; name: string }
interface Variant { id: string; name: string | null; sku: string }
interface Location { id: string; name: string; code: string }
interface Level { location_id: string; location_name: string; location_code: string; on_hand: number; reserved: number; available: number; cost_status?: string; average_unit_cost?: string | null; inventory_value?: string | null; cost_currency?: string | null }
interface Movement { id: string; location_name: string; type: string; quantity_delta: number; reason: string | null; occurred_at: string }
interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    product: Product;
    variant: Variant;
    levels: Level[];
    movements: { data: Movement[]; links: PageLink[] };
    locations: Location[];
    can_adjust: boolean;
    can_view_cost: boolean;
    can_manage_cost: boolean;
}>();

const selectedLocationId = ref('');
const form = useForm({ quantity_delta: 0, reason: '' });
const submit = () => form.post(`/admin/inventory/locations/${selectedLocationId.value}/variants/${props.variant.id}/adjust`, {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});
const costForms = Object.fromEntries(props.levels.map((level) => [level.location_id, useForm({ unit_cost: '', currency: 'PEN' })]));
const initializeCost = (level: Level) => costForms[level.location_id].post(`/admin/inventory/locations/${level.location_id}/variants/${props.variant.id}/initialize-cost`, { preserveScroll: true });
</script>

<template>
    <Head :title="`${product.name} inventory`" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ variant.sku }}<template v-if="variant.name"> / {{ variant.name }}</template></p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ product.name }}</h1>
                </div>
                <Link href="/admin/inventory" class="font-semibold text-emerald-800 hover:underline">Back to inventory</Link>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="level in levels" :key="level.location_id" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                    <p class="font-mono text-xs tracking-[0.16em] text-[#637067] uppercase">{{ level.location_code }}</p>
                    <h2 class="mt-2 text-xl font-semibold">{{ level.location_name }}</h2>
                    <dl class="mt-5 grid grid-cols-3 gap-2 text-sm"><div><dt class="text-[#637067]">On hand</dt><dd class="mt-1 text-lg font-semibold tabular-nums">{{ level.on_hand }}</dd></div><div><dt class="text-[#637067]">Reserved</dt><dd class="mt-1 text-lg font-semibold tabular-nums">{{ level.reserved }}</dd></div><div><dt class="text-[#637067]">Available</dt><dd class="mt-1 text-lg font-semibold tabular-nums">{{ level.available }}</dd></div></dl>
                    <div v-if="can_view_cost" class="mt-5 border-t border-[#17211b]/15 pt-4"><p class="text-xs font-semibold tracking-wide uppercase" :class="level.cost_status === 'ACTIVE' ? 'text-emerald-800' : 'text-amber-800'">{{ level.cost_status }}</p><dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-[#637067]">Average cost</dt><dd class="mt-1 font-mono font-semibold">{{ level.average_unit_cost === null ? 'Not initialized' : `${level.cost_currency} ${level.average_unit_cost}` }}</dd></div><div><dt class="text-[#637067]">Inventory value</dt><dd class="mt-1 font-mono font-semibold">{{ level.inventory_value === null ? 'Unavailable' : `${level.cost_currency} ${level.inventory_value}` }}</dd></div></dl><form v-if="can_manage_cost && level.cost_status === 'UNINITIALIZED' && level.on_hand > 0" class="mt-4 grid grid-cols-[1fr_6rem_auto] gap-2" @submit.prevent="initializeCost(level)"><input v-model="costForms[level.location_id].unit_cost" required inputmode="decimal" placeholder="Unit cost" class="min-w-0 border bg-white px-3 py-2 text-sm" /><input v-model="costForms[level.location_id].currency" required maxlength="3" class="min-w-0 border bg-white px-3 py-2 font-mono text-sm uppercase" /><button :disabled="costForms[level.location_id].processing" class="bg-[#17211b] px-3 py-2 text-xs font-semibold text-white disabled:opacity-40">Initialize</button><FieldError class="col-span-3" :message="costForms[level.location_id].errors.unit_cost ?? costForms[level.location_id].errors.currency" /></form></div>
                </article>
                <p v-if="!levels.length" class="border border-dashed border-[#17211b]/25 p-6 text-[#637067] sm:col-span-2 lg:col-span-3">No stock has been recorded for this variant.</p>
            </div>

            <form v-if="can_adjust" class="mt-10 grid gap-4 border border-[#17211b]/15 bg-white p-6 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submit">
                <div><label class="mb-2 block text-sm font-semibold">Location</label><select v-model="selectedLocationId" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="" disabled>Select a location</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }} / {{ location.code }}</option></select></div>
                <div><label class="mb-2 block text-sm font-semibold">Quantity change</label><input v-model.number="form.quantity_delta" required type="number" step="1" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.quantity_delta" /></div>
                <div class="sm:col-span-2"><label class="mb-2 block text-sm font-semibold">Reason</label><input v-model="form.reason" required maxlength="255" placeholder="Stock count correction" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.reason" /></div>
                <button :disabled="form.processing || !selectedLocationId" class="w-fit bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50 sm:col-span-2 lg:col-span-4">{{ form.processing ? 'Saving...' : 'Record adjustment' }}</button>
            </form>

            <div class="mt-12"><h2 class="text-3xl font-semibold tracking-[-0.03em]">Movement history</h2><div class="mt-6 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]"><table class="w-full min-w-[700px] text-left text-sm"><thead class="border-b border-[#17211b]/15 font-mono text-xs uppercase"><tr><th class="p-4">Date</th><th class="p-4">Location</th><th class="p-4">Type</th><th class="p-4">Change</th><th class="p-4">Reason</th></tr></thead><tbody><tr v-for="movement in movements.data" :key="movement.id" class="border-b border-[#17211b]/10 last:border-0"><td class="p-4">{{ movement.occurred_at }}</td><td class="p-4">{{ movement.location_name }}</td><td class="p-4">{{ movement.type }}</td><td class="p-4 font-semibold tabular-nums" :class="movement.quantity_delta < 0 ? 'text-red-800' : 'text-emerald-800'">{{ movement.quantity_delta > 0 ? '+' : '' }}{{ movement.quantity_delta }}</td><td class="p-4">{{ movement.reason ?? 'None' }}</td></tr><tr v-if="!movements.data.length"><td colspan="5" class="p-8 text-center text-[#637067]">No movements recorded.</td></tr></tbody></table></div><nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in movements.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : ''" v-html="link.label" /></template></nav></div>
        </section>
    </AdminLayout>
</template>
