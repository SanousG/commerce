<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../FieldError.vue';

interface CategoryChoice {
    id: string;
    name: string;
    parent_id: string | null;
}

const props = defineProps<{
    categories: CategoryChoice[];
    modelValue: string[];
    error?: string;
    disabled?: boolean;
    canCreateCategory?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const categoryNames = computed(() => new Map(props.categories.map((category) => [category.id, category.name])));
const selectedCount = computed(() => props.modelValue.length);
const { t } = useI18n();

const updateSelection = (categoryId: string, selected: boolean) => {
    if (props.disabled) return;

    emit('update:modelValue', selected
        ? [...props.modelValue, categoryId]
        : props.modelValue.filter((id) => id !== categoryId));
};
</script>

<template>
    <fieldset class="grid gap-3" :disabled="disabled" :aria-invalid="error ? 'true' : undefined" aria-describedby="product-categories-help product-categories-error">
        <legend class="sr-only">{{ t('products.categorySelector.title') }}</legend>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-admin-text">{{ t('products.categorySelector.title') }}</p>
                <p id="product-categories-help" class="mt-1 text-xs leading-5 text-admin-neutral">
                    {{ t('products.categorySelector.help') }}
                </p>
            </div>
            <span v-if="categories.length" class="shrink-0 font-mono text-[11px] text-admin-neutral">
                 {{ t('common.selected', { count: selectedCount }) }}
            </span>
        </div>

        <div v-if="categories.length" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            <label
                v-for="category in categories"
                :key="category.id"
                class="flex min-h-14 items-center gap-3 rounded-md border border-admin-border bg-admin-surface px-3 py-2.5 transition has-checked:border-admin-secondary has-checked:bg-blue-50/60 hover:border-admin-border-strong has-disabled:cursor-not-allowed has-disabled:opacity-60"
            >
                <input
                    type="checkbox"
                    :value="category.id"
                    :checked="modelValue.includes(category.id)"
                    class="size-4 shrink-0 accent-admin-secondary"
                    @change="updateSelection(category.id, ($event.target as HTMLInputElement).checked)"
                />
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-admin-text">{{ category.name }}</span>
                    <span v-if="category.parent_id" class="mt-0.5 block truncate text-xs text-admin-neutral">
                        {{ t('products.categorySelector.parent', { name: categoryNames.get(category.parent_id) ?? t('products.categorySelector.unavailable') }) }}
                    </span>
                    <span v-else class="mt-0.5 block text-xs text-admin-neutral">{{ t('products.categorySelector.topLevel') }}</span>
                </span>
            </label>
        </div>

        <div v-else class="rounded-md border border-dashed border-admin-border-strong bg-admin-canvas px-4 py-5">
            <p class="text-sm font-semibold text-admin-text">{{ t('products.categorySelector.none') }}</p>
            <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('products.categorySelector.noneHelp') }}</p>
            <Link v-if="canCreateCategory" href="/admin/categories/create" class="admin-link mt-3 inline-flex min-h-9 items-center text-sm">
                {{ t('products.categorySelector.create') }}
            </Link>
        </div>

        <FieldError id="product-categories-error" :message="error" />
    </fieldset>
</template>
