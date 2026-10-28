<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { StorefrontImage } from '../../../types';
const props = defineProps<{ images: StorefrontImage[]; fallbackImages?: StorefrontImage[] }>();
const { t } = useI18n();
const active = ref(0);
const availableImages = computed(() => props.images.length ? props.images : (props.fallbackImages ?? []));
watch(availableImages, () => { active.value = 0; });
</script>
<template><div><div class="aspect-square overflow-hidden rounded-xl border border-[var(--sf-border)] bg-white"><img v-if="availableImages[active]" :src="availableImages[active].mediumUrl" :alt="availableImages[active].alt" :width="availableImages[active].width" :height="availableImages[active].height" class="h-full w-full object-contain" /><div v-else class="flex h-full items-center justify-center text-sm text-slate-500">{{ t('storefront.imageUnavailable') }}</div></div><div v-if="availableImages.length > 1" class="mt-3 flex gap-3 overflow-x-auto pb-1"><button v-for="(image, index) in availableImages" :key="image.mediumUrl" type="button" class="h-20 w-20 shrink-0 overflow-hidden rounded-lg border-2 bg-white" :class="index === active ? 'border-[var(--sf-accent)]' : 'border-transparent'" :aria-label="t('storefront.viewImage', { number: index + 1 })" @click="active = index"><img :src="image.thumbnailUrl ?? image.mediumUrl" :alt="image.alt" class="h-full w-full object-cover" /></button></div></div></template>
