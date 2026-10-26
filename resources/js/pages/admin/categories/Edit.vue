<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

type CategoryStatus = 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';

interface ParentChoice {
    id: string;
    name: string;
}

interface Category {
    id: string;
    name: string;
    slug: string;
    status: CategoryStatus;
    parent_id: string | null;
    position: number;
}

const props = defineProps<{
    category: Category;
    parents: ParentChoice[] | Record<string, ParentChoice>;
    statuses: CategoryStatus[];
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('categories.update'));
const parentChoices = computed(() => Object.values(props.parents));
const editableStatuses = computed(() => props.statuses.filter((status) => status !== 'ARCHIVED' || props.category.status === 'ARCHIVED'));
const hasCurrentParentChoice = computed(() => props.category.parent_id === null || parentChoices.value.some((parent) => parent.id === props.category.parent_id));
const { t } = useI18n();

const form = useForm({
    name: props.category.name,
    status: props.category.status,
    parent_id: props.category.parent_id,
    position: props.category.position,
});

const submit = () => form.put(`/admin/categories/${props.category.id}`, { preserveScroll: true });
</script>

<template>
    <Head :title="`${t('common.edit')} ${category.name}`" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-5xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('categories.title'), href: '/admin/categories' }, { label: category.name }, { label: t('common.edit') }]" />

            <PageHeader :title="t('categories.edit')" :eyebrow="t('common.allCompany')" :description="t('categories.editDescription')">
                <template #actions>
                    <Link href="/admin/categories" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50">{{ t('categories.back') }}</Link>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-admin-neutral">
                <StatusBadge :status="category.status" />
                <span>{{ t('categories.category') }} <strong class="ml-1 text-admin-text-secondary">{{ category.name }}</strong></span>
                <span>{{ t('categories.slug') }} <strong class="ml-1 font-mono font-medium text-admin-text-secondary">{{ category.slug }}</strong></span>
                <span v-if="!canUpdate" class="rounded border border-admin-border bg-slate-50 px-2 py-1 font-medium text-admin-neutral">{{ t('common.readOnly') }}</span>
            </div>

            <StatusNotice :message="page.props.status" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <aside class="flex gap-3 rounded-lg border border-admin-info-border bg-admin-info-bg px-4 py-3" :aria-label="t('categories.acyclic')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-info" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="M12 11v5m0-8h.01" /></svg>
                <div>
                    <p class="text-xs font-semibold text-admin-text">{{ t('categories.acyclic') }}</p>
                    <p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('categories.acyclicHelp') }}</p>
                </div>
            </aside>

            <form class="admin-card divide-y divide-admin-border overflow-hidden" @submit.prevent="submit">
                <fieldset :disabled="!canUpdate" class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                        <p class="admin-label text-admin-secondary">{{ t('categories.identity') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('categories.details') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('categories.detailsHelp') }}</p>
                    </div>

                    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <div class="mb-1.5 flex items-end justify-between gap-3">
                                <label for="category-name" class="text-sm font-semibold text-admin-text">{{ t('categories.name') }} <span class="text-admin-danger" aria-hidden="true">*</span></label>
                                <span class="font-mono text-[10px] text-admin-neutral">{{ form.name.length }} / 120</span>
                            </div>
                            <input
                                id="category-name"
                                v-model="form.name"
                                required
                                maxlength="120"
                                autofocus
                                autocomplete="off"
                                class="admin-control w-full px-3 py-2 text-sm placeholder:text-slate-400 disabled:bg-slate-50"
                                :class="form.errors.name ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.name ? 'true' : undefined"
                                aria-describedby="category-name-help category-name-error"
                            />
                            <p id="category-name-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.changedNameHelp') }}</p>
                            <FieldError id="category-name-error" :message="form.errors.name" />
                        </div>

                        <div>
                            <label for="category-slug" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('categories.slug') }} <span class="font-normal text-admin-neutral">({{ t('categories.readOnly') }})</span></label>
                            <div class="relative">
                                <input id="category-slug" :value="category.slug" readonly class="admin-control w-full bg-slate-50 px-3 py-2 pr-16 font-mono text-sm text-admin-neutral" aria-describedby="category-slug-help" />
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 font-mono text-[10px] text-admin-neutral">{{ t('categories.locked') }}</span>
                            </div>
                            <p id="category-slug-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.slugHelp') }}</p>
                        </div>

                        <div>
                            <label for="category-status" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('common.status') }} <span class="text-admin-danger" aria-hidden="true">*</span></label>
                            <select
                                id="category-status"
                                v-model="form.status"
                                required
                                class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50"
                                :class="form.errors.status ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.status ? 'true' : undefined"
                                aria-describedby="category-status-help category-status-error"
                            >
                                <option v-for="status in editableStatuses" :key="status" :value="status">{{ t(`status.${status}`) }}</option>
                            </select>
                            <p id="category-status-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.statusHelp') }}</p>
                            <FieldError id="category-status-error" :message="form.errors.status" />
                        </div>
                    </div>
                </fieldset>

                <fieldset :disabled="!canUpdate" class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                        <p class="admin-label text-admin-secondary">{{ t('categories.placement') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('categories.hierarchy') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('categories.hierarchyEditHelp') }}</p>
                    </div>

                    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="category-parent" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('categories.parent') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label>
                            <select
                                id="category-parent"
                                v-model="form.parent_id"
                                class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50"
                                :class="form.errors.parent_id ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.parent_id ? 'true' : undefined"
                                aria-describedby="category-parent-help category-parent-error"
                            >
                                <option :value="null">{{ t('categories.noParent') }}</option>
                                <option v-if="category.parent_id && !hasCurrentParentChoice" :value="category.parent_id">{{ t('categories.currentParent') }}</option>
                                <option v-for="parent in parentChoices" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                            </select>
                            <p id="category-parent-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.eligibleHelp') }}</p>
                            <FieldError id="category-parent-error" :message="form.errors.parent_id" />
                            <p v-if="!parentChoices.length" class="mt-3 rounded-md border border-dashed border-admin-border-strong bg-admin-canvas px-3 py-2 text-xs leading-5 text-admin-neutral">{{ t('categories.noAlternative') }}</p>
                        </div>

                        <div>
                            <label for="category-position" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('categories.position') }}</label>
                            <div class="relative">
                                <input
                                    id="category-position"
                                    v-model.number="form.position"
                                    type="number"
                                    min="0"
                                    max="4294967295"
                                    step="1"
                                    inputmode="numeric"
                                    class="admin-control w-full px-3 py-2 pr-16 font-mono text-sm tabular-nums disabled:bg-slate-50"
                                    :class="form.errors.position ? 'border-admin-danger' : ''"
                                    :aria-invalid="form.errors.position ? 'true' : undefined"
                                    aria-describedby="category-position-help category-position-error"
                                />
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 font-mono text-[10px] text-admin-neutral">{{ t('categories.index') }}</span>
                            </div>
                            <p id="category-position-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.positionEditHelp') }}</p>
                            <FieldError id="category-position-error" :message="form.errors.position" />
                        </div>
                    </div>
                </fieldset>

                <footer class="flex flex-col-reverse gap-2 bg-admin-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                    <Link href="/admin/categories" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-admin-border-strong bg-admin-surface px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 sm:w-auto">{{ t('common.cancel') }}</Link>
                    <button v-if="canUpdate" type="submit" :disabled="form.processing" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                        {{ form.processing ? t('common.saving') : t('common.saveChanges') }}
                        <svg v-if="!form.processing" viewBox="0 0 20 20" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 10.5 8 14l8-8" /></svg>
                    </button>
                </footer>
            </form>
        </section>
    </AdminLayout>
</template>
