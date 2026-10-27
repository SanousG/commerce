<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '../../components/FieldError.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="t('auth.register.head')" />

    <AuthLayout
        :eyebrow="t('auth.register.eyebrow')"
        :title="t('auth.register.title')"
        :subtitle="t('auth.register.subtitle')"
    >
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <div>
                <label for="register-name" class="mb-1.5 block text-sm font-semibold">{{ t('auth.register.name') }}</label>
                <input id="register-name" v-model="form.name" type="text" autocomplete="name" required autofocus :placeholder="t('auth.register.namePlaceholder')" aria-describedby="register-name-error" :aria-invalid="Boolean(form.errors.name)" class="auth-control" />
                <FieldError id="register-name-error" :message="form.errors.name" />
            </div>

            <div>
                <label for="register-email" class="mb-1.5 block text-sm font-semibold">{{ t('auth.common.email') }}</label>
                <input id="register-email" v-model="form.email" type="email" autocomplete="email" required :placeholder="t('auth.common.emailPlaceholder')" aria-describedby="register-email-error" :aria-invalid="Boolean(form.errors.email)" class="auth-control" />
                <FieldError id="register-email-error" :message="form.errors.email" />
            </div>

            <div>
                <label for="register-password" class="mb-1.5 block text-sm font-semibold">{{ t('auth.common.password') }}</label>
                <input id="register-password" v-model="form.password" type="password" autocomplete="new-password" required :placeholder="t('auth.common.passwordPlaceholder')" aria-describedby="register-password-help register-password-error" :aria-invalid="Boolean(form.errors.password)" class="auth-control" />
                <p id="register-password-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('auth.common.passwordHelp') }}</p>
                <FieldError id="register-password-error" :message="form.errors.password" />
            </div>

            <div>
                <label for="register-password-confirmation" class="mb-1.5 block text-sm font-semibold">{{ t('auth.register.confirmPassword') }}</label>
                <input id="register-password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required :placeholder="t('auth.common.passwordPlaceholder')" aria-describedby="register-password-error" :aria-invalid="Boolean(form.errors.password)" class="auth-control" />
            </div>

            <button type="submit" :disabled="form.processing" class="auth-primary-button !mt-5">
                <svg v-if="form.processing" viewBox="0 0 24 24" fill="none" class="mr-2 size-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                {{ form.processing ? t('auth.register.submitting') : t('auth.register.submit') }}
            </button>
        </form>

        <p class="mt-6 border-t border-admin-border pt-5 text-center text-sm text-admin-neutral">
            {{ t('auth.register.existing') }}
            <Link href="/login" class="auth-link ml-1">{{ t('auth.register.login') }}</Link>
        </p>
    </AuthLayout>
</template>
