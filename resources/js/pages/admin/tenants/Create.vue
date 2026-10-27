<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const { t } = useI18n();
const form = useForm({ name: '' });

const submit = () => {
    if (form.processing) return;
    form.post('/admin/tenants', { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('tenancy.create.title')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-3xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('tenancy.companies.title'), href: '/admin/tenants' }, { label: t('tenancy.create.title') }]" />
            <PageHeader :title="t('tenancy.create.title')" :eyebrow="t('tenancy.create.eyebrow')" :description="t('tenancy.create.description')" />

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_17rem] lg:items-start">
                <form class="admin-card min-w-0 p-4 sm:p-5" @submit.prevent="submit">
                    <div class="border-b border-admin-border pb-4">
                        <p class="admin-label">{{ t('tenancy.create.section') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('tenancy.create.details') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('tenancy.create.detailsHelp') }}</p>
                    </div>

                    <label for="tenant-name" class="mt-4 grid gap-1.5">
                        <span class="admin-label">{{ t('tenancy.create.name') }}</span>
                        <input
                            id="tenant-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="organization"
                            required
                            autofocus
                            maxlength="120"
                            :placeholder="t('tenancy.create.namePlaceholder')"
                            aria-describedby="tenant-name-help tenant-name-error"
                            :aria-invalid="Boolean(form.errors.name)"
                            class="admin-control w-full px-3 text-sm"
                        />
                    </label>
                    <p id="tenant-name-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('tenancy.create.nameHelp') }}</p>
                    <FieldError id="tenant-name-error" :message="form.errors.name" />

                    <div class="mt-5 flex flex-col-reverse gap-2 border-t border-admin-border pt-4 sm:flex-row sm:justify-end">
                        <Link href="/admin/tenants" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('common.cancel') }}</Link>
                        <button type="submit" :disabled="form.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60">{{ form.processing ? t('tenancy.create.creating') : t('tenancy.create.submit') }}</button>
                    </div>
                </form>

                <aside class="admin-card p-4" aria-labelledby="tenant-creation-outcome">
                    <p class="admin-label">{{ t('tenancy.create.outcomeSection') }}</p>
                    <h2 id="tenant-creation-outcome" class="mt-1 text-sm font-semibold text-admin-primary">{{ t('tenancy.create.outcome') }}</h2>
                    <ul class="mt-3 grid gap-2 text-xs leading-5 text-admin-text-secondary">
                        <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-admin-secondary" aria-hidden="true" />{{ t('tenancy.create.ownerOutcome') }}</li>
                        <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-admin-secondary" aria-hidden="true" />{{ t('tenancy.create.activeOutcome') }}</li>
                        <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-admin-secondary" aria-hidden="true" />{{ t('tenancy.create.defaultsOutcome') }}</li>
                        <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-admin-secondary" aria-hidden="true" />{{ t('tenancy.create.storeOutcome') }}</li>
                    </ul>
                </aside>
            </div>
        </section>
    </AdminLayout>
</template>
