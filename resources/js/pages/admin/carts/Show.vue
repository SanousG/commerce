<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Promotion { id: string; name: string; internal_code: string | null }
interface CartItem {
    id: string;
    listing_id: string;
    product: string;
    variant: string;
    sku: string;
    quantity: number;
    available: number;
    base_unit_price: string | null;
    final_unit_price: string | null;
    line_base_total: string | null;
    line_discount: string | null;
    line_total: string | null;
    applied_promotions: Promotion[];
    issues: string[];
}
interface Cart {
    id: string;
    currency: string | null;
    item_count: number;
    subtotal_before_discounts: string;
    discount_total: string;
    subtotal: string;
    valid: boolean;
    items: CartItem[];
    store: string;
    customer_id: string | null;
    customer: string | null;
    status: string;
    token_hint: string;
    expires_at: string | null;
}
interface Customer { id: string; name: string }
interface ListingVariant { id: string; name: string; sku: string }
interface Listing { id: string; product: string; variants: ListingVariant[] }

const props = defineProps<{
    cart: Cart;
    customers: Customer[];
    listings: Listing[];
    canManage: boolean;
}>();

const page = usePage<PageProps>();
const serverErrors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);
const addForm = useForm({ store_listing_id: '', product_variant_id: '', quantity: 1 });
const customerForm = useForm({ customer_id: props.cart.customer_id ?? '' });
const quantities = reactive<Record<string, number>>(Object.fromEntries(props.cart.items.map((item) => [item.id, item.quantity])));
const updatingItem = ref<string | null>(null);
const deletingItem = ref<string | null>(null);
const selectedListing = computed(() => props.listings.find((listing) => listing.id === addForm.store_listing_id));

watch(() => addForm.store_listing_id, () => { addForm.product_variant_id = ''; });
watch(() => props.cart.items, (items) => {
    for (const id of Object.keys(quantities)) {
        if (!items.some((item) => item.id === id)) delete quantities[id];
    }
    for (const item of items) quantities[item.id] = item.quantity;
});

const money = (amount: string | null) => amount === null ? 'Unavailable' : `${props.cart.currency ?? ''} ${amount}`.trim();
const discountMoney = (amount: string | null) => amount === null ? 'Unavailable' : `-${money(amount)}`;
const addItem = () => addForm.post(`/admin/carts/${props.cart.id}/items`, {
    preserveScroll: true,
    onSuccess: () => addForm.reset(),
});
const attachCustomer = () => customerForm.patch(`/admin/carts/${props.cart.id}/customer`, { preserveScroll: true });
const updateQuantity = (item: CartItem) => {
    updatingItem.value = item.id;
    router.patch(`/admin/carts/${props.cart.id}/items/${item.id}`, { quantity: quantities[item.id] }, {
        preserveScroll: true,
        onFinish: () => { updatingItem.value = null; },
    });
};
const removeItem = (item: CartItem) => {
    if (!window.confirm(`Remove ${item.product} / ${item.variant} from this cart?`)) return;
    deletingItem.value = item.id;
    router.delete(`/admin/carts/${props.cart.id}/items/${item.id}`, {
        preserveScroll: true,
        onFinish: () => { deletingItem.value = null; },
    });
};
const clearCart = () => {
    if (window.confirm('Remove every item from this cart?')) router.delete(`/admin/carts/${props.cart.id}/items`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Cart ${cart.token_hint}`" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ cart.store }} / {{ cart.status }}</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Cart {{ cart.token_hint }}</h1>
                    <p class="mt-3 text-sm text-[#637067]">{{ cart.customer ?? 'Guest cart' }}<template v-if="cart.expires_at"> / Expires {{ new Date(cart.expires_at).toLocaleString() }}</template></p>
                </div>
                <Link href="/admin/carts" class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Back to carts</Link>
            </div>

            <p v-if="page.props.status" class="mt-8 border-l-4 border-emerald-700 bg-white px-4 py-3 text-sm font-medium">{{ page.props.status }}</p>
            <div v-if="serverErrors.quantity || serverErrors.cart" class="mt-8 border-l-4 border-red-700 bg-white px-4 py-3"><FieldError :message="serverErrors.quantity ?? serverErrors.cart" /></div>
            <p v-if="!canManage" class="mt-8 border-l-4 border-stone-500 bg-white px-4 py-3 text-sm">This cart is read-only.</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5"><p class="text-xs text-[#637067] uppercase">Items</p><p class="mt-2 text-2xl font-semibold tabular-nums">{{ cart.item_count }}</p></article>
                <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5"><p class="text-xs text-[#637067] uppercase">Before discounts</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums">{{ money(cart.subtotal_before_discounts) }}</p></article>
                <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5"><p class="text-xs text-[#637067] uppercase">Discount</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums">-{{ money(cart.discount_total) }}</p></article>
                <article class="border p-5" :class="cart.valid ? 'border-emerald-700/30 bg-emerald-50' : 'border-red-800/30 bg-red-50'"><p class="text-xs uppercase" :class="cart.valid ? 'text-emerald-800' : 'text-red-800'">{{ cart.valid ? 'Valid subtotal' : 'Invalid subtotal' }}</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums">{{ money(cart.subtotal) }}</p></article>
            </div>

            <div v-if="canManage" class="mt-8 grid gap-6 lg:grid-cols-[2fr_1fr]">
                <form class="border border-[#17211b]/15 bg-white p-5 sm:p-6" @submit.prevent="addItem">
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Add item</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_7rem_auto] sm:items-start">
                        <div><label for="listing" class="mb-2 block text-sm font-semibold">Listing</label><select id="listing" v-model="addForm.store_listing_id" required class="w-full border border-[#17211b]/25 bg-white px-3 py-3"><option value="" disabled>Select listing</option><option v-for="listing in listings" :key="listing.id" :value="listing.id">{{ listing.product }}</option></select><FieldError :message="addForm.errors.store_listing_id" /></div>
                        <div><label for="variant" class="mb-2 block text-sm font-semibold">Variant</label><select id="variant" v-model="addForm.product_variant_id" :disabled="!selectedListing" required class="w-full border border-[#17211b]/25 bg-white px-3 py-3 disabled:bg-stone-100"><option value="" disabled>Select variant</option><option v-for="variant in selectedListing?.variants ?? []" :key="variant.id" :value="variant.id">{{ variant.name }} / {{ variant.sku }}</option></select><FieldError :message="addForm.errors.product_variant_id" /></div>
                        <div><label for="add_quantity" class="mb-2 block text-sm font-semibold">Quantity</label><input id="add_quantity" v-model.number="addForm.quantity" min="1" max="9999" required type="number" class="w-full border border-[#17211b]/25 px-3 py-3" /><FieldError :message="addForm.errors.quantity" /></div>
                        <button :disabled="addForm.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60 sm:mt-7">{{ addForm.processing ? 'Adding...' : 'Add' }}</button>
                    </div>
                </form>

                <form class="border border-[#17211b]/15 bg-white p-5 sm:p-6" @submit.prevent="attachCustomer">
                    <label for="customer" class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Customer</label>
                    <select id="customer" v-model="customerForm.customer_id" class="mt-4 w-full border border-[#17211b]/25 bg-white px-3 py-3"><option value="">Guest</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option></select>
                    <FieldError :message="customerForm.errors.customer_id" />
                    <button :disabled="customerForm.processing" class="mt-4 border border-[#17211b] px-4 py-2 text-sm font-semibold disabled:opacity-60">{{ customerForm.processing ? 'Saving...' : 'Update customer' }}</button>
                </form>
            </div>

            <section class="mt-10">
                <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Cart lines</p><h2 class="mt-3 text-3xl font-semibold tracking-[-0.03em]">Items</h2></div><button v-if="canManage && cart.items.length" type="button" class="border border-red-800 px-4 py-2 text-sm font-semibold text-red-800" @click="clearCart">Clear cart</button></div>

                <div class="mt-6 grid gap-4">
                    <article v-for="item in cart.items" :key="item.id" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6">
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] lg:items-start">
                            <div><h3 class="text-xl font-semibold">{{ item.product }}</h3><p class="mt-1 text-sm text-[#637067]">{{ item.variant }} / <span class="font-mono">{{ item.sku }}</span></p><p class="mt-3 text-sm">Available: <strong class="tabular-nums">{{ item.available }}</strong></p><div v-if="item.applied_promotions.length" class="mt-3 flex flex-wrap gap-2"><span v-for="promotion in item.applied_promotions" :key="promotion.id" class="bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-900">{{ promotion.name }}</span></div><ul v-if="item.issues.length" class="mt-4 space-y-1 text-sm font-medium text-red-800"><li v-for="issue in item.issues" :key="issue">{{ issue }}</li></ul></div>
                            <dl class="grid grid-cols-2 gap-x-5 gap-y-3 text-sm"><div><dt class="text-[#637067]">Base unit</dt><dd class="mt-1 font-mono tabular-nums">{{ money(item.base_unit_price) }}</dd></div><div><dt class="text-[#637067]">Final unit</dt><dd class="mt-1 font-mono tabular-nums">{{ money(item.final_unit_price) }}</dd></div><div><dt class="text-[#637067]">Base line</dt><dd class="mt-1 font-mono tabular-nums">{{ money(item.line_base_total) }}</dd></div><div><dt class="text-[#637067]">Line discount</dt><dd class="mt-1 font-mono tabular-nums">{{ discountMoney(item.line_discount) }}</dd></div><div><dt class="text-[#637067]">Line total</dt><dd class="mt-1 font-mono font-semibold tabular-nums">{{ money(item.line_total) }}</dd></div></dl>
                            <div v-if="canManage" class="flex flex-wrap gap-3 lg:w-48 lg:flex-col">
                                <form class="flex min-w-0 gap-2" @submit.prevent="updateQuantity(item)"><label :for="`quantity-${item.id}`" class="sr-only">Quantity for {{ item.product }}</label><input :id="`quantity-${item.id}`" v-model.number="quantities[item.id]" min="1" max="9999" required type="number" class="min-w-0 w-20 border border-[#17211b]/25 bg-white px-3 py-2" /><button :disabled="updatingItem === item.id" class="border border-[#17211b] px-3 py-2 text-sm font-semibold disabled:opacity-60">Update</button></form>
                                <button type="button" :disabled="deletingItem === item.id" class="w-fit text-sm font-semibold text-red-800 disabled:opacity-60" @click="removeItem(item)">{{ deletingItem === item.id ? 'Removing...' : 'Remove item' }}</button>
                            </div>
                        </div>
                    </article>
                    <p v-if="!cart.items.length" class="border border-dashed border-[#17211b]/25 p-8 text-center text-[#637067]">This cart is empty.</p>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>
