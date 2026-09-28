<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Fulfillment {
    id: string;
    order_id: string;
    order_number: string;
    location_name: string;
    location_code: string;
    status: string;
    item_count: number;
    total_quantity: number;
    shipment_statuses: string[];
    created_at: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
defineProps<{ fulfillments: { data: Fulfillment[]; links: PageLink[] } }>();
</script>

<template>
    <Head title="Fulfillments" />
    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Pick, pack, dispatch</p>
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div><h1 class="text-5xl font-semibold tracking-[-0.04em]">Fulfillments</h1><p class="mt-3 max-w-2xl text-[#637067]">Store-scoped work moving reserved inventory to customers.</p></div>
                <Link href="/admin/orders" class="border border-[#17211b] px-4 py-3 text-sm font-semibold">Open orders</Link>
            </div>

            <div class="mt-8 grid gap-4 md:hidden">
                <Link v-for="fulfillment in fulfillments.data" :key="fulfillment.id" :href="`/admin/fulfillments/${fulfillment.id}`" class="border border-[#17211b]/15 bg-white p-5">
                    <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-emerald-800">{{ fulfillment.order_number }}</p><p class="mt-1 text-sm text-[#637067]">{{ fulfillment.location_code }} / {{ fulfillment.location_name }}</p></div><span class="bg-[#17211b] px-2 py-1 font-mono text-xs text-white">{{ fulfillment.status }}</span></div>
                    <p class="mt-4 text-sm">{{ fulfillment.item_count }} lines / {{ fulfillment.total_quantity }} units</p>
                    <p class="mt-2 text-xs text-[#637067]">Shipments: {{ fulfillment.shipment_statuses.join(', ') || 'Not created' }}</p>
                </Link>
            </div>

            <div class="mt-8 hidden overflow-x-auto border border-[#17211b]/15 bg-white md:block">
                <table class="min-w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Order</th><th class="p-4">Location</th><th class="p-4">Status</th><th class="p-4">Work</th><th class="p-4">Shipments</th><th class="p-4">Created</th></tr></thead><tbody>
                    <tr v-for="fulfillment in fulfillments.data" :key="fulfillment.id" class="border-t border-[#17211b]/10">
                        <td class="p-4"><Link :href="`/admin/fulfillments/${fulfillment.id}`" class="font-semibold text-emerald-800">{{ fulfillment.order_number }}</Link><p class="mt-1 font-mono text-xs text-[#637067]">{{ fulfillment.id }}</p></td>
                        <td class="p-4"><span class="font-mono">{{ fulfillment.location_code }}</span><br><span class="text-[#637067]">{{ fulfillment.location_name }}</span></td>
                        <td class="p-4 font-mono font-semibold">{{ fulfillment.status }}</td><td class="p-4">{{ fulfillment.item_count }} lines / {{ fulfillment.total_quantity }} units</td>
                        <td class="p-4">{{ fulfillment.shipment_statuses.join(', ') || 'Not created' }}</td><td class="p-4">{{ new Date(fulfillment.created_at).toLocaleString() }}</td>
                    </tr>
                    <tr v-if="!fulfillments.data.length"><td colspan="6" class="p-10 text-center text-[#637067]">No fulfillment work has been created for this store.</td></tr>
                </tbody></table>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2"><Link v-for="link in fulfillments.links" :key="link.label" :href="link.url ?? ''" :class="['border px-3 py-2 text-sm', link.active ? 'bg-[#17211b] text-white' : '', !link.url ? 'pointer-events-none opacity-40' : '']" preserve-state><span v-html="link.label" /></Link></nav>
        </section>
    </AdminLayout>
</template>
