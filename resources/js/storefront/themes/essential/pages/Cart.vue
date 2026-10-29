<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicCart, PublicCartItem, PublicStore } from '../../../types';
import EssentialLayout from '../EssentialLayout.vue';

const props = defineProps<{ store: PublicStore; cart: PublicCart }>();
const { t } = useI18n();
const processing = ref<string | null>(null);
const error = ref<string | null>(null);
const mutationId = () => crypto.randomUUID();

const money = (amount: string | null) => new Intl.NumberFormat(props.store.locale, {
    style: 'currency',
    currency: props.cart.currency ?? props.store.currency,
}).format(Number(amount ?? 0));

const mutationOptions = (key: string) => ({
    preserveScroll: true,
    onStart: () => { processing.value = key; error.value = null; },
    onFinish: () => { processing.value = null; },
    onError: (errors: Record<string, string>) => { error.value = Object.values(errors)[0] ?? t('storefront.cartUpdateError'); },
});

const updateQuantity = (item: PublicCartItem, quantity: number) => {
    if (quantity < 1 || quantity > props.cart.maxQuantity || processing.value) return;
    router.patch(`/tienda/${props.store.publicHandle}/carrito/articulos/${item.key}`, {
        quantity,
        mutation_id: mutationId(),
    }, mutationOptions(item.key));
};

const removeItem = (item: PublicCartItem) => {
    if (processing.value) return;
    router.delete(`/tienda/${props.store.publicHandle}/carrito/articulos/${item.key}`, {
        data: { mutation_id: mutationId() },
        ...mutationOptions(item.key),
    });
};

const clearCart = () => {
    if (processing.value || !window.confirm(t('storefront.clearCartConfirm'))) return;
    router.delete(`/tienda/${props.store.publicHandle}/carrito`, {
        data: { mutation_id: mutationId() },
        ...mutationOptions('clear'),
    });
};

const startCheckout = () => {
    if (processing.value || !props.cart.valid || props.cart.items.length === 0) return;
    router.post(`/tienda/${props.store.publicHandle}/checkout`, { mutation_id: mutationId() }, mutationOptions('checkout'));
};
</script>

<template>
    <Head :title="t('storefront.cartTitle')" />
    <EssentialLayout :store="store">
        <div class="mx-auto w-full max-w-[1440px] px-4 py-5 sm:px-6 sm:py-8 lg:px-8">
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500" :aria-label="t('common.breadcrumb')">
                <Link :href="`/tienda/${store.publicHandle}`">{{ t('storefront.home') }}</Link><span aria-hidden="true">/</span>
                <Link :href="`/tienda/${store.publicHandle}/productos`">{{ t('storefront.catalog') }}</Link><span aria-hidden="true">/</span>
                <span class="text-slate-900">{{ t('storefront.cart') }}</span>
            </nav>

            <div class="mt-3 flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 pb-5 sm:mt-6">
                <div><h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ t('storefront.cartTitle') }}</h1><p class="mt-1 text-sm text-slate-500">{{ t('storefront.cartDescription') }}</p></div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-600">{{ t('storefront.cartProductCount', { count: cart.lineCount }) }}</span>
            </div>

            <p v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ error }}</p>

            <section v-if="cart.items.length === 0" class="mx-auto my-12 max-w-2xl rounded-xl border border-slate-200 bg-white px-5 py-12 text-center sm:p-12">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-3xl text-slate-400" aria-hidden="true">▢</div>
                <h2 class="mt-5 text-lg font-bold">{{ t('storefront.emptyCartTitle') }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ t('storefront.emptyCartDescription') }}</p>
                <Link :href="`/tienda/${store.publicHandle}/productos`" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--sf-primary)] px-6 text-sm font-semibold text-white">{{ t('storefront.exploreCatalog') }}</Link>
            </section>

            <div v-else class="mt-6 grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
                <section class="space-y-4 lg:col-span-8" :aria-label="t('storefront.cartItems')">
                    <article v-for="item in cart.items" :key="item.key" class="rounded-xl border bg-white p-4 sm:p-6" :class="item.issues.length ? 'border-amber-300 ring-1 ring-amber-100' : 'border-slate-200'">
                        <div class="flex gap-3 sm:gap-5">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-slate-100 bg-slate-100 sm:h-28 sm:w-28">
                                <img v-if="item.image" :src="item.image.thumbnailUrl ?? item.image.mediumUrl" :alt="item.image.alt" class="h-full w-full object-cover" />
                                <span v-else class="flex h-full items-center justify-center text-xs text-slate-400">{{ t('storefront.imageUnavailable') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0"><p v-if="item.category" class="truncate text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ item.category }}</p><Link :href="item.productUrl" class="mt-0.5 block text-sm font-semibold leading-snug hover:text-[var(--sf-accent)] sm:text-base">{{ item.productName }}</Link></div>
                                    <button type="button" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-50 hover:text-red-600 disabled:opacity-50" :disabled="processing !== null" :aria-label="t('storefront.removeCartItem', { product: item.productName })" @click="removeItem(item)">×</button>
                                </div>
                                <p class="mt-1 text-xs text-slate-500"><span v-if="item.options.length">{{ item.options.map((option) => `${option.name}: ${option.value}`).join(' · ') }}</span><span v-else>{{ item.variantName }}</span></p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                            <div class="inline-flex items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50" role="group" :aria-label="t('storefront.quantityFor', { product: item.productName })">
                                <button type="button" class="flex h-11 w-11 items-center justify-center text-lg disabled:opacity-40" :disabled="processing !== null || item.quantity <= 1" @click="updateQuantity(item, item.quantity - 1)">−</button>
                                <span class="w-9 text-center text-sm font-semibold" aria-live="polite">{{ item.quantity }}</span>
                                <button type="button" class="flex h-11 w-11 items-center justify-center text-lg disabled:opacity-40" :disabled="processing !== null || item.quantity >= Math.min(item.available, cart.maxQuantity)" @click="updateQuantity(item, item.quantity + 1)">+</button>
                            </div>
                            <div class="text-right"><div class="text-[11px] text-slate-500">{{ t('storefront.unitPrice') }}: {{ money(item.finalUnitPrice) }}</div><div class="text-base font-bold">{{ money(item.lineTotal) }}</div></div>
                        </div>
                        <div v-if="item.issues.length" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900" role="alert">
                            <p v-for="issue in item.issues" :key="issue">{{ t(`storefront.cartIssues.${issue}`) }}</p>
                        </div>
                    </article>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <Link :href="`/tienda/${store.publicHandle}/productos`" class="inline-flex min-h-11 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold">← {{ t('storefront.continueShopping') }}</Link>
                        <button type="button" class="min-h-11 rounded-lg px-4 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50" :disabled="processing !== null" @click="clearCart">{{ t('storefront.clearCart') }}</button>
                    </div>
                </section>

                <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-28 lg:col-span-4 lg:p-6" :aria-label="t('storefront.cartSummary')">
                    <h2 class="border-b border-slate-100 pb-4 text-lg font-bold">{{ t('storefront.cartSummary') }}</h2>
                    <div class="space-y-3 border-b border-slate-100 py-5 text-sm">
                        <div class="flex justify-between gap-3 text-slate-600"><span>{{ t('storefront.subtotalItems', { count: cart.itemCount }) }}</span><strong class="text-slate-900">{{ money(cart.subtotalBeforeDiscounts) }}</strong></div>
                        <div v-if="Number(cart.discountTotal) > 0" class="flex justify-between gap-3 text-emerald-700"><span>{{ t('storefront.discount') }}</span><strong>−{{ money(cart.discountTotal) }}</strong></div>
                        <div class="flex justify-between gap-3 text-slate-600"><span>{{ t('storefront.includedTaxes') }}</span><strong class="text-slate-900">{{ money(cart.taxTotal) }}</strong></div>
                        <div class="flex items-baseline justify-between gap-3 pt-2"><span class="font-bold">{{ t('storefront.estimatedTotal') }}</span><strong class="text-xl">{{ money(cart.subtotal) }}</strong></div>
                    </div>
                    <p class="my-5 rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs leading-5 text-slate-700">{{ t('storefront.checkoutAvailabilityNotice') }}</p>
                    <button type="button" class="flex min-h-12 w-full items-center justify-center rounded-lg bg-[var(--sf-primary)] px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600" :disabled="processing !== null || !cart.valid" @click="startCheckout">{{ processing === 'checkout' ? t('storefront.cartProcessing') : t('storefront.goToCheckout') }}</button>
                </aside>
            </div>
        </div>
    </EssentialLayout>
</template>
