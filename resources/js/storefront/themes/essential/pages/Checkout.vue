<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicCheckout, PublicStore } from '../../../types';
import EssentialLayout from '../EssentialLayout.vue';

const props = defineProps<{ store: PublicStore; checkout: PublicCheckout }>();
const { t } = useI18n();
const serverError = ref<string | null>(null);
const billingDetails = ref(Boolean(props.checkout.buyer.billingAddressLine1));
const b = props.checkout.buyer;
const form = useForm({
    mutation_id: crypto.randomUUID(),
    customer_name: b.customerName ?? '', email: b.email ?? '', phone: b.phone ?? '',
    billing_recipient_name: b.billingRecipientName ?? '', billing_phone: b.billingPhone ?? '',
    billing_country_code: b.billingCountryCode ?? '', billing_region: b.billingRegion ?? '',
    billing_city: b.billingCity ?? '', billing_district: b.billingDistrict ?? '',
    billing_address_line_1: b.billingAddressLine1 ?? '', billing_address_line_2: b.billingAddressLine2 ?? '',
    billing_postal_code: b.billingPostalCode ?? '', billing_reference: b.billingReference ?? '',
});
const money = (amount: string | null) => new Intl.NumberFormat(props.store.locale, { style: 'currency', currency: props.checkout.currency ?? props.store.currency }).format(Number(amount ?? 0));
const fieldError = (name: string) => form.errors[name as keyof typeof form.errors];
const submit = () => {
    if (!billingDetails.value) {
        for (const key of ['billing_recipient_name', 'billing_phone', 'billing_country_code', 'billing_region', 'billing_city', 'billing_district', 'billing_address_line_1', 'billing_address_line_2', 'billing_postal_code', 'billing_reference'] as const) form[key] = '';
    }
    serverError.value = null;
    form.post(`/tienda/${props.store.publicHandle}/checkout/preparar`, {
        preserveScroll: true,
        onError: (errors) => { serverError.value = Object.values(errors)[0] ?? t('storefront.checkoutError'); },
        onFinish: () => { if (!form.hasErrors) form.mutation_id = crypto.randomUUID(); },
    });
};
const expires = computed(() => props.checkout.expiresAt ? new Intl.DateTimeFormat(props.store.locale, { hour: '2-digit', minute: '2-digit' }).format(new Date(props.checkout.expiresAt)) : null);
const commercialReady = computed(() => props.checkout.commercial.pickupConfigured && props.checkout.commercial.paymentMethods.includes('CASH'));
const pickup = computed(() => props.checkout.commercial.pickup);
const pickupAddress = computed(() => pickup.value ? [pickup.value.addressLine1, pickup.value.addressLine2, pickup.value.district, pickup.value.province, pickup.value.department].filter(Boolean).join(', ') : '');
</script>

<template>
    <Head :title="t('storefront.checkoutTitle')" />
    <EssentialLayout :store="store">
        <div class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex min-h-16 max-w-[1440px] flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <nav class="flex items-center gap-2 text-xs sm:text-sm" :aria-label="t('storefront.purchaseProgress')">
                    <Link :href="`/tienda/${store.publicHandle}/carrito`" class="text-slate-500">1. {{ t('storefront.cart') }}</Link><span>/</span>
                    <strong class="text-[var(--sf-accent)]">2. {{ t('storefront.orderInformation') }}</strong><span>/</span>
                    <span class="text-slate-400">3. {{ t('storefront.confirmation') }}</span>
                </nav>
                <Link :href="`/tienda/${store.publicHandle}/carrito`" class="text-xs font-semibold">← {{ t('storefront.backToCart') }}</Link>
            </div>
        </div>
        <div class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 sm:py-10 lg:px-8">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ t('storefront.checkoutTitle') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('storefront.checkoutDescription') }}</p>
            <p v-if="serverError" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ serverError }}</p>

            <div v-if="checkout.ready" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5" role="status">
                <h2 class="font-bold text-emerald-950">{{ t('storefront.checkoutPreparedTitle') }}</h2>
                <p class="mt-1 text-sm leading-6 text-emerald-900">{{ t('storefront.checkoutPreparedDescription', { time: expires }) }}</p>
            </div>

            <div class="mt-6 grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
                <form class="space-y-6 lg:col-span-8" @submit.prevent="submit">
                    <fieldset :disabled="checkout.ready || form.processing" class="space-y-6 disabled:opacity-75">
                        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 class="border-b border-slate-100 pb-4 text-base font-bold">1. {{ t('storefront.buyerContact') }}</h2>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <label class="sm:col-span-2"><span class="field-label">{{ t('storefront.fullName') }} *</span><input v-model="form.customer_name" class="field-input" autocomplete="name" /><span v-if="fieldError('customer_name')" class="field-error">{{ fieldError('customer_name') }}</span></label>
                                <label><span class="field-label">{{ t('storefront.email') }}</span><input v-model="form.email" type="email" class="field-input" autocomplete="email" /><span v-if="fieldError('email')" class="field-error">{{ fieldError('email') }}</span></label>
                                <label><span class="field-label">{{ t('storefront.phone') }}</span><input v-model="form.phone" type="tel" class="field-input" autocomplete="tel" /><span v-if="fieldError('phone')" class="field-error">{{ fieldError('phone') }}</span></label>
                            </div>
                        </section>

                        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 class="border-b border-slate-100 pb-4 text-base font-bold">2. {{ t('storefront.storePickup') }}</h2>
                            <div v-if="pickup" class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                                <p class="font-bold">{{ pickup.name }}</p>
                                <p class="mt-1 leading-6">{{ pickupAddress }}</p>
                                <p v-if="pickup.postalCode" class="mt-1">{{ pickup.postalCode }} · {{ pickup.countryCode }}</p>
                                <p v-if="pickup.instructions" class="mt-3 border-t border-emerald-200 pt-3 leading-6">{{ pickup.instructions }}</p>
                                <p class="mt-3 border-t border-emerald-200 pt-3 text-xs font-semibold">{{ t('storefront.pickupNoShippingCharge') }}</p>
                            </div>
                            <p v-else class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ t('storefront.pickupUnavailable') }}</p>
                            <label class="mt-5 flex items-start gap-3 border-t border-slate-100 pt-5 text-sm"><input v-model="billingDetails" type="checkbox" class="mt-1" />{{ t('storefront.addBillingDetails') }}</label>
                            <div v-if="billingDetails" class="mt-4 grid gap-4 rounded-lg bg-slate-50 p-4 sm:grid-cols-2">
                                <h3 class="font-bold sm:col-span-2">{{ t('storefront.billingAddress') }}</h3>
                                <label><span class="field-label">{{ t('storefront.recipient') }}</span><input v-model="form.billing_recipient_name" class="field-input" /></label>
                                <label><span class="field-label">{{ t('storefront.phone') }}</span><input v-model="form.billing_phone" class="field-input" /></label>
                                <label><span class="field-label">{{ t('storefront.countryCode') }}</span><input v-model="form.billing_country_code" maxlength="2" class="field-input uppercase" /></label>
                                <label><span class="field-label">{{ t('storefront.region') }}</span><input v-model="form.billing_region" class="field-input" /></label>
                                <label><span class="field-label">{{ t('storefront.city') }}</span><input v-model="form.billing_city" class="field-input" /></label>
                                <label><span class="field-label">{{ t('storefront.district') }}</span><input v-model="form.billing_district" class="field-input" /></label>
                                <label class="sm:col-span-2"><span class="field-label">{{ t('storefront.addressLine1') }}</span><input v-model="form.billing_address_line_1" class="field-input" /></label>
                                <label><span class="field-label">{{ t('storefront.addressLine2') }}</span><input v-model="form.billing_address_line_2" class="field-input" /></label>
                                <label><span class="field-label">{{ t('storefront.postalCode') }}</span><input v-model="form.billing_postal_code" class="field-input" /></label>
                            </div>
                        </section>
                    </fieldset>

                    <section class="rounded-xl border p-5" :class="commercialReady ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                        <h2 class="font-bold" :class="commercialReady ? 'text-emerald-950' : 'text-amber-950'">3. {{ t('storefront.pickupPaymentTitle') }}</h2>
                        <p class="mt-2 text-sm leading-6" :class="commercialReady ? 'text-emerald-900' : 'text-amber-900'">{{ commercialReady ? t('storefront.cashOnPickupDescription') : t('storefront.pickupPaymentUnavailable') }}</p>
                        <p class="mt-2 text-sm leading-6" :class="commercialReady ? 'text-emerald-900' : 'text-amber-900'">{{ t('storefront.noDepositNotice') }}</p>
                        <p class="mt-2 text-sm leading-6" :class="commercialReady ? 'text-emerald-900' : 'text-amber-900'">{{ t('storefront.orderCreationStillBlocked') }}</p>
                    </section>
                    <button v-if="!checkout.ready" type="submit" class="min-h-12 w-full rounded-lg bg-[var(--sf-primary)] px-5 text-sm font-semibold text-white disabled:opacity-60" :disabled="form.processing || !commercialReady">{{ form.processing ? t('storefront.preparingCheckout') : t('storefront.prepareCheckout') }}</button>
                    <p class="text-center text-xs text-slate-500">{{ t('storefront.prepareNotOrder') }}</p>
                </form>

                <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-28 lg:col-span-4 lg:p-6">
                    <h2 class="border-b border-slate-100 pb-4 text-lg font-bold">{{ t('storefront.cartSummary') }}</h2>
                    <ul class="divide-y divide-slate-100">
                        <li v-for="item in checkout.items" :key="`${item.productName}-${item.variantName}`" class="flex justify-between gap-4 py-4 text-sm">
                            <div><strong>{{ item.productName }}</strong><p class="mt-1 text-xs text-slate-500">{{ item.variantName }} · {{ item.quantity }} × {{ money(item.finalUnitPrice) }}</p></div><strong class="shrink-0">{{ money(item.lineTotal) }}</strong>
                        </li>
                    </ul>
                    <div class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                        <div class="flex justify-between"><span>{{ t('storefront.subtotalItems', { count: checkout.itemCount }) }}</span><strong>{{ money(checkout.totals.subtotalBeforeDiscounts) }}</strong></div>
                        <div v-if="Number(checkout.totals.discountTotal)" class="flex justify-between text-emerald-700"><span>{{ t('storefront.discount') }}</span><strong>−{{ money(checkout.totals.discountTotal) }}</strong></div>
                        <div class="flex justify-between"><span>{{ t('storefront.includedTaxes') }}</span><strong>{{ money(checkout.totals.taxTotal) }}</strong></div>
                        <div class="flex justify-between border-t pt-3 text-base"><strong>{{ t('storefront.estimatedTotal') }}</strong><strong>{{ money(checkout.totals.grandTotal) }}</strong></div>
                    </div>
                </aside>
            </div>
        </div>
    </EssentialLayout>
</template>

<style scoped>
.field-label { display: block; margin-bottom: .375rem; font-size: .75rem; font-weight: 600; color: #334155; }
.field-input { min-height: 44px; width: 100%; border-radius: .5rem; border: 1px solid #cbd5e1; background: white; padding: .625rem .75rem; font-size: .875rem; outline: none; }
.field-input:focus { border-color: var(--sf-accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--sf-accent) 20%, transparent); }
.field-error { display: block; margin-top: .25rem; font-size: .75rem; color: #b91c1c; }
</style>
