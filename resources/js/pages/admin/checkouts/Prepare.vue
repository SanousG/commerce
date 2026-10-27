<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface CartOption { id: string; token_hint: string; customer: string }
defineProps<{ carts: CartOption[] }>();
const form = useForm({ cart_id: '', idempotency_key: crypto.randomUUID() });
const page = usePage<PageProps>();
const { t } = useI18n();
const serverErrors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);
const submit = () => { if (!form.processing) form.post('/admin/checkouts'); };
const cartError = computed(() => form.errors.cart_id || serverErrors.value.cart ? t('checkout.errors.cart') : undefined);
</script>

<template>
    <Head :title="t('checkout.start')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-4xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('checkout.title'), href: '/admin/checkouts' }, { label: t('checkout.start') }]" />
            <PageHeader :title="t('checkout.start')" :eyebrow="t('common.currentStore')" :description="t('checkout.startDescription')"><template #actions><Link href="/admin/checkouts" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary">{{ t('checkout.back') }}</Link></template></PageHeader>
            <aside class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3"><p class="text-sm font-semibold text-admin-text">{{ t('checkout.pendingTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('checkout.pendingHelp') }}</p></aside>
            <EmptyState v-if="!carts.length" :title="t('checkout.noEligibleCarts')" :description="t('checkout.noEligibleCartsHelp')"><template #actions><Link href="/admin/carts" class="admin-link text-sm">{{ t('checkout.openCarts') }}</Link></template></EmptyState>
            <form v-else class="admin-card overflow-hidden" @submit.prevent="submit"><header class="border-b border-admin-border px-5 py-4 sm:px-6"><p class="admin-label text-admin-secondary">{{ t('checkout.cartSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('checkout.chooseCart') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('checkout.chooseCartHelp') }}</p></header><div class="p-5 sm:p-6"><label for="checkout-cart" class="mb-1.5 block text-sm font-semibold">{{ t('checkout.activeCart') }} <span class="text-admin-danger">*</span></label><select id="checkout-cart" v-model="form.cart_id" required class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(cartError)"><option value="" disabled>{{ t('checkout.selectCart') }}</option><option v-for="cart in carts" :key="cart.id" :value="cart.id">{{ cart.token_hint }} · {{ cart.customer }}</option></select><FieldError :message="cartError" /><FieldError :message="form.errors.idempotency_key ? t('checkout.errors.idempotency') : undefined" /></div><footer class="flex flex-col-reverse gap-2 border-t border-admin-border bg-admin-canvas px-5 py-4 sm:flex-row sm:justify-end sm:px-6"><Link href="/admin/checkouts" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary">{{ t('common.cancel') }}</Link><button type="submit" :disabled="form.processing" class="min-h-10 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? t('checkout.starting') : t('checkout.continue') }}</button></footer></form>
        </section>
    </AdminLayout>
</template>
