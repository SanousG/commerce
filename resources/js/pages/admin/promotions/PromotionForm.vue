<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';

export interface Choice { id: string; name: string }
export interface VariantChoice { id: string; sku: string; name: string | null; product: string }
export interface PromotionFormState {
    name: string; internal_code: string; description: string; status: string; discount_type: string;
    discount_value: string; currency: string; starts_at: string; ends_at: string; priority: number;
    scope: string; product_ids: string[]; category_ids: string[]; variant_ids: string[];
    errors: Record<string, string | undefined>; processing: boolean;
}

const props = defineProps<{
    form: PromotionFormState; statuses: string[]; discountTypes: string[]; scopes: string[]; currencies: string[];
    products: Choice[]; categories: Choice[]; variants: VariantChoice[]; disabled?: boolean; mode: 'create' | 'edit';
}>();
defineEmits<{ submit: [] }>();
const { t } = useI18n();
const prefix = `promotion-${props.mode}`;
const selectedCount = computed(() => props.form.product_ids.length + props.form.category_ids.length + props.form.variant_ids.length);
const errorFor = (field: string) => {
    const message = props.form.errors[field] ?? Object.entries(props.form.errors).find(([key]) => key.startsWith(`${field}.`))?.[1];
    if (!message) return undefined;
    const known: Record<string, string> = {
        'Archived promotions cannot be changed.': 'promotions.errors.archived',
        'The discount value must be greater than zero.': 'promotions.errors.positiveDiscount',
        'A percentage discount cannot exceed 100.': 'promotions.errors.percentageMaximum',
        'Select at least one product, category, or variant.': 'promotions.errors.targetsRequired',
        'The internal code has already been taken.': 'promotions.errors.codeTaken',
        'Every target must belong to the current company.': 'promotions.errors.foreignTarget',
    };
    return t(known[message] ?? `promotions.errors.${field}`);
};
</script>

<template>
    <form class="admin-card overflow-hidden" @submit.prevent="$emit('submit')">
        <fieldset :disabled="disabled" class="contents">
            <section class="grid gap-5 border-b border-admin-border p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                <div><p class="admin-label text-admin-secondary">{{ t('promotions.form.identitySection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('promotions.form.identity') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('promotions.form.identityHelp') }}</p></div>
                <div class="grid min-w-0 gap-5 lg:grid-cols-2">
                    <div><div class="mb-1.5 flex items-end justify-between gap-3"><label :for="`${prefix}-name`" class="text-sm font-semibold text-admin-text">{{ t('promotions.name') }} <span class="text-admin-danger" aria-hidden="true">*</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ form.name.length }} / 160</span></div><input :id="`${prefix}-name`" v-model="form.name" required maxlength="160" :autofocus="mode === 'create'" autocomplete="off" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50" :class="errorFor('name') ? 'border-admin-danger' : ''" :aria-invalid="errorFor('name') ? 'true' : undefined" :aria-describedby="`${prefix}-name-help ${prefix}-name-error`" /><p :id="`${prefix}-name-help`" class="mt-1.5 text-xs text-admin-neutral">{{ t('promotions.form.nameHelp') }}</p><FieldError :id="`${prefix}-name-error`" :message="errorFor('name')" /></div>
                    <div><div class="mb-1.5 flex items-end justify-between gap-3"><label :for="`${prefix}-code`" class="text-sm font-semibold text-admin-text">{{ t('promotions.internalCode') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><span class="font-mono text-[10px] text-admin-neutral">{{ form.internal_code.length }} / 60</span></div><input :id="`${prefix}-code`" v-model="form.internal_code" maxlength="60" autocomplete="off" spellcheck="false" placeholder="SUMMER_2026" class="admin-control w-full px-3 py-2 font-mono text-sm uppercase disabled:cursor-not-allowed disabled:bg-slate-50" :class="errorFor('internal_code') ? 'border-admin-danger' : ''" :aria-invalid="errorFor('internal_code') ? 'true' : undefined" :aria-describedby="`${prefix}-code-help ${prefix}-code-error`" /><p :id="`${prefix}-code-help`" class="mt-1.5 text-xs text-admin-neutral">{{ t('promotions.form.codeHelp') }}</p><FieldError :id="`${prefix}-code-error`" :message="errorFor('internal_code')" /></div>
                    <div class="lg:col-span-2"><label :for="`${prefix}-description`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('promotions.descriptionField') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label><textarea :id="`${prefix}-description`" v-model="form.description" rows="3" maxlength="10000" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50" :aria-invalid="errorFor('description') ? 'true' : undefined" :aria-describedby="`${prefix}-description-error`" /><FieldError :id="`${prefix}-description-error`" :message="errorFor('description')" /></div>
                </div>
            </section>

            <section class="grid gap-5 border-b border-admin-border p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                <div><p class="admin-label text-admin-secondary">{{ t('promotions.form.discountSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('promotions.form.discountSettings') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('promotions.form.discountHelp') }}</p></div>
                <div class="grid min-w-0 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div><label :for="`${prefix}-status`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('common.status') }} <span class="text-admin-danger">*</span></label><select :id="`${prefix}-status`" v-model="form.status" required class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50"><option v-for="status in statuses" :key="status" :value="status">{{ t(`status.${status}`) }}</option></select><FieldError :message="errorFor('status')" /></div>
                    <div><label :for="`${prefix}-type`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('promotions.discountType') }} <span class="text-admin-danger">*</span></label><select :id="`${prefix}-type`" v-model="form.discount_type" required class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50"><option v-for="type in discountTypes" :key="type" :value="type">{{ t(`promotions.discountTypes.${type}`) }}</option></select><FieldError :message="errorFor('discount_type')" /></div>
                    <div><label :for="`${prefix}-value`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('promotions.value') }} <span class="text-admin-danger">*</span></label><input :id="`${prefix}-value`" v-model="form.discount_value" required inputmode="decimal" maxlength="20" pattern="\d{1,15}([.]\d{1,4})?" placeholder="0.0000" class="admin-control w-full px-3 py-2 font-mono text-sm disabled:bg-slate-50" :class="errorFor('discount_value') ? 'border-admin-danger' : ''" :aria-invalid="errorFor('discount_value') ? 'true' : undefined" /><FieldError :message="errorFor('discount_value')" /></div>
                    <div><label :for="`${prefix}-currency`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('promotions.currency') }} <span v-if="form.discount_type === 'FIXED_AMOUNT'" class="text-admin-danger">*</span></label><select :id="`${prefix}-currency`" v-model="form.currency" :required="form.discount_type === 'FIXED_AMOUNT'" :disabled="disabled || form.discount_type !== 'FIXED_AMOUNT'" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-50"><option value="">{{ t('promotions.chooseCurrency') }}</option><option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option></select><FieldError :message="errorFor('currency')" /></div>
                </div>
            </section>

            <section class="grid gap-5 border-b border-admin-border p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                <div><p class="admin-label text-admin-secondary">{{ t('promotions.form.validitySection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('promotions.form.validity') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('promotions.form.validityHelp') }}</p></div>
                <div class="grid min-w-0 gap-5 lg:grid-cols-3"><div><label :for="`${prefix}-starts`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('promotions.startsAt') }}</label><input :id="`${prefix}-starts`" v-model="form.starts_at" type="datetime-local" class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50" :aria-invalid="errorFor('starts_at') ? 'true' : undefined" /><FieldError :message="errorFor('starts_at')" /></div><div><label :for="`${prefix}-ends`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('promotions.endsAt') }}</label><input :id="`${prefix}-ends`" v-model="form.ends_at" type="datetime-local" class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50" :aria-invalid="errorFor('ends_at') ? 'true' : undefined" /><FieldError :message="errorFor('ends_at')" /></div><div><label :for="`${prefix}-priority`" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('promotions.priority') }} <span class="text-admin-danger">*</span></label><input :id="`${prefix}-priority`" v-model.number="form.priority" required type="number" min="-1000000" max="1000000" class="admin-control w-full px-3 py-2 font-mono text-sm disabled:bg-slate-50" /><p class="mt-1.5 text-xs text-admin-neutral">{{ t('promotions.form.priorityHelp') }}</p><FieldError :message="errorFor('priority')" /></div></div>
            </section>

            <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                <div><p class="admin-label text-admin-secondary">{{ t('promotions.form.scopeSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('promotions.form.catalogScope') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('promotions.form.scopeHelp') }}</p></div>
                <div class="min-w-0"><fieldset><legend class="sr-only">{{ t('promotions.form.catalogScope') }}</legend><div class="grid gap-2 sm:grid-cols-2"><label v-for="scope in scopes" :key="scope" class="flex cursor-pointer items-start gap-3 rounded-md border border-admin-border bg-white p-3.5"><input v-model="form.scope" type="radio" :value="scope" class="mt-0.5 size-4 accent-admin-secondary" /><span><strong class="block text-sm text-admin-text">{{ t(`promotions.scopes.${scope}`) }}</strong><small class="mt-0.5 block text-xs leading-5 text-admin-neutral">{{ t(`promotions.form.scope${scope}Help`) }}</small></span></label></div><FieldError :message="errorFor('scope')" /></fieldset>
                    <div v-if="form.scope === 'SELECTED'" class="mt-5 grid gap-4 xl:grid-cols-3"><fieldset v-for="group in [{ key: 'product_ids', title: 'products', items: products }, { key: 'category_ids', title: 'categories', items: categories }]" :key="group.key" class="min-w-0"><legend class="mb-2 text-sm font-semibold text-admin-text">{{ t(`promotions.${group.title}`) }}</legend><div class="max-h-64 overflow-y-auto rounded-md border border-admin-border bg-admin-canvas p-2"><label v-for="item in group.items" :key="item.id" class="flex min-h-10 cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs hover:bg-white"><input v-model="form[group.key as 'product_ids' | 'category_ids']" type="checkbox" :value="item.id" class="size-4 shrink-0 accent-admin-secondary" /><span class="min-w-0 truncate" :title="item.name">{{ item.name }}</span></label><p v-if="!group.items.length" class="p-3 text-xs text-admin-neutral">{{ t('promotions.form.noTargets') }}</p></div><FieldError :message="errorFor(group.key)" /></fieldset>
                        <fieldset class="min-w-0"><legend class="mb-2 text-sm font-semibold text-admin-text">{{ t('promotions.variants') }}</legend><div class="max-h-64 overflow-y-auto rounded-md border border-admin-border bg-admin-canvas p-2"><label v-for="variant in variants" :key="variant.id" class="flex min-h-10 cursor-pointer items-start gap-2 rounded px-2 py-1.5 text-xs hover:bg-white"><input v-model="form.variant_ids" type="checkbox" :value="variant.id" class="mt-0.5 size-4 shrink-0 accent-admin-secondary" /><span class="min-w-0"><span class="block truncate font-medium" :title="variant.product">{{ variant.product }}</span><span class="block truncate font-mono text-[10px] text-admin-neutral">{{ variant.sku }}<template v-if="variant.name"> · {{ variant.name }}</template></span></span></label><p v-if="!variants.length" class="p-3 text-xs text-admin-neutral">{{ t('promotions.form.noTargets') }}</p></div><FieldError :message="errorFor('variant_ids')" /></fieldset></div>
                    <p v-if="form.scope === 'SELECTED'" class="mt-3 text-xs leading-5 text-admin-neutral">{{ t('promotions.form.targetLimit') }} · {{ t('common.selected', { count: selectedCount }) }}</p>
                </div>
            </section>
        </fieldset>
        <footer v-if="!disabled" class="flex flex-col-reverse gap-2 border-t border-admin-border bg-admin-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6"><slot name="cancel" /><button type="submit" :disabled="form.processing" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">{{ form.processing ? t('common.saving') : (mode === 'create' ? t('promotions.create') : t('promotions.save')) }}</button></footer>
    </form>
</template>
