<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PreferenceProps {
    preferences: { email_enabled: boolean; muted_types: string[] };
    types: string[];
    mandatoryTypes: string[];
}

const props = defineProps<PreferenceProps>();
const page = usePage<PageProps>();
const { t } = useI18n();
const saved = computed(() => page.props.status === 'Notification preferences saved.');
const form = useForm({ email_enabled: props.preferences.email_enabled, muted_types: [...props.preferences.muted_types] });
const isMuted = (type: string) => form.muted_types.includes(type);
const onMuteToggle = (type: string, event: Event) => {
    const muted = (event.target as HTMLInputElement).checked;
    form.muted_types = muted ? [...new Set([...form.muted_types, type])] : form.muted_types.filter((value) => value !== type);
};
const submit = () => {
    if (form.processing) return;
    form.patch('/admin/settings/notifications', { preserveScroll: true, onSuccess: () => form.defaults() });
};
</script>

<template>
    <Head :title="t('notifications.preferencePage.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-5xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('notifications.title'), href: '/admin/notifications' }, { label: t('notifications.preferencePage.title') }]" />
            <PageHeader :title="t('notifications.preferencePage.title')" :eyebrow="t('notifications.preferencePage.eyebrow')" :description="t('notifications.preferencePage.description')">
                <template #actions><Link href="/admin/notifications" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold sm:w-auto">{{ t('notifications.preferencePage.openCenter') }}</Link></template>
            </PageHeader>

            <p v-if="saved" class="rounded-md border border-admin-success-border bg-admin-success-bg px-4 py-3 text-sm font-semibold text-admin-success" role="status">{{ t('notifications.preferencePage.saved') }}</p>

            <form class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start" @submit.prevent="submit">
                <div class="admin-card overflow-hidden">
                    <section class="border-b border-admin-border p-4 sm:p-5" aria-labelledby="email-delivery-title">
                        <p class="admin-label text-admin-secondary">{{ t('notifications.preferencePage.emailSection') }}</p>
                        <label class="mt-2 flex cursor-pointer items-start gap-3 rounded-md border border-admin-border bg-admin-canvas p-3">
                            <input v-model="form.email_enabled" type="checkbox" class="mt-0.5 size-4 shrink-0 accent-admin-secondary" />
                            <span><span id="email-delivery-title" class="block text-sm font-semibold">{{ t('notifications.preferencePage.emailTitle') }}</span><span class="mt-1 block text-xs leading-5 text-admin-neutral">{{ t('notifications.preferencePage.emailHelp') }}</span></span>
                        </label>
                        <FieldError :message="form.errors.email_enabled ? t('notifications.preferencePage.errors.email') : undefined" />
                    </section>

                    <fieldset class="p-4 sm:p-5">
                        <legend class="sr-only">{{ t('notifications.preferencePage.typesTitle') }}</legend>
                        <p class="admin-label text-admin-secondary">{{ t('notifications.preferencePage.typesSection') }}</p>
                        <h2 class="mt-1 text-lg font-semibold">{{ t('notifications.preferencePage.typesTitle') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('notifications.preferencePage.typesHelp') }}</p>
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <label v-for="type in types" :key="type" class="flex min-w-0 items-start gap-3 rounded-md border px-3 py-3" :class="mandatoryTypes.includes(type) ? 'border-admin-warning-border bg-admin-warning-bg' : 'border-admin-border bg-white'">
                                <input type="checkbox" class="mt-0.5 size-4 shrink-0 accent-admin-secondary" :checked="isMuted(type)" :disabled="mandatoryTypes.includes(type) || form.processing" @change="onMuteToggle(type, $event)" />
                                <span class="min-w-0"><span class="block text-xs font-semibold">{{ t(`notifications.types.${type}`) }}</span><span class="mt-0.5 block text-[11px] leading-4 text-admin-neutral">{{ mandatoryTypes.includes(type) ? t('notifications.preferencePage.mandatory') : isMuted(type) ? t('notifications.preferencePage.muted') : t('notifications.preferencePage.enabled') }}</span></span>
                            </label>
                        </div>
                        <FieldError :message="form.errors.muted_types ? t('notifications.preferencePage.errors.mandatory') : undefined" />
                    </fieldset>

                    <footer class="flex justify-end border-t border-admin-border bg-admin-canvas px-4 py-4 sm:px-5"><button type="submit" :disabled="form.processing || !form.isDirty" class="min-h-10 w-full rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">{{ form.processing ? t('notifications.preferencePage.saving') : t('notifications.preferencePage.save') }}</button></footer>
                </div>

                <aside class="grid gap-3">
                    <article class="rounded-lg border border-admin-info-border bg-admin-info-bg p-4"><p class="admin-label text-admin-info">{{ t('notifications.preferencePage.scopeSection') }}</p><h2 class="mt-1 text-sm font-semibold text-admin-info">{{ t('notifications.preferencePage.personalTitle') }}</h2><p class="mt-1 text-xs leading-5 text-admin-info">{{ t('notifications.preferencePage.personalHelp') }}</p></article>
                    <article class="rounded-lg border border-admin-warning-border bg-admin-warning-bg p-4"><p class="admin-label text-amber-700">{{ t('notifications.preferencePage.requiredSection') }}</p><h2 class="mt-1 text-sm font-semibold text-amber-800">{{ t('notifications.preferencePage.requiredTitle') }}</h2><p class="mt-1 text-xs leading-5 text-amber-800">{{ t('notifications.preferencePage.requiredHelp') }}</p></article>
                    <article class="admin-card p-4"><p class="admin-label">{{ t('notifications.preferencePage.auditSection') }}</p><h2 class="mt-1 text-sm font-semibold">{{ t('notifications.preferencePage.auditTitle') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('notifications.preferencePage.auditHelp') }}</p></article>
                </aside>
            </form>
        </section>
    </AdminLayout>
</template>
