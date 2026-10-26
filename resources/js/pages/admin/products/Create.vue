<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import ProductCategorySelector from '../../../components/catalog/ProductCategorySelector.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface CategoryChoice { id: string; name: string; parent_id: string | null }
defineProps<{ categories: CategoryChoice[] }>();

const page = usePage<PageProps>();
const form = useForm({ name: '', description: '', sku: '', barcode: '', categories: [] as string[] });
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreateCategory = computed(() => permissions.value.includes('categories.create'));
const { t } = useI18n();
const categoryError = computed(() => {
    const errors = form.errors as Record<string, string>;

    return errors.categories ?? Object.entries(errors).find(([key]) => key.startsWith('categories.'))?.[1];
});
const submit = () => form.post('/admin/products');
</script>

<template>
    <Head :title="t('products.create')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-5xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('products.title'), href: '/admin/products' }, { label: t('common.create') }]" />
            <PageHeader :title="t('products.create')" :eyebrow="t('common.allCompany')" :description="t('products.createDescription')" />

            <aside class="flex gap-3 rounded-lg border border-admin-info-border bg-admin-info-bg px-4 py-3" :aria-label="t('products.oneStep')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-info" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="M12 11v5m0-8h.01" /></svg>
                <div>
                     <p class="text-xs font-semibold text-admin-text">{{ t('products.oneStep') }}</p>
                     <p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('products.oneStepHelp') }}</p>
                </div>
            </aside>

            <form class="admin-card divide-y divide-admin-border overflow-hidden" @submit.prevent="submit">
                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                         <p class="admin-label text-admin-secondary">{{ t('products.sectionProduct') }}</p>
                         <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('products.general') }}</h2>
                         <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('products.generalHelp') }}</p>
                    </div>

                    <div class="grid min-w-0 gap-5">
                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3">
                                 <label for="name" class="text-sm font-semibold text-admin-text">{{ t('products.name') }} <span class="text-admin-danger" aria-hidden="true">*</span></label>
                                <span class="font-mono text-[10px] text-admin-neutral">{{ form.name.length }} / 160</span>
                            </div>
                            <input
                                id="name"
                                v-model="form.name"
                                required
                                maxlength="160"
                                autofocus
                                class="admin-control w-full px-3 py-2 text-sm placeholder:text-slate-400"
                                :class="form.errors.name ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.name ? 'true' : undefined"
                                aria-describedby="product-name-help product-name-error"
                            />
                             <p id="product-name-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('products.nameHelp') }}</p>
                            <FieldError id="product-name-error" :message="form.errors.name" />
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3">
                                 <label for="description" class="text-sm font-semibold text-admin-text">{{ t('products.description') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label>
                                <span class="font-mono text-[10px] text-admin-neutral">{{ form.description.length }} / 10,000</span>
                            </div>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="5"
                                maxlength="10000"
                                class="admin-control block w-full resize-y px-3 py-2 text-sm leading-5 placeholder:text-slate-400"
                                :class="form.errors.description ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.description ? 'true' : undefined"
                                aria-describedby="product-description-help product-description-error"
                            />
                             <p id="product-description-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('products.descriptionHelp') }}</p>
                            <FieldError id="product-description-error" :message="form.errors.description" />
                        </div>
                    </div>
                </section>

                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                         <p class="admin-label text-admin-secondary">{{ t('products.initialVariant') }}</p>
                         <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('products.identifiers') }}</h2>
                         <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('products.identifiersHelp') }}</p>
                    </div>

                    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3">
                                 <label for="sku" class="text-sm font-semibold text-admin-text">{{ t('products.defaultSku') }} <span class="text-admin-danger" aria-hidden="true">*</span></label>
                                <span class="font-mono text-[10px] text-admin-neutral">{{ form.sku.length }} / 120</span>
                            </div>
                            <input
                                id="sku"
                                v-model="form.sku"
                                required
                                maxlength="120"
                                spellcheck="false"
                                class="admin-control w-full px-3 py-2 font-mono text-sm placeholder:text-slate-400"
                                :class="form.errors.sku ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.sku ? 'true' : undefined"
                                aria-describedby="product-sku-help product-sku-error"
                            />
                             <p id="product-sku-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('products.skuHelp') }}</p>
                            <FieldError id="product-sku-error" :message="form.errors.sku" />
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-end justify-between gap-3">
                                 <label for="barcode" class="text-sm font-semibold text-admin-text">{{ t('products.barcode') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label>
                                <span class="font-mono text-[10px] text-admin-neutral">{{ form.barcode.length }} / 120</span>
                            </div>
                            <input
                                id="barcode"
                                v-model="form.barcode"
                                maxlength="120"
                                spellcheck="false"
                                class="admin-control w-full px-3 py-2 font-mono text-sm placeholder:text-slate-400"
                                :class="form.errors.barcode ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.barcode ? 'true' : undefined"
                                aria-describedby="product-barcode-help product-barcode-error"
                            />
                             <p id="product-barcode-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('products.barcodeHelp') }}</p>
                            <FieldError id="product-barcode-error" :message="form.errors.barcode" />
                        </div>
                    </div>
                </section>

                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                         <p class="admin-label text-admin-secondary">{{ t('products.organization') }}</p>
                         <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('products.catalogOrganization') }}</h2>
                         <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('products.organizationHelp') }}</p>
                    </div>

                    <div class="min-w-0">
                        <ProductCategorySelector v-model="form.categories" :categories="categories" :error="categoryError" :can-create-category="canCreateCategory" />
                    </div>
                </section>

                <footer class="flex flex-col-reverse gap-2 bg-admin-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                     <Link href="/admin/products" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-admin-border-strong bg-admin-surface px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 sm:w-auto">{{ t('common.cancel') }}</Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                         {{ form.processing ? t('common.creating') : t('products.create') }}
                        <svg v-if="!form.processing" viewBox="0 0 20 20" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m7 4 6 6-6 6" /></svg>
                    </button>
                </footer>
            </form>
        </section>
    </AdminLayout>
</template>
