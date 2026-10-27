<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../components/FieldError.vue';
import StatusNotice from '../../components/StatusNotice.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import type { PageProps } from '../../types';

const page = usePage<PageProps>();
const status = computed(() => page.props.status);
const { t } = useI18n();
const form = useForm({ email: '' });

const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head :title="t('auth.forgot.head')" />

    <AuthLayout
        :eyebrow="t('auth.forgot.eyebrow')"
        :title="t('auth.forgot.title')"
        :subtitle="t('auth.forgot.subtitle')"
    >
        <StatusNotice :message="status" />

        <div class="mb-6 flex gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mt-0.5 size-5 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.7"/><path d="M12 11v5m0-8h.01" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p>{{ t('auth.forgot.privacy') }}</p>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <div>
                <label for="forgot-email" class="mb-1.5 block text-sm font-semibold">{{ t('auth.common.email') }}</label>
                <input id="forgot-email" v-model="form.email" type="email" autocomplete="email" required autofocus :placeholder="t('auth.common.emailPlaceholder')" aria-describedby="forgot-email-error" :aria-invalid="Boolean(form.errors.email)" class="auth-control" />
                <FieldError id="forgot-email-error" :message="form.errors.email" />
            </div>

            <button type="submit" :disabled="form.processing" class="auth-primary-button">
                <svg v-if="form.processing" viewBox="0 0 24 24" fill="none" class="mr-2 size-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                {{ form.processing ? t('auth.common.sending') : t('auth.forgot.submit') }}
            </button>
        </form>

        <div class="mt-6 border-t border-admin-border pt-5 text-center">
            <Link href="/login" class="auth-link inline-flex items-center gap-2 text-sm">
                <svg viewBox="0 0 20 20" fill="none" class="size-4" aria-hidden="true"><path d="m12.5 5-5 5 5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ t('auth.forgot.back') }}
            </Link>
        </div>
    </AuthLayout>
</template>
