<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface ParentChoice {
    id: string;
    name: string;
}

defineProps<{ parents: ParentChoice[] }>();

const form = useForm({
    name: '',
    parent_id: null as string | null,
    position: 0,
});
const { t } = useI18n();

const submit = () => form.post('/admin/categories');
</script>

<template>
    <Head :title="t('categories.create')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-5xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('categories.title'), href: '/admin/categories' }, { label: t('common.create') }]" />

            <PageHeader :title="t('categories.create')" :eyebrow="t('common.allCompany')" :description="t('categories.createDescription')" />

            <aside class="flex gap-3 rounded-lg border border-admin-info-border bg-admin-info-bg px-4 py-3" :aria-label="t('categories.createdActive')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-4 shrink-0 text-admin-info" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="M12 11v5m0-8h.01" /></svg>
                <div>
                    <p class="text-xs font-semibold text-admin-text">{{ t('categories.createdActive') }}</p>
                    <p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('categories.createdActiveHelp') }}</p>
                </div>
            </aside>

            <form class="admin-card divide-y divide-admin-border overflow-hidden" @submit.prevent="submit">
                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                        <p class="admin-label text-admin-secondary">{{ t('categories.identity') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('categories.definition') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('categories.definitionHelp') }}</p>
                    </div>

                    <div class="min-w-0">
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
                            class="admin-control w-full px-3 py-2 text-sm placeholder:text-slate-400"
                            :class="form.errors.name ? 'border-admin-danger' : ''"
                            :aria-invalid="form.errors.name ? 'true' : undefined"
                            aria-describedby="category-name-help category-name-error"
                        />
                        <p id="category-name-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.nameHelp') }}</p>
                        <FieldError id="category-name-error" :message="form.errors.name" />
                    </div>
                </section>

                <section class="grid gap-5 p-5 sm:p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
                    <div>
                        <p class="admin-label text-admin-secondary">{{ t('categories.placement') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-text">{{ t('categories.hierarchy') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('categories.hierarchyCreateHelp') }}</p>
                    </div>

                    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="category-parent" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('categories.parent') }} <span class="font-normal text-admin-neutral">({{ t('common.optional') }})</span></label>
                            <select
                                id="category-parent"
                                v-model="form.parent_id"
                                class="admin-control w-full px-3 py-2 text-sm"
                                :class="form.errors.parent_id ? 'border-admin-danger' : ''"
                                :aria-invalid="form.errors.parent_id ? 'true' : undefined"
                                aria-describedby="category-parent-help category-parent-error"
                            >
                                <option :value="null">{{ t('categories.noParent') }}</option>
                                <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                            </select>
                            <p id="category-parent-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.parentHelp') }}</p>
                            <FieldError id="category-parent-error" :message="form.errors.parent_id" />
                            <p v-if="!parents.length" class="mt-3 rounded-md border border-dashed border-admin-border-strong bg-admin-canvas px-3 py-2 text-xs leading-5 text-admin-neutral">{{ t('categories.noParents') }}</p>
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
                                    class="admin-control w-full px-3 py-2 pr-16 font-mono text-sm tabular-nums"
                                    :class="form.errors.position ? 'border-admin-danger' : ''"
                                    :aria-invalid="form.errors.position ? 'true' : undefined"
                                    aria-describedby="category-position-help category-position-error"
                                />
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 font-mono text-[10px] text-admin-neutral">{{ t('categories.index') }}</span>
                            </div>
                            <p id="category-position-help" class="mt-1.5 text-xs text-admin-neutral">{{ t('categories.positionHelp') }}</p>
                            <FieldError id="category-position-error" :message="form.errors.position" />
                        </div>
                    </div>
                </section>

                <footer class="flex flex-col-reverse gap-2 bg-admin-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                    <Link href="/admin/categories" class="inline-flex min-h-10 w-full items-center justify-center rounded-md border border-admin-border-strong bg-admin-surface px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 sm:w-auto">{{ t('common.cancel') }}</Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                        {{ form.processing ? t('common.creating') : t('categories.create') }}
                        <svg v-if="!form.processing" viewBox="0 0 20 20" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m7 4 6 6-6 6" /></svg>
                    </button>
                </footer>
            </form>
        </section>
    </AdminLayout>
</template>
