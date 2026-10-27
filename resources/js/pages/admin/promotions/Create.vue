<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import PromotionForm, { type Choice, type VariantChoice } from './PromotionForm.vue';

const props = defineProps<{ statuses: string[]; discountTypes: string[]; scopes: string[]; currencies: string[]; products: Choice[]; categories: Choice[]; variants: VariantChoice[]; targetSearch: string }>();
const form = useForm({ name: '', internal_code: '', description: '', status: 'DRAFT', discount_type: 'PERCENTAGE', discount_value: '', currency: '', starts_at: '', ends_at: '', priority: 0, scope: 'ALL', product_ids: [] as string[], category_ids: [] as string[], variant_ids: [] as string[] });
const targetSearch = ref(props.targetSearch);
const searching = ref(false);
const { t } = useI18n();
const searchTargets = () => router.get('/admin/promotions/create', targetSearch.value.trim() ? { target_search: targetSearch.value.trim() } : {}, { preserveState: true, replace: true, preserveScroll: true, onStart: () => { searching.value = true; }, onFinish: () => { searching.value = false; } });
const clearTargets = () => { targetSearch.value = ''; searchTargets(); };
const submit = () => { if (!form.processing) form.post('/admin/promotions'); };
</script>

<template>
    <Head :title="t('promotions.createPage.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-6xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.merchandising') }, { label: t('promotions.title'), href: '/admin/promotions' }, { label: t('common.create') }]" />
            <PageHeader :title="t('promotions.createPage.title')" :eyebrow="t('common.allCompany')" :description="t('promotions.createPage.description')"><template #actions><Link href="/admin/promotions" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50">{{ t('promotions.back') }}</Link></template></PageHeader>
            <aside class="flex gap-3 rounded-lg border border-admin-info-border bg-admin-info-bg px-4 py-3" :aria-label="t('promotions.createPage.rulesTitle')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-info" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="M12 11v5m0-8h.01" /></svg><div><p class="text-xs font-semibold text-admin-text">{{ t('promotions.createPage.rulesTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('promotions.createPage.rulesHelp') }}</p></div></aside>
            <form class="admin-card flex flex-col gap-3 p-3 sm:flex-row sm:items-end sm:p-4" role="search" @submit.prevent="searchTargets"><label for="promotion-target-search" class="grid min-w-0 flex-1 gap-1.5"><span class="admin-label">{{ t('promotions.targetSearch') }}</span><input id="promotion-target-search" v-model="targetSearch" type="search" autocomplete="off" :placeholder="t('promotions.targetSearchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label><div class="flex gap-2"><button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-primary bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('promotions.findTargets') }}</button><button v-if="props.targetSearch" type="button" :disabled="searching" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary sm:flex-none" @click="clearTargets">{{ t('common.clear') }}</button></div></form>
            <PromotionForm :form="form" :statuses="statuses" :discount-types="discountTypes" :scopes="scopes" :currencies="currencies" :products="products" :categories="categories" :variants="variants" mode="create" @submit="submit"><template #cancel><Link href="/admin/promotions" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 sm:w-auto">{{ t('common.cancel') }}</Link></template></PromotionForm>
        </section>
    </AdminLayout>
</template>
