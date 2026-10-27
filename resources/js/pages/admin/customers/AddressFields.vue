<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';

type TextKey = 'label' | 'recipient_name' | 'phone' | 'country_code' | 'region' | 'city' | 'district' | 'address_line_1' | 'address_line_2' | 'postal_code' | 'reference';
interface AddressData extends Record<TextKey, string> { is_default_shipping: boolean; is_default_billing: boolean }
interface AddressForm extends AddressData { errors: Partial<Record<keyof AddressData, string>> }
const props = defineProps<{ form: AddressForm; prefix: string; disabled?: boolean }>();
const { t } = useI18n();
const fields: Array<{ key: TextKey; label: string; max: number; required?: boolean; autocomplete?: string }> = [
    { key: 'label', label: 'label', max: 80 }, { key: 'recipient_name', label: 'recipientName', max: 200, autocomplete: 'name' },
    { key: 'phone', label: 'phone', max: 32, autocomplete: 'tel' }, { key: 'country_code', label: 'countryCode', max: 2, required: true, autocomplete: 'country' },
    { key: 'region', label: 'region', max: 120, autocomplete: 'address-level1' }, { key: 'city', label: 'city', max: 120, required: true, autocomplete: 'address-level2' },
    { key: 'district', label: 'district', max: 120, autocomplete: 'address-level3' }, { key: 'address_line_1', label: 'addressLine1', max: 240, required: true, autocomplete: 'address-line1' },
    { key: 'address_line_2', label: 'addressLine2', max: 240, autocomplete: 'address-line2' }, { key: 'postal_code', label: 'postalCode', max: 32, autocomplete: 'postal-code' },
    { key: 'reference', label: 'reference', max: 500 },
];
const errorKeys: Record<TextKey, string> = { label: 'label', recipient_name: 'recipientName', phone: 'phone', country_code: 'countryCode', region: 'region', city: 'city', district: 'district', address_line_1: 'addressLine1', address_line_2: 'addressLine2', postal_code: 'postalCode', reference: 'reference' };
const error = (key: TextKey) => props.form.errors[key] ? t(`customers.errors.${errorKeys[key]}`) : undefined;
</script>

<template>
    <div v-for="field in fields" :key="field.key" :class="field.key === 'reference' ? 'sm:col-span-2' : ''"><label :for="`${prefix}-${field.key}`" class="mb-1.5 block text-xs font-semibold text-admin-text">{{ t(`customers.${field.label}`) }} <span v-if="field.required" class="text-admin-danger">*</span><span v-else class="font-normal text-admin-neutral"> ({{ t('common.optional') }})</span></label><input :id="`${prefix}-${field.key}`" v-model="form[field.key]" :disabled="disabled" :required="field.required" :maxlength="field.max" :autocomplete="field.autocomplete" :type="field.key === 'phone' ? 'tel' : 'text'" :inputmode="field.key === 'phone' ? 'tel' : undefined" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas disabled:text-admin-neutral" :class="field.key === 'phone' || field.key === 'country_code' || field.key === 'postal_code' ? 'font-mono' : ''" :aria-invalid="Boolean(form.errors[field.key])" /><FieldError :message="error(field.key)" /></div>
    <div class="grid gap-2 sm:col-span-2 sm:grid-cols-2"><label class="flex min-h-10 items-center gap-2 rounded-md border border-admin-border px-3 text-xs font-medium"><input v-model="form.is_default_shipping" :disabled="disabled" type="checkbox" class="size-4 rounded border-admin-border-strong text-admin-secondary" />{{ t('customers.defaultShipping') }}</label><label class="flex min-h-10 items-center gap-2 rounded-md border border-admin-border px-3 text-xs font-medium"><input v-model="form.is_default_billing" :disabled="disabled" type="checkbox" class="size-4 rounded border-admin-border-strong text-admin-secondary" />{{ t('customers.defaultBilling') }}</label></div>
</template>
