<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface SupplierProduct {
    id: string;
    product_name: string;
    variant_name: string | null;
    sku: string;
    supplier_sku: string | null;
    unit_cost: string;
    currency: string;
    minimum_order_quantity: number;
    order_multiple: number | null;
}
interface PurchaseOrderItem {
    id: string;
    supplier_product_id: string;
    product_name: string;
    variant_name: string | null;
    sku: string;
    supplier_sku: string | null;
    description: string | null;
    quantity_ordered: number;
    received_quantity: number;
    remaining_quantity: number;
    unit_cost: string;
    currency: string;
    line_total: string;
}
interface PurchaseOrder {
    id: string;
    number: string;
    status: string;
    currency: string;
    supplier_name: string;
    supplier_code: string;
    expected_at: string | null;
    ordered_at: string | null;
    cancelled_at: string | null;
    completed_at: string | null;
    notes: string | null;
    subtotal: string;
    discount_total: string;
    shipping_total: string;
    tax_total: string;
    grand_total: string;
}
interface ReceiptItem { id: string; purchase_order_item_id: string; product_name: string; sku: string; quantity: number; unit_cost: string }
interface Receipt { id: string; number: string; status: 'DRAFT' | 'POSTED' | 'CANCELLED'; inventory_location_id: string; location_name: string; location_code: string; reference: string | null; notes: string | null; received_at: string; items: ReceiptItem[] }
interface Location { id: string; name: string; code: string }
interface Capabilities { update: boolean; update_items: boolean; approve: boolean; mark_ordered: boolean; cancel: boolean; receive: boolean }

const props = defineProps<{ purchaseOrder: PurchaseOrder; items: PurchaseOrderItem[]; receipts: Receipt[]; locations: Location[]; capabilities: Capabilities; supplierProducts: SupplierProduct[]; statuses: string[] }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canReceive = computed(() => permissions.value.includes('procurement.receive'));
const serverErrors = computed(() => page.props.errors as Record<string, string | undefined>);
const orderForm = useForm({ expected_at: props.purchaseOrder.expected_at?.slice(0, 10) ?? '', notes: props.purchaseOrder.notes ?? '' });
const itemForms = props.items.map((item) => ({ id: item.id, form: useForm({ quantity: item.quantity_ordered, unit_cost: item.unit_cost, description: item.description ?? '' }) }));
const addItemForm = useForm({ supplier_product_id: '', quantity: 1, unit_cost: '', description: '' });
const receiptForm = useForm({ inventory_location_id: '', reference: '', received_at: '', notes: '', items: props.items.filter((item) => item.remaining_quantity > 0).map((item) => ({ purchase_order_item_id: item.id, quantity: item.remaining_quantity })) });
const money = (amount: string) => `${props.purchaseOrder.currency} ${amount}`;
const itemForReceipt = (itemId: string) => props.items.find((item) => item.id === itemId);
const saveOrder = () => router.patch(`/admin/purchase-orders/${props.purchaseOrder.id}`, orderForm.data(), { preserveScroll: true, onError: (errors) => orderForm.setError(errors) });
const chooseProduct = () => {
    const product = props.supplierProducts.find((entry) => entry.id === addItemForm.supplier_product_id);
    if (product) { addItemForm.unit_cost = product.unit_cost; addItemForm.quantity = product.minimum_order_quantity; }
};
const addItem = () => addItemForm.post(`/admin/purchase-orders/${props.purchaseOrder.id}/items`, { preserveScroll: true, onSuccess: () => addItemForm.reset() });
const updateItem = (entry: (typeof itemForms)[number]) => router.patch(`/admin/purchase-order-items/${entry.id}`, entry.form.data(), { preserveScroll: true, onError: (errors) => entry.form.setError(errors) });
const removeItem = (item: PurchaseOrderItem) => { if (window.confirm(`Remove ${item.sku} from this draft?`)) router.delete(`/admin/purchase-order-items/${item.id}`, { preserveScroll: true }); };
const approve = () => router.post(`/admin/purchase-orders/${props.purchaseOrder.id}/approve`, {}, { preserveScroll: true });
const markOrdered = () => router.post(`/admin/purchase-orders/${props.purchaseOrder.id}/mark-ordered`, {}, { preserveScroll: true });
const cancelOrder = () => { if (window.confirm('Cancel this purchase order?')) router.post(`/admin/purchase-orders/${props.purchaseOrder.id}/cancel`, {}, { preserveScroll: true }); };
const createReceipt = () => receiptForm.transform((data) => ({ ...data, items: data.items.filter((item) => Number(item.quantity) > 0) })).post(`/admin/purchase-orders/${props.purchaseOrder.id}/receipts`, { preserveScroll: true });
const postReceipt = (receipt: Receipt) => { if (window.confirm('Post this receipt and add its quantities to inventory?')) router.post(`/admin/purchase-receipts/${receipt.id}/post`, {}, { preserveScroll: true }); };
const cancelReceipt = (receipt: Receipt) => { if (window.confirm('Cancel this draft receipt?')) router.post(`/admin/purchase-receipts/${receipt.id}/cancel`, {}, { preserveScroll: true }); };
</script>

<template>
    <Head :title="`Purchase order ${purchaseOrder.number}`" />
    <AdminLayout><section>
        <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ purchaseOrder.status }} / {{ purchaseOrder.supplier_code }}</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ purchaseOrder.number }}</h1><p class="mt-3 text-[#637067]">{{ purchaseOrder.supplier_name }}</p></div><Link href="/admin/purchase-orders" class="border border-[#17211b] px-4 py-3 text-sm font-semibold">Back to purchase orders</Link></div>
        <p v-if="page.props.status" class="mt-6 border border-emerald-700 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ page.props.status }}</p>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><article v-for="row in [['Status', purchaseOrder.status], ['Expected', purchaseOrder.expected_at ? new Date(purchaseOrder.expected_at).toLocaleDateString() : 'Not set'], ['Ordered', purchaseOrder.ordered_at ? new Date(purchaseOrder.ordered_at).toLocaleDateString() : 'Not ordered'], ['Grand total', money(purchaseOrder.grand_total)]]" :key="row[0]" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5"><p class="text-xs uppercase text-[#637067]">{{ row[0] }}</p><p class="mt-2 font-mono text-lg font-semibold">{{ row[1] }}</p></article></div>
        <div v-if="capabilities.approve || capabilities.mark_ordered || capabilities.cancel" class="mt-6 flex flex-wrap gap-3"><button v-if="capabilities.approve" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white" @click="approve">Approve order</button><button v-if="capabilities.mark_ordered" class="border border-emerald-800 px-5 py-3 text-sm font-semibold text-emerald-900" @click="markOrdered">Mark ordered</button><button v-if="capabilities.cancel" class="px-5 py-3 text-sm font-semibold text-red-800" @click="cancelOrder">Cancel order</button></div><FieldError :message="serverErrors.purchase_order" />

        <form v-if="capabilities.update" class="mt-10 grid gap-4 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-3" @submit.prevent="saveOrder"><h2 class="text-2xl font-semibold sm:col-span-3">Draft details</h2><label class="text-sm font-semibold">Expected date<input v-model="orderForm.expected_at" type="date" class="mt-2 w-full border bg-white px-3 py-3 font-normal" /></label><label class="text-sm font-semibold sm:col-span-2">Notes<textarea v-model="orderForm.notes" rows="3" maxlength="10000" class="mt-2 w-full border bg-white px-3 py-3 font-normal" /></label><div class="sm:col-span-3"><FieldError :message="orderForm.errors.expected_at ?? orderForm.errors.notes" /><button class="mt-2 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Save draft details</button></div></form>

        <section class="mt-10"><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Committed costs</p><h2 class="mt-2 text-3xl font-semibold">Order items</h2></div><p class="font-mono text-lg font-semibold">{{ money(purchaseOrder.subtotal) }}</p></div>
            <div class="mt-5 grid gap-4"><form v-for="(entry, index) in itemForms" :key="entry.id" class="grid gap-4 border border-[#17211b]/15 bg-white p-5 md:grid-cols-[minmax(0,1fr)_8rem_10rem_10rem_auto] md:items-end" @submit.prevent="updateItem(entry)"><div><p class="font-semibold">{{ items[index].product_name }}</p><p class="text-xs text-[#637067]">{{ items[index].variant_name ?? 'Default variant' }} / {{ items[index].sku }}<template v-if="items[index].supplier_sku"> / supplier {{ items[index].supplier_sku }}</template></p><p class="mt-2 text-xs font-semibold text-emerald-800">{{ items[index].quantity_ordered }} ordered / {{ items[index].received_quantity }} received / {{ items[index].remaining_quantity }} remaining</p></div><label class="text-sm font-semibold">Quantity<input v-model.number="entry.form.quantity" :disabled="!capabilities.update_items" type="number" min="1" class="mt-2 w-full border px-3 py-2 font-mono font-normal disabled:opacity-60" /></label><label class="text-sm font-semibold">Unit cost<input v-model="entry.form.unit_cost" :disabled="!capabilities.update_items" inputmode="decimal" class="mt-2 w-full border px-3 py-2 font-mono font-normal disabled:opacity-60" /></label><div><p class="text-sm font-semibold">Line total</p><p class="mt-3 font-mono">{{ money(items[index].line_total) }}</p></div><div v-if="capabilities.update_items" class="flex gap-3"><button class="font-semibold text-emerald-800">Save</button><button type="button" class="font-semibold text-red-800" @click="removeItem(items[index])">Remove</button></div><label v-if="capabilities.update_items" class="text-sm font-semibold md:col-span-5">Description<textarea v-model="entry.form.description" rows="2" maxlength="10000" class="mt-2 w-full border px-3 py-2 font-normal" /></label><FieldError class="md:col-span-5" :message="entry.form.errors.quantity ?? entry.form.errors.unit_cost ?? entry.form.errors.description" /></form><p v-if="!items.length" class="border border-dashed p-8 text-center text-[#637067]">No draft items yet.</p></div>
            <form v-if="capabilities.update_items && supplierProducts.length" class="mt-6 grid gap-4 border border-dashed border-[#17211b]/30 bg-[#f8f5ed] p-5 md:grid-cols-[minmax(0,1fr)_8rem_10rem_auto] md:items-end" @submit.prevent="addItem"><label class="text-sm font-semibold">Add supplier product<select v-model="addItemForm.supplier_product_id" required class="mt-2 w-full border bg-white px-3 py-3 font-normal" @change="chooseProduct"><option value="" disabled>Select a product</option><option v-for="product in supplierProducts" :key="product.id" :value="product.id">{{ product.product_name }} / {{ product.sku }} / MOQ {{ product.minimum_order_quantity }}<template v-if="product.order_multiple"> / multiple {{ product.order_multiple }}</template></option></select></label><label class="text-sm font-semibold">Quantity<input v-model.number="addItemForm.quantity" required type="number" min="1" class="mt-2 w-full border bg-white px-3 py-3 font-mono font-normal" /></label><label class="text-sm font-semibold">Unit cost<input v-model="addItemForm.unit_cost" inputmode="decimal" class="mt-2 w-full border bg-white px-3 py-3 font-mono font-normal" /></label><button :disabled="addItemForm.processing" class="bg-[#17211b] px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Add item</button><label class="text-sm font-semibold md:col-span-4">Description<textarea v-model="addItemForm.description" rows="2" maxlength="10000" class="mt-2 w-full border bg-white px-3 py-2 font-normal" /></label><FieldError class="md:col-span-4" :message="addItemForm.errors.supplier_product_id ?? addItemForm.errors.quantity ?? addItemForm.errors.unit_cost ?? addItemForm.errors.description" /></form>
        </section>

        <form v-if="capabilities.receive && receiptForm.items.length" class="mt-10 border border-emerald-800/30 bg-emerald-50 p-6" @submit.prevent="createReceipt"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Warehouse intake</p><h2 class="mt-2 text-3xl font-semibold">Create draft receipt</h2><p class="mt-2 text-sm text-[#637067]">Review the draft after creation. Inventory changes only when the receipt is posted.</p><div class="mt-5 grid gap-4 sm:grid-cols-3"><label class="text-sm font-semibold">Location<select v-model="receiptForm.inventory_location_id" required class="mt-2 w-full border bg-white px-3 py-3 font-normal"><option value="" disabled>Select location</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.code }} / {{ location.name }}</option></select></label><label class="text-sm font-semibold">Supplier reference<input v-model="receiptForm.reference" maxlength="100" class="mt-2 w-full border bg-white px-3 py-3 font-normal" /></label><label class="text-sm font-semibold">Received at<input v-model="receiptForm.received_at" type="datetime-local" class="mt-2 w-full border bg-white px-3 py-3 font-normal" /></label><label class="text-sm font-semibold sm:col-span-3">Notes<textarea v-model="receiptForm.notes" rows="3" maxlength="10000" class="mt-2 w-full border bg-white px-3 py-3 font-normal" /></label></div>
            <div class="mt-5 overflow-x-auto border border-emerald-800/20 bg-white"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-emerald-900 text-white"><tr><th class="p-3">Item</th><th class="p-3">Ordered</th><th class="p-3">Already received</th><th class="p-3">Remaining</th><th class="p-3">Receiving now</th></tr></thead><tbody><tr v-for="(line, index) in receiptForm.items" :key="line.purchase_order_item_id" class="border-t border-emerald-800/10"><td class="p-3"><strong>{{ itemForReceipt(line.purchase_order_item_id)?.product_name }}</strong><br><span class="font-mono text-xs text-[#637067]">{{ itemForReceipt(line.purchase_order_item_id)?.sku }}</span></td><td class="p-3 font-mono">{{ itemForReceipt(line.purchase_order_item_id)?.quantity_ordered }}</td><td class="p-3 font-mono">{{ itemForReceipt(line.purchase_order_item_id)?.received_quantity }}</td><td class="p-3 font-mono">{{ itemForReceipt(line.purchase_order_item_id)?.remaining_quantity }}</td><td class="p-3"><input v-model.number="line.quantity" required type="number" min="0" :max="itemForReceipt(line.purchase_order_item_id)?.remaining_quantity" class="w-24 border px-3 py-2 font-mono" /><FieldError :message="receiptForm.errors[`items.${index}.quantity`]" /></td></tr></tbody></table></div><FieldError :message="receiptForm.errors.inventory_location_id ?? receiptForm.errors.received_at ?? receiptForm.errors.reference ?? receiptForm.errors.notes ?? receiptForm.errors.items ?? serverErrors.receipt" /><button :disabled="receiptForm.processing || !locations.length" class="mt-5 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-40">Create draft receipt</button>
        </form>

        <section class="mt-10"><h2 class="text-3xl font-semibold">Receipts</h2><div class="mt-5 grid gap-4 lg:grid-cols-2"><article v-for="receipt in receipts" :key="receipt.id" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5"><div class="flex flex-wrap justify-between gap-3"><div><p class="font-mono font-semibold">{{ receipt.number }}</p><p class="text-sm text-[#637067]">{{ receipt.location_code }} / {{ receipt.location_name }}</p></div><div class="text-right"><span class="bg-[#17211b] px-2 py-1 font-mono text-xs text-white">{{ receipt.status }}</span><p class="mt-2 text-sm">{{ new Date(receipt.received_at).toLocaleString() }}</p></div></div><p v-if="receipt.reference" class="mt-3 text-sm">Reference: {{ receipt.reference }}</p><p v-if="receipt.notes" class="mt-2 whitespace-pre-wrap text-sm text-[#637067]">{{ receipt.notes }}</p><ul class="mt-4 space-y-2 text-sm"><li v-for="item in receipt.items" :key="item.id" class="flex justify-between border-t pt-2"><span>{{ item.product_name }} / {{ item.sku }}</span><strong class="font-mono">{{ item.quantity }}</strong></li></ul><div v-if="receipt.status === 'DRAFT' && canReceive" class="mt-5 flex flex-wrap gap-3"><button class="bg-emerald-900 px-4 py-2 text-sm font-semibold text-white" @click="postReceipt(receipt)">Post receipt</button><button class="px-4 py-2 text-sm font-semibold text-red-800" @click="cancelReceipt(receipt)">Cancel receipt</button></div></article><p v-if="!receipts.length" class="text-[#637067]">No receipts registered.</p></div></section>

        <section class="mt-10 grid gap-6 lg:grid-cols-2"><article class="border border-[#17211b]/15 bg-white p-6"><h2 class="text-2xl font-semibold">Totals</h2><dl class="mt-5 grid gap-3 text-sm"><div v-for="row in [['Subtotal', purchaseOrder.subtotal], ['Discount', purchaseOrder.discount_total], ['Shipping', purchaseOrder.shipping_total], ['Tax', purchaseOrder.tax_total], ['Grand total', purchaseOrder.grand_total]]" :key="row[0]" class="flex justify-between border-b pb-2"><dt>{{ row[0] }}</dt><dd class="font-mono font-semibold">{{ money(row[1]) }}</dd></div></dl></article><article class="border border-[#17211b]/15 bg-white p-6"><h2 class="text-2xl font-semibold">Procurement record</h2><p class="mt-4 whitespace-pre-wrap text-sm text-[#637067]">{{ purchaseOrder.notes || 'No notes.' }}</p><dl class="mt-5 grid gap-2 text-sm"><div class="flex justify-between"><dt>Ordered</dt><dd>{{ purchaseOrder.ordered_at ? new Date(purchaseOrder.ordered_at).toLocaleString() : 'Not ordered' }}</dd></div><div class="flex justify-between"><dt>Completed</dt><dd>{{ purchaseOrder.completed_at ? new Date(purchaseOrder.completed_at).toLocaleString() : 'Not completed' }}</dd></div><div class="flex justify-between"><dt>Cancelled</dt><dd>{{ purchaseOrder.cancelled_at ? new Date(purchaseOrder.cancelled_at).toLocaleString() : 'Not cancelled' }}</dd></div></dl></article></section>
    </section></AdminLayout>
</template>
