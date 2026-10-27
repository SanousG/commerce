<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Promotion { id: string; name: string; internal_code: string | null }
interface CartItem { id: string; listing_id: string; product: string; variant: string; sku: string; quantity: number; available: number; base_unit_price: string | null; final_unit_price: string | null; line_base_total: string | null; line_discount: string | null; line_total: string | null; tax_category_code: string | null; tax_rate: string | null; taxable_amount: string | null; exempt_amount: string | null; unaffected_amount: string | null; tax_amount: string | null; applied_promotions: Promotion[]; issues: string[] }
interface Cart { id: string; currency: string | null; item_count: number; subtotal_before_discounts: string; discount_total: string; subtotal: string; taxable_amount: string; exempt_amount: string; unaffected_amount: string; tax_total: string; valid: boolean; items: CartItem[]; store: string; customer_id: string | null; customer: string | null; status: string; token_hint: string; expires_at: string | null }
interface Customer { id: string; name: string }
interface ListingVariant { id: string; name: string; sku: string }
interface Listing { id: string; product: string; variants: ListingVariant[] }

const props = defineProps<{ cart: Cart; customers: Customer[]; listings: Listing[]; canManage: boolean }>();
const page = usePage<PageProps>();
const { t, te, locale } = useI18n();
const serverErrors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);
const addForm = useForm({ store_listing_id: '', product_variant_id: '', quantity: 1 });
const customerForm = useForm({ customer_id: props.cart.customer_id ?? '' });
const quantities = reactive<Record<string, number>>(Object.fromEntries(props.cart.items.map((item) => [item.id, item.quantity])));
const updatingItem = ref<string | null>(null);
const deletingItem = ref<string | null>(null);
const clearing = ref(false);
const selectedListing = computed(() => props.listings.find((listing) => listing.id === addForm.store_listing_id));
const localizedStatus = computed(() => {
    const keys: Record<string, string> = { 'Cart created.': 'carts.notices.created', 'Item added.': 'carts.notices.itemAdded', 'Quantity updated.': 'carts.notices.quantityUpdated', 'Item removed.': 'carts.notices.itemRemoved', 'Cart cleared.': 'carts.notices.cleared', 'Cart customer updated.': 'carts.notices.customerUpdated' };
    return page.props.status ? t(keys[page.props.status] ?? 'carts.notices.completed') : undefined;
});

watch(() => addForm.store_listing_id, () => { addForm.product_variant_id = ''; });
watch(() => props.cart.items, (items) => { for (const id of Object.keys(quantities)) if (!items.some((item) => item.id === id)) delete quantities[id]; for (const item of items) quantities[item.id] = item.quantity; });
const money = (amount: string | null) => amount === null ? t('common.unavailable') : `${props.cart.currency ?? ''} ${amount}`.trim();
const discountMoney = (amount: string | null) => amount === null ? t('common.unavailable') : `${props.cart.currency ?? ''} -${amount}`.trim();
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : t('carts.noExpiry');
const issueLabel = (issue: string) => te(`carts.issues.${issue}`) ? t(`carts.issues.${issue}`) : t('carts.issues.UNKNOWN');
const addItem = () => addForm.post(`/admin/carts/${props.cart.id}/items`, { preserveScroll: true, onSuccess: () => addForm.reset() });
const attachCustomer = () => customerForm.patch(`/admin/carts/${props.cart.id}/customer`, { preserveScroll: true });
const updateQuantity = (item: CartItem) => { updatingItem.value = item.id; router.patch(`/admin/carts/${props.cart.id}/items/${item.id}`, { quantity: quantities[item.id] }, { preserveScroll: true, onFinish: () => { updatingItem.value = null; } }); };
const removeItem = (item: CartItem) => { if (!window.confirm(t('carts.removeConfirm', { product: item.product, variant: item.variant }))) return; deletingItem.value = item.id; router.delete(`/admin/carts/${props.cart.id}/items/${item.id}`, { preserveScroll: true, onFinish: () => { deletingItem.value = null; } }); };
const clearCart = () => { if (!window.confirm(t('carts.clearConfirm'))) return; clearing.value = true; router.delete(`/admin/carts/${props.cart.id}/items`, { preserveScroll: true, onFinish: () => { clearing.value = false; } }); };
</script>

<template>
    <Head :title="t('carts.detailTitle', { token: cart.token_hint })" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('carts.title'), href: '/admin/carts' }, { label: cart.token_hint }]" />
            <PageHeader :title="t('carts.detailTitle', { token: cart.token_hint })" :eyebrow="t('carts.storeScope', { store: cart.store })" :description="t('carts.detailDescription')"><template #actions><Link href="/admin/carts" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 sm:w-auto">{{ t('carts.back') }}</Link></template></PageHeader>
            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />
            <aside v-if="serverErrors.quantity || serverErrors.cart" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3"><FieldError :message="serverErrors.quantity ?? serverErrors.cart" /></aside>
            <aside v-if="!canManage" class="rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3"><p class="text-sm font-semibold text-admin-text">{{ t('carts.readOnlyTitle') }}</p><p class="mt-0.5 text-xs text-admin-text-secondary">{{ t('carts.readOnlyHelp') }}</p></aside>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="admin-card p-4"><p class="admin-label">{{ t('carts.customer') }}</p><p class="mt-2 truncate text-sm font-semibold">{{ cart.customer ?? t('carts.guest') }}</p><p class="mt-2"><StatusBadge :status="cart.status" /></p></article>
                <article class="admin-card p-4"><p class="admin-label">{{ t('carts.items') }}</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums">{{ cart.item_count }}</p><p class="mt-1 text-xs text-admin-neutral">{{ t('carts.expiresAt', { date: formatDate(cart.expires_at) }) }}</p></article>
                <article class="admin-card p-4"><p class="admin-label">{{ t('carts.beforeDiscounts') }}</p><p class="mt-2 font-mono text-lg font-semibold tabular-nums">{{ money(cart.subtotal_before_discounts) }}</p><p class="mt-1 text-xs text-admin-neutral">{{ t('carts.discount') }}: <span class="font-mono">{{ discountMoney(cart.discount_total) }}</span></p></article>
                <article class="rounded-lg border p-4" :class="cart.valid ? 'border-admin-success-border bg-admin-success-bg' : 'border-admin-danger-border bg-admin-danger-bg'"><p class="admin-label" :class="cart.valid ? 'text-admin-success' : 'text-admin-danger'">{{ cart.valid ? t('carts.validSubtotal') : t('carts.invalidSubtotal') }}</p><p class="mt-2 font-mono text-lg font-semibold tabular-nums">{{ money(cart.subtotal) }}</p></article>
            </div>
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4"><article v-for="row in [[t('carts.taxableBase'), cart.taxable_amount], [t('carts.exempt'), cart.exempt_amount], [t('carts.unaffected'), cart.unaffected_amount], [t('carts.taxIncluded'), cart.tax_total]]" :key="row[0]" class="admin-card min-w-0 p-3 sm:p-4"><p class="admin-label truncate">{{ row[0] }}</p><p class="mt-2 truncate font-mono text-xs font-semibold tabular-nums sm:text-sm">{{ money(row[1]) }}</p></article></div>

            <section v-if="canManage" class="grid gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]">
                <form class="admin-card p-4 sm:p-5" @submit.prevent="addItem"><div><p class="admin-label text-admin-secondary">{{ t('carts.addSection') }}</p><h2 class="mt-1 text-base font-semibold">{{ t('carts.addItem') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('carts.addHelp') }}</p></div><div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_6rem_auto] xl:items-start"><label class="grid gap-1.5" for="listing"><span class="admin-label">{{ t('carts.listing') }}</span><select id="listing" v-model="addForm.store_listing_id" required class="admin-control w-full px-3 text-sm"><option value="" disabled>{{ t('carts.selectListing') }}</option><option v-for="listing in listings" :key="listing.id" :value="listing.id">{{ listing.product }}</option></select><FieldError :message="addForm.errors.store_listing_id" /></label><label class="grid gap-1.5" for="variant"><span class="admin-label">{{ t('carts.variant') }}</span><select id="variant" v-model="addForm.product_variant_id" :disabled="!selectedListing" required class="admin-control w-full px-3 text-sm disabled:bg-slate-100"><option value="" disabled>{{ t('carts.selectVariant') }}</option><option v-for="variant in selectedListing?.variants ?? []" :key="variant.id" :value="variant.id">{{ variant.name }} · {{ variant.sku }}</option></select><FieldError :message="addForm.errors.product_variant_id" /></label><label class="grid gap-1.5" for="add-quantity"><span class="admin-label">{{ t('carts.quantity') }}</span><input id="add-quantity" v-model.number="addForm.quantity" min="1" max="9999" required type="number" inputmode="numeric" class="admin-control w-full px-3 text-sm" /><FieldError :message="addForm.errors.quantity" /></label><button :disabled="addForm.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60 xl:mt-[1.375rem]">{{ addForm.processing ? t('carts.adding') : t('carts.add') }}</button></div></form>
                <form class="admin-card p-4 sm:p-5" @submit.prevent="attachCustomer"><label class="grid gap-1.5" for="customer"><span class="admin-label text-admin-secondary">{{ t('carts.customer') }}</span><span class="text-base font-semibold">{{ t('carts.customerAssignment') }}</span><select id="customer" v-model="customerForm.customer_id" class="admin-control mt-2 w-full px-3 text-sm"><option value="">{{ t('carts.guest') }}</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option></select><FieldError :message="customerForm.errors.customer_id" /></label><button :disabled="customerForm.processing" class="mt-3 min-h-9 w-full rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary disabled:opacity-60">{{ customerForm.processing ? t('common.saving') : t('carts.updateCustomer') }}</button></form>
            </section>

            <section><div class="mb-3 flex flex-wrap items-end justify-between gap-3"><div><p class="admin-label text-admin-secondary">{{ t('carts.linesSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('carts.items') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('carts.linesHelp') }}</p></div><button v-if="canManage && cart.items.length" type="button" :disabled="clearing" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-4 text-sm font-semibold text-admin-danger disabled:opacity-60" @click="clearCart">{{ clearing ? t('carts.clearing') : t('carts.clearCart') }}</button></div>
                <div v-if="cart.items.length" class="grid gap-3"><article v-for="item in cart.items" :key="item.id" class="admin-card p-4 sm:p-5"><div class="grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(18rem,1fr)_auto]"><div class="min-w-0"><h3 class="truncate text-sm font-semibold">{{ item.product }}</h3><p class="mt-1 truncate text-xs text-admin-text-secondary">{{ item.variant }} · <span class="font-mono">{{ item.sku }}</span></p><p class="mt-3 text-xs text-admin-neutral">{{ t('carts.available') }}: <strong class="font-mono tabular-nums text-admin-text">{{ item.available }}</strong></p><div v-if="item.applied_promotions.length" class="mt-3 flex flex-wrap gap-1.5"><span v-for="promotion in item.applied_promotions" :key="promotion.id" class="rounded border border-admin-success-border bg-admin-success-bg px-2 py-1 text-[10px] font-semibold text-admin-success">{{ promotion.name }}</span></div><ul v-if="item.issues.length" class="mt-3 grid gap-1.5" :aria-label="t('carts.issuesLabel')"><li v-for="issue in item.issues" :key="issue" class="rounded border border-admin-danger-border bg-admin-danger-bg px-2.5 py-1.5 text-xs font-medium text-admin-danger">{{ issueLabel(issue) }}</li></ul></div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-xs"><div><dt class="admin-label">{{ t('carts.baseUnit') }}</dt><dd class="mt-1 font-mono tabular-nums">{{ money(item.base_unit_price) }}</dd></div><div><dt class="admin-label">{{ t('carts.finalUnit') }}</dt><dd class="mt-1 font-mono tabular-nums">{{ money(item.final_unit_price) }}</dd></div><div><dt class="admin-label">{{ t('carts.baseLine') }}</dt><dd class="mt-1 font-mono tabular-nums">{{ money(item.line_base_total) }}</dd></div><div><dt class="admin-label">{{ t('carts.lineDiscount') }}</dt><dd class="mt-1 font-mono tabular-nums">{{ discountMoney(item.line_discount) }}</dd></div><div><dt class="admin-label">{{ t('carts.lineTotal') }}</dt><dd class="mt-1 font-mono font-semibold tabular-nums">{{ money(item.line_total) }}</dd></div><div><dt class="admin-label">{{ t('carts.tax') }}</dt><dd class="mt-1 font-mono tabular-nums">{{ item.tax_category_code ?? t('common.notConfigured') }}<template v-if="item.tax_rate"> · {{ item.tax_rate }}%</template><span class="block">{{ money(item.tax_amount) }}</span></dd></div></dl>
                    <div v-if="canManage" class="flex flex-wrap items-end gap-3 lg:w-44 lg:flex-col lg:items-stretch"><form class="flex min-w-0 flex-1 gap-2 lg:flex-none" @submit.prevent="updateQuantity(item)"><label :for="`quantity-${item.id}`" class="sr-only">{{ t('carts.quantityFor', { product: item.product }) }}</label><input :id="`quantity-${item.id}`" v-model.number="quantities[item.id]" min="1" max="9999" required type="number" inputmode="numeric" class="admin-control min-w-0 w-20 px-3 text-sm" /><button :disabled="updatingItem === item.id" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-3 text-xs font-semibold disabled:opacity-60">{{ updatingItem === item.id ? t('carts.updating') : t('carts.update') }}</button></form><button type="button" :disabled="deletingItem === item.id" class="min-h-9 rounded-md px-2 text-xs font-semibold text-admin-danger disabled:opacity-60" @click="removeItem(item)">{{ deletingItem === item.id ? t('carts.removing') : t('carts.removeItem') }}</button></div></div></article></div>
                <EmptyState v-else :title="t('carts.emptyCart')" :description="canManage ? t('carts.emptyCartHelp') : t('carts.emptyReadOnlyHelp')" />
            </section>
        </section>
    </AdminLayout>
</template>
