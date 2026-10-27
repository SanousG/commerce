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
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="t('auth.login.head')" />

    <AuthLayout
        :eyebrow="t('auth.login.eyebrow')"
        :title="t('auth.login.title')"
        :subtitle="t('auth.login.subtitle')"
    >
        <StatusNotice :message="status" />

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <div>
                <label for="login-email" class="mb-1.5 block text-sm font-semibold text-admin-text">{{ t('auth.common.email') }}</label>
                <input
                    id="login-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    autofocus
                    :placeholder="t('auth.common.emailPlaceholder')"
                    aria-describedby="login-email-error"
                    :aria-invalid="Boolean(form.errors.email)"
                    class="auth-control"
                />
                <FieldError id="login-email-error" :message="form.errors.email" />
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between gap-4">
                    <label for="login-password" class="text-sm font-semibold text-admin-text">{{ t('auth.common.password') }}</label>
                    <Link href="/forgot-password" class="auth-link text-xs sm:text-sm">
                        {{ t('auth.login.forgot') }}
                    </Link>
                </div>
                <input
                    id="login-password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :placeholder="t('auth.common.passwordPlaceholder')"
                    aria-describedby="login-password-error"
                    :aria-invalid="Boolean(form.errors.password)"
                    class="auth-control"
                />
                <FieldError id="login-password-error" :message="form.errors.password" />
            </div>

            <label for="remember" class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-admin-text-secondary">
                <input id="remember" v-model="form.remember" type="checkbox" class="size-4 rounded border-admin-border-strong accent-admin-secondary" />
                <span>{{ t('auth.login.remember') }}</span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="auth-primary-button"
            >
                <svg v-if="form.processing" viewBox="0 0 24 24" fill="none" class="mr-2 size-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                {{ form.processing ? t('auth.login.submitting') : t('auth.login.submit') }}
            </button>
        </form>

        <p class="mt-6 border-t border-admin-border pt-5 text-center text-sm text-admin-neutral">
            {{ t('auth.login.newHere') }}
            <Link href="/register" class="auth-link ml-1">
                {{ t('auth.login.register') }}
            </Link>
        </p>
    </AuthLayout>
</template>
