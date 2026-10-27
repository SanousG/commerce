<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';
import PromotionForm, { type Choice, type VariantChoice } from './PromotionForm.vue';

interface PriceListChoice { id: string; name: string; currency: string }
interface Promotion { id: string; name: string; internal_code: string | null; description: string | null; status: string; discount_type: string; discount_value: string; currency: string | null; starts_at: string | null; ends_at: string | null; priority: number; scope: string; is_currently_eligible: boolean }
interface Preview { base_amount: string; final_amount: string; total_discount: string; applied_promotions: { id: string; name: string; internal_code: string | null }[] }

const props = defineProps<{ promotion: Promotion; selected: { product_ids: string[]; category_ids: string[]; variant_ids: string[] }; statuses: string[]; discountTypes: string[]; scopes: string[]; currencies: string[]; products: Choice[]; categories: Choice[]; variants: VariantChoice[]; priceLists: PriceListChoice[]; targetSearch: string; preview: Preview | null; previewSelection: { variant_id: string; price_list_id: string } }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('promotions.update'));
const canArchive = computed(() => permissions.value.includes('promotions.archive'));
const isArchived = computed(() => props.promotion.status === 'ARCHIVED');
const canEdit = computed(() => canUpdate.value && !isArchived.value);
const form = useForm({ name: props.promotion.name, internal_code: props.promotion.internal_code ?? '', description: props.promotion.description ?? '', status: props.promotion.status, discount_type: props.promotion.discount_type, discount_value: props.promotion.discount_value, currency: props.promotion.currency ?? '', starts_at: props.promotion.starts_at ?? '', ends_at: props.promotion.ends_at ?? '', priority: props.promotion.priority, scope: props.promotion.scope, product_ids: [...props.selected.product_ids], category_ids: [...props.selected.category_ids], variant_ids: [...props.selected.variant_ids] });
const lifecycle = useForm({});
const previewVariant = ref(props.previewSelection.variant_id);
const previewPriceList = ref(props.previewSelection.price_list_id);
const targetSearch = ref(props.targetSearch);
const searching = ref(false);
const previewing = ref(false);
const { t } = useI18n();
const localizedStatus = computed(() => {
    const keys: Record<string, string> = { 'Promotion created.': 'promotions.notices.created', 'Promotion updated.': 'promotions.notices.updated' };
    return page.props.status ? t(keys[page.props.status] ?? 'promotions.notices.completed') : undefined;
});
const lifecycleError = computed(() => Object.values(lifecycle.errors)[0] ? t('promotions.errors.actionFailed') : undefined);
const update = () => { if (!form.processing && canEdit.value) form.put(`/admin/promotions/${props.promotion.id}`, { preserveScroll: true }); };
const loadPreview = () => router.get(`/admin/promotions/${props.promotion.id}/edit`, { preview_variant: previewVariant.value, preview_price_list: previewPriceList.value }, { preserveState: false, replace: true, preserveScroll: true, onStart: () => { previewing.value = true; }, onFinish: () => { previewing.value = false; } });
const searchTargets = () => router.get(`/admin/promotions/${props.promotion.id}/edit`, targetSearch.value.trim() ? { target_search: targetSearch.value.trim() } : {}, { preserveState: true, replace: true, preserveScroll: true, onStart: () => { searching.value = true; }, onFinish: () => { searching.value = false; } });
const clearTargets = () => { targetSearch.value = ''; searchTargets(); };
const archive = () => {
    if (lifecycle.processing || !window.confirm(t('promotions.archiveConfirm', { name: props.promotion.name }))) return;
    lifecycle.patch(`/admin/promotions/${props.promotion.id}/archive`);
};
</script>

<template>
    <Head :title="`${t('promotions.editPage.title')} · ${promotion.name}`" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-6xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.merchandising') }, { label: t('promotions.title'), href: '/admin/promotions' }, { label: promotion.name }]" />
            <PageHeader :title="promotion.name" :eyebrow="t('common.allCompany')" :description="t('promotions.editPage.description')"><template #actions><Link href="/admin/promotions" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50">{{ t('promotions.back') }}</Link></template></PageHeader>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-admin-neutral"><StatusBadge :status="promotion.status" /><span>{{ t('promotions.internalCode') }} <strong class="ml-1 font-mono font-medium text-admin-text-secondary">{{ promotion.internal_code ?? t('promotions.noCode') }}</strong></span><span :class="promotion.is_currently_eligible ? 'text-admin-success' : ''">{{ promotion.is_currently_eligible ? t('promotions.eligibleNow') : t('promotions.notEligibleNow') }}</span></div>
            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />
            <aside v-if="isArchived" class="flex gap-3 rounded-md border border-admin-border-strong bg-admin-canvas px-4 py-3" role="status"><div><p class="text-sm font-semibold text-admin-text">{{ t('promotions.editPage.archivedTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-neutral">{{ t('promotions.editPage.archivedHelp') }}</p></div></aside>
            <aside v-else-if="!canUpdate" class="flex gap-3 rounded-md border border-admin-border-strong bg-admin-canvas px-4 py-3" role="status"><div><p class="text-sm font-semibold text-admin-text">{{ t('promotions.editPage.readOnlyTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-neutral">{{ t('promotions.editPage.readOnlyHelp') }}</p></div></aside>
            <div v-if="lifecycleError" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3"><FieldError :message="lifecycleError" /></div>
            <section v-if="canArchive && !isArchived" class="admin-card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><p class="admin-label text-admin-secondary">{{ t('promotions.editPage.lifecycleSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('promotions.editPage.lifecycle') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('promotions.editPage.lifecycleHelp') }}</p></div><button type="button" :disabled="lifecycle.processing" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-4 text-sm font-semibold text-admin-danger hover:bg-admin-danger-bg disabled:opacity-60" @click="archive">{{ lifecycle.processing ? t('common.archiving') : t('common.archive') }}</button></section>
            <form v-if="canEdit" class="admin-card flex flex-col gap-3 p-3 sm:flex-row sm:items-end sm:p-4" role="search" @submit.prevent="searchTargets"><label for="promotion-target-search" class="grid min-w-0 flex-1 gap-1.5"><span class="admin-label">{{ t('promotions.targetSearch') }}</span><input id="promotion-target-search" v-model="targetSearch" type="search" autocomplete="off" :placeholder="t('promotions.targetSearchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label><div class="flex gap-2"><button type="submit" :disabled="searching" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60 sm:flex-none">{{ searching ? t('common.searching') : t('promotions.findTargets') }}</button><button v-if="props.targetSearch" type="button" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary sm:flex-none" @click="clearTargets">{{ t('common.clear') }}</button></div></form>
            <PromotionForm :form="form" :statuses="statuses" :discount-types="discountTypes" :scopes="scopes" :currencies="currencies" :products="products" :categories="categories" :variants="variants" :disabled="!canEdit" mode="edit" @submit="update" />

            <section class="admin-card overflow-hidden"><header class="border-b border-admin-border px-5 py-4 sm:px-6"><p class="admin-label text-admin-secondary">{{ t('promotions.editPage.previewSection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('promotions.editPage.preview') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('promotions.editPage.previewHelp') }}</p></header><div class="p-5 sm:p-6"><form class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end" @submit.prevent="loadPreview"><label class="grid gap-1.5" for="preview-variant"><span class="text-sm font-semibold text-admin-text">{{ t('promotions.editPage.variant') }}</span><select id="preview-variant" v-model="previewVariant" required class="admin-control min-w-0 px-3 text-sm"><option value="">{{ t('promotions.editPage.chooseVariant') }}</option><option v-for="variant in variants" :key="variant.id" :value="variant.id">{{ variant.product }} · {{ variant.sku }}</option></select></label><label class="grid gap-1.5" for="preview-price-list"><span class="text-sm font-semibold text-admin-text">{{ t('promotions.editPage.priceList') }}</span><select id="preview-price-list" v-model="previewPriceList" required class="admin-control min-w-0 px-3 text-sm"><option value="">{{ t('promotions.editPage.choosePriceList') }}</option><option v-for="list in priceLists" :key="list.id" :value="list.id">{{ list.name }} · {{ list.currency }}</option></select></label><button type="submit" :disabled="previewing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60">{{ previewing ? t('promotions.editPage.calculating') : t('promotions.editPage.calculate') }}</button></form>
                    <dl v-if="preview" class="mt-5 grid gap-4 rounded-md border border-admin-info-border bg-admin-info-bg p-4 sm:grid-cols-3"><div><dt class="admin-label">{{ t('promotions.editPage.base') }}</dt><dd class="mt-1 font-mono text-lg font-semibold tabular-nums">{{ preview.base_amount }}</dd></div><div><dt class="admin-label">{{ t('promotions.editPage.discount') }}</dt><dd class="mt-1 font-mono text-lg font-semibold tabular-nums">{{ preview.total_discount }}</dd></div><div><dt class="admin-label">{{ t('promotions.editPage.final') }}</dt><dd class="mt-1 font-mono text-lg font-semibold tabular-nums">{{ preview.final_amount }}</dd></div><div class="sm:col-span-3"><dt class="admin-label">{{ t('promotions.editPage.applied') }}</dt><dd class="mt-1 text-sm font-semibold">{{ preview.applied_promotions.map((item) => item.name).join(', ') || t('common.none') }}</dd></div></dl><p v-else-if="previewSelection.variant_id" class="mt-4 text-sm text-admin-neutral">{{ t('promotions.editPage.noBasePrice') }}</p></div></section>
        </section>
    </AdminLayout>
</template>
