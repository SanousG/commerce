<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

const props = defineProps<{ tenant: { name: string; slug: string; status: string } }>();
const page = usePage<PageProps>();
const { t } = useI18n();
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('settings.manage') ?? false);
const saved = computed(() => page.props.status === 'Company settings updated.');
const form = useForm({ name: props.tenant.name });
const submit = () => {
    if (!canManage.value || form.processing) return;
    form.patch('/admin/settings/general', { preserveScroll: true, onSuccess: () => form.defaults() });
};
</script>

<template>
    <Head :title="t('settings.general.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-4xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('settings.title'), href: '/admin/settings' }, { label: t('settings.general.title') }]" />
            <PageHeader :title="t('settings.general.title')" :eyebrow="t('settings.general.eyebrow')" :description="t('settings.general.description')" />

            <div v-if="!canManage" class="rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3" role="status">
                <p class="text-sm font-semibold text-amber-800">{{ t('settings.general.readOnlyTitle') }}</p>
                <p class="mt-0.5 text-xs leading-5 text-amber-800">{{ t('settings.general.readOnlyHelp') }}</p>
            </div>
            <p v-if="saved" class="rounded-md border border-admin-success-border bg-admin-success-bg px-4 py-3 text-sm font-semibold text-admin-success" role="status">{{ t('settings.general.saved') }}</p>

            <form class="admin-card overflow-hidden" @submit.prevent="submit">
                <header class="border-b border-admin-border bg-admin-canvas px-4 py-4 sm:px-6">
                    <p class="admin-label text-admin-secondary">{{ t('settings.general.section') }}</p>
                    <h2 class="mt-1 text-lg font-semibold">{{ t('settings.general.identity') }}</h2>
                    <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('settings.general.identityHelp') }}</p>
                </header>
                <div class="grid gap-5 p-4 sm:p-6">
                    <div>
                        <label for="company-name" class="mb-1.5 block text-sm font-semibold">{{ t('settings.general.name') }} <span class="text-admin-danger" aria-hidden="true">*</span></label>
                        <input id="company-name" v-model="form.name" :disabled="!canManage" required maxlength="120" autocomplete="organization" class="admin-control w-full px-3 text-sm disabled:cursor-not-allowed disabled:bg-admin-canvas" :aria-invalid="Boolean(form.errors.name)" aria-describedby="company-name-help company-name-error" />
                        <p id="company-name-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('settings.general.nameHelp') }}</p>
                        <FieldError id="company-name-error" :message="form.errors.name ? t('settings.general.nameError') : undefined" />
                    </div>
                    <dl class="grid gap-4 rounded-md border border-admin-border bg-admin-canvas p-4 text-sm sm:grid-cols-2">
                        <div class="min-w-0"><dt class="admin-label">{{ t('settings.general.slug') }}</dt><dd class="mt-1 break-all font-mono text-xs text-admin-text-secondary">{{ tenant.slug }}</dd></div>
                        <div><dt class="admin-label">{{ t('common.status') }}</dt><dd class="mt-1"><StatusBadge :status="tenant.status" /></dd></div>
                    </dl>
                </div>
                <footer v-if="canManage" class="flex justify-end border-t border-admin-border bg-admin-canvas px-4 py-4 sm:px-6">
                    <button type="submit" :disabled="form.processing || !form.isDirty" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">{{ form.processing ? t('settings.general.saving') : t('settings.general.save') }}</button>
                </footer>
            </form>

            <p class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3 text-xs leading-5 text-admin-info">{{ t('settings.general.storeOwnership') }}</p>
        </section>
    </AdminLayout>
</template>
