<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicVariant } from '../../../types';
const props = defineProps<{ variants: PublicVariant[]; modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const { t } = useI18n();
const optionNames = computed(() => [...new Set(props.variants.flatMap((variant) => variant.options.map((option) => option.name)))]);
function label(variant: PublicVariant) { return variant.options.map((option) => option.value).join(' / ') || variant.name; }
</script>
<template><fieldset><legend class="text-sm font-semibold text-slate-950">{{ optionNames.length ? optionNames.join(' / ') : t('storefront.variant') }}</legend><div class="mt-3 flex flex-wrap gap-2"><button v-for="variant in variants" :key="variant.key" type="button" class="min-h-11 rounded-lg border px-4 text-sm font-semibold transition" :class="modelValue === variant.key ? 'border-[var(--sf-primary)] bg-[var(--sf-primary)] text-white' : 'border-[var(--sf-border)] bg-white text-slate-800 hover:border-[var(--sf-accent)]'" :aria-pressed="modelValue === variant.key" @click="emit('update:modelValue', variant.key)">{{ label(variant) }}<span v-if="variant.availability.state === 'unavailable'" class="ml-1 font-normal">· {{ t('storefront.availability.unavailable') }}</span></button></div></fieldset></template>
