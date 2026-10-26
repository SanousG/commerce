<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import ProductCategorySelector from '../../../components/catalog/ProductCategorySelector.vue';
import FieldError from '../../../components/FieldError.vue';
import MediaPicker, { type PickerMedia } from '../../../components/media/MediaPicker.vue';
import MediaThumbnail from '../../../components/media/MediaThumbnail.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Choice { id: string; name: string }
interface CategoryChoice extends Choice { parent_id: string | null }
interface TaxCategoryChoice extends Choice { code: string }
interface OptionValue { id: string; value: string }
interface ProductOption { id: string; name: string; values: OptionValue[] }
interface ProductMedia { id: string; title: string | null; alt_text: string | null; default_alt_text: string | null; is_primary: boolean; position: number; thumbnail_url: string }
interface VariantMedia { id: string; title: string | null; alt_text: string | null; position: number; thumbnail_url: string }
interface Variant { id: string; name: string | null; sku: string; barcode: string | null; status: string; is_default: boolean; option_value_ids: string[]; display_options: string[]; default_price: string | null; media: VariantMedia[] }
interface Product { id: string; name: string; slug: string; description: string | null; status: string; tax_category_id: string | null; category_ids: string[]; options: ProductOption[]; variants: Variant[]; media: ProductMedia[] }
interface Pricing { price_list_id: string; name: string; currency: string; can_update: boolean }

const props = defineProps<{
    product: Product;
    pricing: Pricing | null;
    categories: CategoryChoice[];
    taxCategories: TaxCategoryChoice[];
    productStatuses: string[];
    variantStatuses: string[];
    mediaPermissions: { view: boolean; upload: boolean };
    mediaLimits: { max_image_mb: number };
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('products.update'));
const canManageTaxes = computed(() => permissions.value.includes('taxes.manage'));
const canCreateCategory = computed(() => permissions.value.includes('categories.create'));
const defaultVariant = computed(() => props.product.variants.find((variant) => variant.is_default) ?? null);
const { t } = useI18n();

const productForm = useForm({
    name: props.product.name,
    description: props.product.description ?? '',
    status: props.product.status,
    tax_category_id: props.product.tax_category_id,
    categories: [...props.product.category_ids],
});
const categoryError = computed(() => {
    const errors = productForm.errors as Record<string, string>;

    return errors.categories ?? Object.entries(errors).find(([key]) => key.startsWith('categories.'))?.[1];
});

const optionValuesText = ref('');
const optionForm = useForm({ name: '', values: [] as string[] });
const optionValuesError = computed(() => {
    const errors = optionForm.errors as Record<string, string>;

    return errors.values ?? Object.entries(errors).find(([key]) => key.startsWith('values.'))?.[1];
});

const variantForm = useForm({
    name: '',
    sku: '',
    barcode: '',
    status: 'ACTIVE',
    is_default: false,
    option_value_ids: [] as string[],
});
const variantOptionError = computed(() => {
    const errors = variantForm.errors as Record<string, string>;

    return errors.option_value_ids ?? Object.entries(errors).find(([key]) => key.startsWith('option_value_ids.'))?.[1];
});

const cloneVariant = (variant: Variant): Variant => ({
    ...variant,
    option_value_ids: [...variant.option_value_ids],
    display_options: [...variant.display_options],
    media: variant.media.map((asset) => ({ ...asset })),
});
const variantEdits = reactive(
    Object.fromEntries(props.product.variants.map((variant) => [variant.id, cloneVariant(variant)])) as Record<string, Variant>,
);
const variantUpdating = reactive<Record<string, boolean>>({});
const variantUpdateErrors = reactive<Record<string, Record<string, string>>>({});

const productMediaEdits = ref<ProductMedia[]>(props.product.media.map((asset) => ({ ...asset })));
const productMediaPickerOpen = ref(false);
const variantMediaPickerId = ref<string | null>(null);
const mediaAction = ref<string | null>(null);
const mediaError = ref('');
const mediaErrorContext = ref<string | null>(null);

const priceInputs = reactive(
    Object.fromEntries(props.product.variants.map((variant) => [variant.id, variant.default_price ?? ''])) as Record<string, string>,
);
const priceUpdating = reactive<Record<string, boolean>>({});
const priceErrors = reactive<Record<string, string>>({});

const firstError = (errors: Record<string, string>): string => Object.values(errors)[0] ?? t('products.operationFailed');
const variantError = (variantId: string, field: string): string | undefined => variantUpdateErrors[variantId]?.[field];
const variantOptionUpdateError = (variantId: string): string | undefined => {
    const errors = variantUpdateErrors[variantId] ?? {};

    return errors.option_value_ids ?? Object.entries(errors).find(([key]) => key.startsWith('option_value_ids.'))?.[1];
};

const updateProduct = () => productForm
    .transform((data) => canManageTaxes.value ? data : {
        name: data.name,
        description: data.description,
        status: data.status,
        categories: data.categories,
    })
    .put(`/admin/products/${props.product.id}`, { preserveScroll: true, preserveState: true });

const createOption = () => {
    optionForm.values = optionValuesText.value.split(',').map((value) => value.trim()).filter(Boolean);
    optionForm.post(`/admin/products/${props.product.id}/options`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            optionForm.reset();
            optionValuesText.value = '';
        },
    });
};

const addNewVariantsFromProps = () => {
    props.product.variants.forEach((variant) => {
        if (!variantEdits[variant.id]) variantEdits[variant.id] = cloneVariant(variant);
        if (!(variant.id in priceInputs)) priceInputs[variant.id] = variant.default_price ?? '';
    });
};
const syncDefaultVariantFlags = () => {
    props.product.variants.forEach((variant) => {
        if (variantEdits[variant.id]) variantEdits[variant.id].is_default = variant.is_default;
    });
};
const createVariant = () => variantForm.post(`/admin/products/${props.product.id}/variants`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
        variantForm.reset();
        addNewVariantsFromProps();
        syncDefaultVariantFlags();
    },
});

const syncVariantFromProps = (variantId: string) => {
    const serverVariant = props.product.variants.find((variant) => variant.id === variantId);
    if (serverVariant) {
        variantEdits[variantId] = cloneVariant(serverVariant);
        syncDefaultVariantFlags();
    }
};
const updateVariant = (variant: Variant) => {
    variantUpdating[variant.id] = true;
    variantUpdateErrors[variant.id] = {};
    router.put(`/admin/products/${props.product.id}/variants/${variant.id}`, {
        name: variant.name,
        sku: variant.sku,
        barcode: variant.barcode,
        status: variant.status,
        is_default: variant.is_default,
        option_value_ids: variant.option_value_ids,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => { variantUpdateErrors[variant.id] = errors; },
        onSuccess: () => syncVariantFromProps(variant.id),
        onFinish: () => { variantUpdating[variant.id] = false; },
    });
};

const syncProductMediaFromProps = () => {
    const localAssets = new Map(productMediaEdits.value.map((asset) => [asset.id, asset]));
    productMediaEdits.value = props.product.media.map((asset) => ({
        ...asset,
        alt_text: localAssets.get(asset.id)?.alt_text ?? asset.alt_text,
    }));
};
const beginMediaAction = (key: string, context: string) => {
    mediaAction.value = key;
    mediaError.value = '';
    mediaErrorContext.value = context;
};
const handleMediaError = (errors: Record<string, string>) => { mediaError.value = firstError(errors); };
const finishMediaAction = () => { mediaAction.value = null; };

const attachProductMedia = (asset: PickerMedia) => {
    beginMediaAction('product-attach', 'product');
    router.post(`/admin/products/${props.product.id}/media`, {
        media_asset_id: asset.id,
        is_primary: productMediaEdits.value.length === 0,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: syncProductMediaFromProps,
        onFinish: finishMediaAction,
    });
};
const detachProductMedia = (asset: ProductMedia) => {
    beginMediaAction(`product-detach-${asset.id}`, 'product');
    router.delete(`/admin/products/${props.product.id}/media/${asset.id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: syncProductMediaFromProps,
        onFinish: finishMediaAction,
    });
};
const saveProductMediaAlt = (asset: ProductMedia) => {
    beginMediaAction(`product-alt-${asset.id}`, 'product');
    router.post(`/admin/products/${props.product.id}/media`, {
        media_asset_id: asset.id,
        is_primary: asset.is_primary,
        alt_text: asset.alt_text,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: syncProductMediaFromProps,
        onFinish: finishMediaAction,
    });
};
const galleryOrder = () => productMediaEdits.value.map((asset) => asset.id);
const setPrimary = (id: string) => {
    beginMediaAction(`product-primary-${id}`, 'product');
    router.patch(`/admin/products/${props.product.id}/media-order`, {
        media_asset_ids: galleryOrder(),
        primary_media_asset_id: id,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: syncProductMediaFromProps,
        onFinish: finishMediaAction,
    });
};
const moveProductMedia = (index: number, direction: number) => {
    const ids = galleryOrder();
    const target = index + direction;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    beginMediaAction(`product-order-${ids[index]}`, 'product');
    router.patch(`/admin/products/${props.product.id}/media-order`, {
        media_asset_ids: ids,
        primary_media_asset_id: productMediaEdits.value.find((asset) => asset.is_primary)?.id ?? null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: syncProductMediaFromProps,
        onFinish: finishMediaAction,
    });
};

const syncVariantMediaFromProps = (variantId: string) => {
    const serverVariant = props.product.variants.find((variant) => variant.id === variantId);
    if (serverVariant && variantEdits[variantId]) {
        variantEdits[variantId].media = serverVariant.media.map((asset) => ({ ...asset }));
    }
};
const attachVariantMedia = (variant: Variant, asset: PickerMedia) => {
    beginMediaAction(`variant-attach-${variant.id}`, variant.id);
    router.post(`/admin/product-variants/${variant.id}/media`, { media_asset_id: asset.id }, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: () => syncVariantMediaFromProps(variant.id),
        onFinish: finishMediaAction,
    });
};
const detachVariantMedia = (variant: Variant, asset: VariantMedia) => {
    beginMediaAction(`variant-detach-${variant.id}-${asset.id}`, variant.id);
    router.delete(`/admin/product-variants/${variant.id}/media/${asset.id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: () => syncVariantMediaFromProps(variant.id),
        onFinish: finishMediaAction,
    });
};
const moveVariantMedia = (variant: Variant, index: number, direction: number) => {
    const ids = variant.media.map((asset) => asset.id);
    const target = index + direction;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    beginMediaAction(`variant-order-${variant.id}`, variant.id);
    router.patch(`/admin/product-variants/${variant.id}/media-order`, { media_asset_ids: ids }, {
        preserveScroll: true,
        preserveState: true,
        onError: handleMediaError,
        onSuccess: () => syncVariantMediaFromProps(variant.id),
        onFinish: finishMediaAction,
    });
};

const toggleProductMediaPicker = () => {
    productMediaPickerOpen.value = !productMediaPickerOpen.value;
    if (productMediaPickerOpen.value) variantMediaPickerId.value = null;
};
const toggleVariantMediaPicker = (variantId: string) => {
    variantMediaPickerId.value = variantMediaPickerId.value === variantId ? null : variantId;
    if (variantMediaPickerId.value !== null) productMediaPickerOpen.value = false;
};

const setPrice = (variant: Variant) => {
    if (!props.pricing) return;
    priceUpdating[variant.id] = true;
    priceErrors[variant.id] = '';
    router.put(`/admin/pricing/price-lists/${props.pricing.price_list_id}/variants/${variant.id}`, {
        amount: priceInputs[variant.id],
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => { priceErrors[variant.id] = errors.amount ?? firstError(errors); },
        onSuccess: () => {
            const serverVariant = props.product.variants.find((item) => item.id === variant.id);
            if (serverVariant) priceInputs[variant.id] = serverVariant.default_price ?? priceInputs[variant.id];
        },
        onFinish: () => { priceUpdating[variant.id] = false; },
    });
};
const removePrice = (variant: Variant) => {
    if (!props.pricing) return;
    priceUpdating[variant.id] = true;
    priceErrors[variant.id] = '';
    router.delete(`/admin/pricing/price-lists/${props.pricing.price_list_id}/variants/${variant.id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => { priceErrors[variant.id] = errors.amount ?? firstError(errors); },
        onSuccess: () => { priceInputs[variant.id] = ''; },
        onFinish: () => { priceUpdating[variant.id] = false; },
    });
};

const formatPrice = (amount: string | null) => {
    if (amount === null || !props.pricing) return t('products.notPriced');
    const [whole, fraction = ''] = amount.split('.');

    return `${props.pricing.currency} ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(4, '0')}`;
};
</script>

<template>
    <Head :title="`${t('common.edit')} ${product.name}`" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-7xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('products.title'), href: '/admin/products' }, { label: product.name }, { label: t('common.edit') }]" />

            <PageHeader :title="product.name" :eyebrow="t('common.allCompany')" :description="t('products.manageDescription')">
                <template #actions>
                    <Link href="/admin/products" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50">{{ t('products.backProducts') }}</Link>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-admin-neutral">
                <StatusBadge :status="product.status" />
                <span>{{ t('products.slug') }} <strong class="ml-1 font-mono font-medium text-admin-text-secondary">{{ product.slug }}</strong></span>
                <span>{{ t('products.defaultSku') }} <strong class="ml-1 font-mono font-medium text-admin-text-secondary">{{ defaultVariant?.sku ?? '—' }}</strong></span>
                <span><strong class="font-mono font-medium text-admin-text-secondary">{{ product.variants.length }}</strong> {{ t('products.variants') }}</span>
                <span v-if="mediaPermissions.view"><strong class="font-mono font-medium text-admin-text-secondary">{{ productMediaEdits.length }}</strong> {{ t('products.imagesCount', { count: productMediaEdits.length }) }}</span>
            </div>

            <StatusNotice :message="page.props.status" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <nav :aria-label="t('products.editSections')" class="admin-card flex flex-wrap gap-1 p-1.5 text-xs font-semibold text-admin-text-secondary">
                <a href="#product-information" class="rounded px-3 py-2 hover:bg-slate-50 hover:text-admin-secondary">{{ t('products.information') }}</a>
                <a href="#product-options" class="rounded px-3 py-2 hover:bg-slate-50 hover:text-admin-secondary">{{ t('products.options') }}</a>
                <a href="#product-variants" class="rounded px-3 py-2 hover:bg-slate-50 hover:text-admin-secondary">{{ t('products.variants') }} ({{ product.variants.length }})</a>
                <a v-if="mediaPermissions.view" href="#product-images" class="rounded px-3 py-2 hover:bg-slate-50 hover:text-admin-secondary">{{ t('products.images', { count: productMediaEdits.length }) }}</a>
                <a v-if="pricing" href="#variant-pricing" class="rounded px-3 py-2 hover:bg-slate-50 hover:text-admin-secondary">{{ t('products.defaultPricing') }}</a>
            </nav>

            <form id="product-information" class="admin-card scroll-mt-4 overflow-hidden" @submit.prevent="updateProduct">
                <header class="flex flex-col gap-2 border-b border-admin-border px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div>
                        <p class="admin-label text-admin-secondary">{{ t('products.sectionProduct') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('products.information') }}</h2>
                        <p class="mt-1 text-xs text-admin-neutral">{{ t('products.canonicalHelp') }}</p>
                    </div>
                    <span v-if="!canUpdate" class="rounded border border-admin-border bg-slate-50 px-2 py-1 text-xs font-medium text-admin-neutral">{{ t('common.readOnly') }}</span>
                </header>

                <fieldset :disabled="!canUpdate" class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
                    <div class="grid content-start gap-5">
                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3"><label for="product-name" class="text-sm font-semibold">{{ t('products.name') }} <span class="text-admin-danger">*</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ productForm.name.length }} / 160</span></div>
                            <input id="product-name" v-model="productForm.name" required maxlength="160" class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50" :class="productForm.errors.name ? 'border-admin-danger' : ''" :aria-invalid="productForm.errors.name ? 'true' : undefined" aria-describedby="product-name-error" />
                            <FieldError id="product-name-error" :message="productForm.errors.name" />
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3"><label for="product-description" class="text-sm font-semibold">{{ t('products.description') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ productForm.description.length }} / 10,000</span></div>
                            <textarea id="product-description" v-model="productForm.description" rows="7" maxlength="10000" class="admin-control block w-full resize-y px-3 py-2 text-sm leading-5 disabled:bg-slate-50" :class="productForm.errors.description ? 'border-admin-danger' : ''" :aria-invalid="productForm.errors.description ? 'true' : undefined" aria-describedby="product-description-error" />
                            <FieldError id="product-description-error" :message="productForm.errors.description" />
                        </div>

                        <label class="grid gap-1.5 text-sm font-semibold" for="product-status">{{ t('common.status') }}
                            <select id="product-status" v-model="productForm.status" class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50" :class="productForm.errors.status ? 'border-admin-danger' : ''">
                                <option v-for="status in productStatuses" :key="status" :value="status">{{ t(`status.${status}`) }}</option>
                            </select>
                            <FieldError :message="productForm.errors.status" />
                        </label>

                        <label v-if="taxCategories.length" class="grid gap-1.5 text-sm font-semibold" for="product-tax-category">{{ t('products.taxCategory') }}
                            <select id="product-tax-category" v-model="productForm.tax_category_id" :disabled="!canUpdate || !canManageTaxes" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-admin-neutral" :class="productForm.errors.tax_category_id ? 'border-admin-danger' : ''">
                                <option :value="null">{{ t('common.notConfigured') }}</option>
                                <option v-for="taxCategory in taxCategories" :key="taxCategory.id" :value="taxCategory.id">{{ taxCategory.name }} / {{ taxCategory.code }}</option>
                            </select>
                            <span v-if="!canManageTaxes" class="text-xs font-normal text-admin-neutral">{{ t('products.taxPermission') }}</span>
                            <FieldError :message="productForm.errors.tax_category_id" />
                        </label>
                    </div>

                    <div class="min-w-0 rounded-md border border-admin-border bg-admin-canvas p-4 sm:p-5">
                        <ProductCategorySelector v-model="productForm.categories" :categories="categories" :error="categoryError" :disabled="!canUpdate" :can-create-category="canCreateCategory" />
                    </div>
                </fieldset>

                <footer v-if="canUpdate" class="flex justify-end border-t border-admin-border bg-admin-canvas px-5 py-4 sm:px-6">
                    <button :disabled="productForm.processing" class="inline-flex min-h-10 w-full items-center justify-center rounded-md bg-admin-primary px-5 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                        {{ productForm.processing ? t('common.saving') : t('products.saveProduct') }}
                    </button>
                </footer>
            </form>

            <section id="product-options" class="admin-card scroll-mt-4 overflow-hidden">
                <header class="border-b border-admin-border px-5 py-4 sm:px-6">
                    <p class="admin-label text-admin-secondary">{{ t('products.configuration') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('products.productOptions') }}</h2>
                    <p class="mt-1 text-xs text-admin-neutral">{{ t('products.optionsHelp') }}</p>
                </header>

                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold text-admin-text">{{ t('products.configuredOptions') }}</h3>
                        <div v-if="product.options.length" class="mt-3 grid gap-2">
                            <article v-for="option in product.options" :key="option.id" class="rounded-md border border-admin-border bg-admin-canvas p-3">
                                <div class="flex items-center justify-between gap-3"><h4 class="text-sm font-semibold text-admin-text">{{ option.name }}</h4><span class="font-mono text-[10px] text-admin-neutral">{{ t('products.valuesCount', { count: option.values.length }) }}</span></div>
                                <div class="mt-2 flex flex-wrap gap-1.5"><span v-for="value in option.values" :key="value.id" class="rounded border border-admin-border bg-white px-2 py-1 text-xs text-admin-text-secondary">{{ value.value }}</span></div>
                            </article>
                        </div>
                        <p v-else class="mt-3 rounded-md border border-dashed border-admin-border-strong bg-admin-canvas px-4 py-5 text-sm text-admin-neutral">{{ t('products.noOptions') }}</p>
                    </div>

                    <form v-if="canUpdate" class="rounded-md border border-admin-border bg-admin-canvas p-4" @submit.prevent="createOption">
                        <h3 class="text-sm font-semibold text-admin-text">{{ t('products.addOption') }}</h3>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('products.addOptionHelp') }}</p>
                        <div class="mt-4 grid gap-4">
                            <div><label for="option-name" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('products.optionName') }}</label><input id="option-name" v-model="optionForm.name" required maxlength="80" :placeholder="t('products.optionNameExample')" class="admin-control w-full px-3 py-2 text-sm" :class="optionForm.errors.name ? 'border-admin-danger' : ''" /><FieldError :message="optionForm.errors.name" /></div>
                            <div><label for="option-values" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('products.values') }}</label><input id="option-values" v-model="optionValuesText" required :placeholder="t('products.valuesExample')" class="admin-control w-full px-3 py-2 text-sm" :class="optionValuesError ? 'border-admin-danger' : ''" /><p class="mt-1.5 text-xs text-admin-neutral">{{ t('products.valuesHelp') }}</p><FieldError :message="optionValuesError" /></div>
                            <button :disabled="optionForm.processing" class="min-h-9 w-full rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-fit">{{ optionForm.processing ? t('products.adding') : t('products.addOption') }}</button>
                        </div>
                    </form>
                </div>
            </section>

            <section id="product-variants" class="grid scroll-mt-4 gap-4">
                <header class="admin-card flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div><p class="admin-label text-admin-secondary">{{ t('products.sellableUnits') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('products.productVariants') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('products.variantsHelp') }}</p></div>
                    <span class="font-mono text-xs text-admin-neutral">{{ t('products.configured', { count: product.variants.length }) }}</span>
                </header>

                <form v-if="canUpdate" class="admin-card overflow-hidden" @submit.prevent="createVariant">
                    <header class="border-b border-admin-border bg-admin-canvas px-5 py-3 sm:px-6"><h3 class="text-sm font-semibold text-admin-text">{{ t('products.createVariant') }}</h3></header>
                    <div class="grid gap-5 p-5 sm:p-6">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div><label for="variant-name" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('products.displayName') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="variant-name" v-model="variantForm.name" maxlength="160" class="admin-control w-full px-3 py-2 text-sm" :class="variantForm.errors.name ? 'border-admin-danger' : ''" /><FieldError :message="variantForm.errors.name" /></div>
                            <div><label for="variant-sku" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">SKU <span class="text-admin-danger">*</span></label><input id="variant-sku" v-model="variantForm.sku" required maxlength="120" spellcheck="false" class="admin-control w-full px-3 py-2 font-mono text-sm" :class="variantForm.errors.sku ? 'border-admin-danger' : ''" /><FieldError :message="variantForm.errors.sku" /></div>
                            <div><label for="variant-barcode" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('products.barcode') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><input id="variant-barcode" v-model="variantForm.barcode" maxlength="120" spellcheck="false" class="admin-control w-full px-3 py-2 font-mono text-sm" :class="variantForm.errors.barcode ? 'border-admin-danger' : ''" /><FieldError :message="variantForm.errors.barcode" /></div>
                            <div><label for="variant-status" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('common.status') }}</label><select id="variant-status" v-model="variantForm.status" class="admin-control w-full px-3 py-2 text-sm" :class="variantForm.errors.status ? 'border-admin-danger' : ''"><option v-for="status in variantStatuses" :key="status" :value="status">{{ t(`status.${status}`) }}</option></select><FieldError :message="variantForm.errors.status" /></div>
                        </div>

                        <div v-if="product.options.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <fieldset v-for="option in product.options" :key="option.id" class="rounded-md border border-admin-border bg-admin-canvas p-3">
                                <legend class="px-1 text-xs font-semibold text-admin-text-secondary">{{ option.name }}</legend>
                                <div class="mt-1 flex flex-wrap gap-2"><label v-for="value in option.values" :key="value.id" class="flex min-h-8 items-center gap-2 rounded border border-admin-border bg-white px-2 text-xs"><input v-model="variantForm.option_value_ids" type="checkbox" :value="value.id" class="accent-admin-secondary" />{{ value.value }}</label></div>
                            </fieldset>
                        </div>
                        <FieldError :message="variantOptionError" />

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label class="flex min-h-9 items-center gap-2 text-sm font-medium"><input v-model="variantForm.is_default" type="checkbox" class="size-4 accent-admin-secondary" />{{ t('products.makeDefault') }}</label>
                            <button :disabled="variantForm.processing" class="min-h-9 w-full rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">{{ variantForm.processing ? t('common.creating') : t('products.createVariant') }}</button>
                        </div>
                        <FieldError :message="variantForm.errors.is_default" />
                    </div>
                </form>

                <p v-if="!Object.keys(variantEdits).length" class="admin-card border-dashed px-5 py-8 text-center text-sm text-admin-neutral">{{ t('products.noVariants') }}</p>

                <form v-for="variant in variantEdits" :key="variant.id" class="admin-card overflow-hidden" @submit.prevent="updateVariant(variant)">
                    <header class="flex flex-col gap-3 border-b border-admin-border bg-admin-canvas px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="truncate text-sm font-semibold text-admin-text">{{ variant.name || t('products.unnamedVariant') }}</h3><StatusBadge :status="variant.status" /><span v-if="variant.is_default" class="rounded border border-admin-secondary bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-medium text-admin-secondary">{{ t('products.default') }}</span></div><p class="mt-1 truncate font-mono text-[11px] text-admin-neutral">{{ variant.sku }}</p></div>
                        <button v-if="canUpdate" :disabled="variantUpdating[variant.id]" class="min-h-9 w-full rounded-md bg-admin-primary px-4 text-xs font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">{{ variantUpdating[variant.id] ? t('common.saving') : t('products.saveVariant') }}</button>
                    </header>

                    <fieldset :disabled="!canUpdate" class="grid gap-5 p-5 sm:p-6">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div><label :for="`variant-name-${variant.id}`" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('products.displayName') }}</label><input :id="`variant-name-${variant.id}`" v-model="variant.name" maxlength="160" class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50" :class="variantError(variant.id, 'name') ? 'border-admin-danger' : ''" /><FieldError :message="variantError(variant.id, 'name')" /></div>
                            <div><label :for="`variant-sku-${variant.id}`" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">SKU <span class="text-admin-danger">*</span></label><input :id="`variant-sku-${variant.id}`" v-model="variant.sku" required maxlength="120" spellcheck="false" class="admin-control w-full px-3 py-2 font-mono text-sm disabled:bg-slate-50" :class="variantError(variant.id, 'sku') ? 'border-admin-danger' : ''" /><FieldError :message="variantError(variant.id, 'sku')" /></div>
                            <div><label :for="`variant-barcode-${variant.id}`" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('products.barcode') }}</label><input :id="`variant-barcode-${variant.id}`" v-model="variant.barcode" maxlength="120" spellcheck="false" class="admin-control w-full px-3 py-2 font-mono text-sm disabled:bg-slate-50" :class="variantError(variant.id, 'barcode') ? 'border-admin-danger' : ''" /><FieldError :message="variantError(variant.id, 'barcode')" /></div>
                            <div><label :for="`variant-status-${variant.id}`" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('common.status') }}</label><select :id="`variant-status-${variant.id}`" v-model="variant.status" class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50" :class="variantError(variant.id, 'status') ? 'border-admin-danger' : ''"><option v-for="status in variantStatuses" :key="status" :value="status">{{ t(`status.${status}`) }}</option></select><FieldError :message="variantError(variant.id, 'status')" /></div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3"><label class="flex min-h-9 items-center gap-2 text-sm font-medium"><input v-model="variant.is_default" type="checkbox" class="size-4 accent-admin-secondary" />{{ t('products.makeDefault') }}</label><span v-if="variant.display_options.length" class="text-xs text-admin-neutral">{{ t('products.currentValues', { values: variant.display_options.join(' / ') }) }}</span></div>
                        <FieldError :message="variantError(variant.id, 'is_default')" />

                        <div v-if="product.options.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <fieldset v-for="option in product.options" :key="option.id" class="rounded-md border border-admin-border bg-admin-canvas p-3">
                                <legend class="px-1 text-xs font-semibold text-admin-text-secondary">{{ option.name }}</legend>
                                <div class="mt-1 flex flex-wrap gap-2"><label v-for="value in option.values" :key="value.id" class="flex min-h-8 items-center gap-2 rounded border border-admin-border bg-white px-2 text-xs"><input v-model="variant.option_value_ids" type="checkbox" :value="value.id" class="accent-admin-secondary" />{{ value.value }}</label></div>
                            </fieldset>
                        </div>
                        <FieldError :message="variantOptionUpdateError(variant.id)" />
                    </fieldset>

                    <div v-if="mediaPermissions.view" class="border-t border-admin-border px-5 py-4 sm:px-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="text-sm font-semibold text-admin-text">{{ t('products.variantImages') }}</h4><p class="mt-0.5 text-xs text-admin-neutral">{{ t('products.variantImagesHelp') }}</p></div><button v-if="canUpdate" type="button" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-3 text-xs font-semibold text-admin-text-secondary hover:bg-slate-50" @click="toggleVariantMediaPicker(variant.id)">{{ variantMediaPickerId === variant.id ? t('products.closeLibrary') : t('products.attachImage') }}</button></div>
                        <p v-if="mediaError && mediaErrorContext === variant.id" role="alert" class="mt-3 rounded-md border border-admin-danger-border bg-admin-danger-bg px-3 py-2 text-sm text-admin-danger">{{ mediaError }}</p>
                        <p v-else-if="mediaAction && mediaErrorContext === variant.id" role="status" class="mt-3 text-xs text-admin-neutral">{{ t('products.updatingVariantGallery') }}</p>
                        <div v-if="variant.media.length" class="mt-3 flex flex-wrap gap-3">
                            <article v-for="(asset, mediaIndex) in variant.media" :key="asset.id" class="rounded-md border border-admin-border bg-admin-canvas p-2">
                                <MediaThumbnail :src="asset.thumbnail_url" :alt="asset.alt_text" />
                                <div v-if="canUpdate" class="mt-2 flex items-center justify-center gap-1"><button type="button" :disabled="mediaIndex === 0 || mediaAction !== null" class="grid size-8 place-items-center rounded border border-admin-border bg-white text-admin-text-secondary disabled:opacity-30" :aria-label="t('products.moveLeft', { name: asset.title || t('products.image') })" @click="moveVariantMedia(variant, mediaIndex, -1)">←</button><button type="button" :disabled="mediaIndex === variant.media.length - 1 || mediaAction !== null" class="grid size-8 place-items-center rounded border border-admin-border bg-white text-admin-text-secondary disabled:opacity-30" :aria-label="t('products.moveRight', { name: asset.title || t('products.image') })" @click="moveVariantMedia(variant, mediaIndex, 1)">→</button><button type="button" :disabled="mediaAction !== null" class="grid size-8 place-items-center rounded border border-admin-danger-border bg-white text-admin-danger disabled:opacity-30" :aria-label="t('products.detachImage', { name: asset.title || t('products.image') })" @click="detachVariantMedia(variant, asset)">×</button></div>
                            </article>
                        </div>
                        <p v-else class="mt-3 text-xs text-admin-neutral">{{ t('products.noVariantImages') }}</p>
                        <div v-if="variantMediaPickerId === variant.id" class="mt-4 rounded-md border border-admin-border bg-admin-canvas p-3 sm:p-4"><MediaPicker :selected-ids="variant.media.map((asset) => asset.id)" :allow-upload="false" :disabled="mediaAction !== null" :max-image-mb="mediaLimits.max_image_mb" @select="attachVariantMedia(variant, $event)" /></div>
                    </div>
                </form>
            </section>

            <section v-if="mediaPermissions.view" id="product-images" class="admin-card scroll-mt-4 overflow-hidden">
                <header class="flex flex-col gap-2 border-b border-admin-border px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div><p class="admin-label text-admin-secondary">{{ t('products.mediaSection') }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('products.productImages') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('products.productImagesHelp') }}</p></div>
                    <button v-if="canUpdate" type="button" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-4 text-xs font-semibold text-admin-text-secondary hover:bg-slate-50" @click="toggleProductMediaPicker">{{ productMediaPickerOpen ? t('products.closeLibrary') : t('products.attachImage') }}</button>
                </header>

                <p v-if="mediaError && mediaErrorContext === 'product'" role="alert" class="mx-5 mt-4 rounded-md border border-admin-danger-border bg-admin-danger-bg px-3 py-2 text-sm text-admin-danger sm:mx-6">{{ mediaError }}</p>
                <p v-else-if="mediaAction && mediaErrorContext === 'product'" role="status" class="mx-5 mt-4 text-xs text-admin-neutral sm:mx-6">{{ t('products.updatingGallery') }}</p>

                <div v-if="productMediaEdits.length" class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
                    <article v-for="(asset, index) in productMediaEdits" :key="asset.id" class="grid min-w-0 gap-3 rounded-md border border-admin-border bg-admin-canvas p-3">
                        <div class="flex min-w-0 gap-3"><MediaThumbnail :src="asset.thumbnail_url" :alt="asset.alt_text || asset.default_alt_text" size="lg" /><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-admin-text" :title="asset.title || t('products.untitledImage')">{{ asset.title || t('products.untitledImage') }}</p><p class="mt-1 font-mono text-[10px] text-admin-neutral">{{ t('products.position', { position: index + 1 }) }}</p><label class="mt-3 flex min-h-8 items-center gap-2 text-xs font-medium"><input type="radio" name="primary-media" :checked="asset.is_primary" :disabled="!canUpdate || mediaAction !== null" class="accent-admin-secondary" @change="setPrimary(asset.id)" />{{ t('products.primaryImage') }}</label></div></div>
                        <div><label :for="`product-media-alt-${asset.id}`" class="mb-1.5 block text-xs font-semibold text-admin-text-secondary">{{ t('products.altOverride') }}</label><input :id="`product-media-alt-${asset.id}`" v-model="asset.alt_text" :disabled="!canUpdate" maxlength="1000" :placeholder="asset.default_alt_text || t('products.altFallback')" class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50" /></div>
                        <div v-if="canUpdate" class="flex flex-wrap items-center gap-2 border-t border-admin-border pt-3"><button type="button" :disabled="index === 0 || mediaAction !== null" class="grid size-9 place-items-center rounded-md border border-admin-border bg-white text-admin-text-secondary disabled:opacity-30" :aria-label="t('products.moveLeft', { name: asset.title || t('products.image') })" @click="moveProductMedia(index, -1)">←</button><button type="button" :disabled="index === productMediaEdits.length - 1 || mediaAction !== null" class="grid size-9 place-items-center rounded-md border border-admin-border bg-white text-admin-text-secondary disabled:opacity-30" :aria-label="t('products.moveRight', { name: asset.title || t('products.image') })" @click="moveProductMedia(index, 1)">→</button><button type="button" :disabled="mediaAction !== null" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-3 text-xs font-semibold text-admin-text-secondary disabled:opacity-40" @click="saveProductMediaAlt(asset)">{{ mediaAction === `product-alt-${asset.id}` ? t('common.saving') : t('products.saveAlt') }}</button><button type="button" :disabled="mediaAction !== null" class="ml-auto min-h-9 rounded-md border border-admin-danger-border bg-white px-3 text-xs font-semibold text-admin-danger hover:bg-admin-danger-bg disabled:opacity-40" @click="detachProductMedia(asset)">{{ t('products.detach') }}</button></div>
                    </article>
                </div>
                <p v-else class="m-5 rounded-md border border-dashed border-admin-border-strong bg-admin-canvas px-4 py-8 text-center text-sm text-admin-neutral sm:m-6">{{ t('products.noProductImages') }}</p>

                <div v-if="productMediaPickerOpen && canUpdate" class="border-t border-admin-border bg-admin-canvas p-4 sm:p-6"><div class="mb-4"><h3 class="text-sm font-semibold text-admin-text">{{ t('products.mediaLibrary') }}</h3><p class="mt-1 text-xs text-admin-neutral">{{ t('products.mediaLibraryHelp') }}</p></div><MediaPicker :selected-ids="productMediaEdits.map((asset) => asset.id)" :allow-upload="mediaPermissions.upload" :disabled="mediaAction !== null" :max-image-mb="mediaLimits.max_image_mb" @select="attachProductMedia" /></div>
            </section>

            <section v-if="pricing" id="variant-pricing" class="admin-card scroll-mt-4 overflow-hidden">
                <header class="flex flex-col gap-2 border-b border-admin-border px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div><p class="admin-label text-admin-secondary">{{ t('products.pricingSection', { currency: pricing.currency }) }}</p><h2 class="mt-1 text-lg font-semibold text-admin-text">{{ t('products.variantPricing') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('products.pricingHelp', { name: pricing.name }) }}</p></div>
                    <span v-if="!pricing.can_update" class="rounded border border-admin-border bg-slate-50 px-2 py-1 text-xs font-medium text-admin-neutral">{{ t('common.readOnly') }}</span>
                </header>

                <div class="divide-y divide-admin-border">
                    <form v-for="variant in product.variants" :key="variant.id" class="grid gap-4 px-5 py-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_10rem_minmax(17rem,auto)] lg:items-center" @submit.prevent="setPrice(variant)">
                        <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="truncate font-mono text-xs font-semibold text-admin-text">{{ variant.sku }}</p><span v-if="variant.is_default" class="rounded border border-admin-secondary bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] text-admin-secondary">{{ t('products.default') }}</span></div><p class="mt-1 truncate text-xs text-admin-neutral">{{ variant.display_options.join(' / ') || variant.name || t('products.noOptionValues') }}</p></div>
                        <div><p class="admin-label">{{ t('products.currentPrice') }}</p><p class="mt-1 font-mono text-sm font-semibold text-admin-text">{{ formatPrice(variant.default_price) }}</p></div>
                        <div v-if="pricing.can_update" class="grid gap-2"><div class="flex min-w-0 gap-2"><label class="relative min-w-0 flex-1"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-xs text-admin-neutral">{{ pricing.currency }}</span><input v-model="priceInputs[variant.id]" required inputmode="decimal" placeholder="0.0000" class="admin-control w-full pl-12 pr-3 font-mono text-sm" :class="priceErrors[variant.id] ? 'border-admin-danger' : ''" /></label><button :disabled="priceUpdating[variant.id]" class="min-h-9 rounded-md bg-admin-primary px-3 text-xs font-semibold text-white disabled:cursor-wait disabled:opacity-60">{{ priceUpdating[variant.id] ? t('common.saving') : t('common.save') }}</button><button v-if="variant.default_price !== null" type="button" :disabled="priceUpdating[variant.id]" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-3 text-xs font-semibold text-admin-danger hover:bg-admin-danger-bg disabled:opacity-40" @click="removePrice(variant)">{{ t('common.remove') }}</button></div><FieldError :message="priceErrors[variant.id]" /></div>
                    </form>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>
