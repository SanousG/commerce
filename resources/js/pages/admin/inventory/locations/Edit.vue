<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../../components/admin/PageHeader.vue';
import StatusNotice from '../../../../components/StatusNotice.vue';
import FieldError from '../../../../components/FieldError.vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../../types';

interface Location { id: string; name: string; code: string; status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED'; is_default: boolean }
const props = defineProps<{ location: Location }>();
const form = useForm({ name: props.location.name, code: props.location.code, status: props.location.status });
const page = usePage<PageProps>();
const { t } = useI18n();
const notice = computed(() => page.props.status === 'Inventory location updated.' ? t('inventory.notices.locationUpdated') : undefined);
const submit = () => { if (!form.processing) form.put(`/admin/inventory/locations/${props.location.id}`, { preserveScroll: true }); };
const error = (field: 'name' | 'code' | 'status') => form.errors[field] ? t(`inventory.locations.errors.${field}`) : undefined;
</script>

<template>
    <Head :title="t('inventory.locations.editTitle', { name: location.name })" />
    <AdminLayout><section class="mx-auto grid w-full max-w-4xl gap-5 sm:gap-6">
        <Breadcrumbs :items="[{ label: t('nav.operations') }, { label: t('inventory.title'), href: '/admin/inventory' }, { label: t('inventory.locations.title'), href: '/admin/inventory/locations' }, { label: location.name }]" />
        <PageHeader :title="t('inventory.locations.edit')" :eyebrow="location.code" :description="t('inventory.locations.editDescription')"><template #actions><Link href="/admin/inventory/locations" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary">{{ t('inventory.locations.back') }}</Link></template></PageHeader>
        <StatusNotice :message="notice" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />
        <aside v-if="location.is_default" class="rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3"><p class="text-sm font-semibold text-admin-text">{{ t('inventory.locations.defaultProtected') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('inventory.locations.defaultProtectedHelp') }}</p></aside>
        <aside v-if="location.status === 'ARCHIVED'" class="rounded-md border border-admin-border-strong bg-admin-canvas px-4 py-3"><p class="text-sm font-semibold text-admin-text">{{ t('inventory.locations.archivedTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-admin-text-secondary">{{ t('inventory.locations.archivedHelp') }}</p></aside>
        <form class="admin-card overflow-hidden" @submit.prevent="submit"><header class="border-b border-admin-border px-5 py-4 sm:px-6"><p class="admin-label text-admin-secondary">{{ t('inventory.locations.detailsSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('inventory.locations.details') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('inventory.locations.editHelp') }}</p></header><div class="grid gap-5 p-5 sm:p-6 md:grid-cols-2"><div><label for="location-name" class="mb-1.5 block text-sm font-semibold">{{ t('inventory.locations.name') }} <span class="text-admin-danger">*</span></label><input id="location-name" v-model="form.name" required autofocus maxlength="120" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(form.errors.name)" /><p class="mt-1 text-xs text-admin-neutral">{{ t('inventory.locations.nameHelp') }}</p><FieldError :message="error('name')" /></div><div><label for="location-code" class="mb-1.5 block text-sm font-semibold">{{ t('inventory.locations.code') }} <span class="text-admin-danger">*</span></label><input id="location-code" v-model="form.code" required maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" class="admin-control w-full px-3 font-mono text-sm uppercase" :aria-invalid="Boolean(form.errors.code)" /><p class="mt-1 text-xs text-admin-neutral">{{ t('inventory.locations.codeHelp') }}</p><FieldError :message="error('code')" /></div><div class="md:col-span-2"><label for="location-status" class="mb-1.5 block text-sm font-semibold">{{ t('common.status') }} <span class="text-admin-danger">*</span></label><select id="location-status" v-model="form.status" required class="admin-control w-full px-3 text-sm md:max-w-sm" :aria-invalid="Boolean(form.errors.status)"><option value="ACTIVE">{{ t('status.ACTIVE') }}</option><option value="INACTIVE">{{ t('status.INACTIVE') }}</option><option value="ARCHIVED">{{ t('status.ARCHIVED') }}</option></select><p class="mt-1 text-xs text-admin-neutral">{{ t('inventory.locations.editStatusHelp') }}</p><FieldError :message="error('status')" /></div></div><footer class="flex flex-col-reverse gap-2 border-t border-admin-border bg-admin-canvas px-5 py-4 sm:flex-row sm:justify-end sm:px-6"><Link href="/admin/inventory/locations" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary">{{ t('common.cancel') }}</Link><button type="submit" :disabled="form.processing || !form.isDirty" class="min-h-10 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? t('common.saving') : t('common.saveChanges') }}</button></footer></form>
    </section></AdminLayout>
</template>
