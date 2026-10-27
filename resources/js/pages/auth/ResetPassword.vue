<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '../../components/FieldError.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';

const { t } = useI18n();

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="t('auth.reset.head')" />

    <AuthLayout
        :eyebrow="t('auth.reset.eyebrow')"
        :title="t('auth.reset.title')"
        :subtitle="t('auth.reset.subtitle')"
    >
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <div>
                <label for="reset-email" class="mb-1.5 block text-sm font-semibold">{{ t('auth.common.email') }}</label>
                <input id="reset-email" v-model="form.email" type="email" autocomplete="email" required autofocus :placeholder="t('auth.common.emailPlaceholder')" aria-describedby="reset-email-error" :aria-invalid="Boolean(form.errors.email)" class="auth-control" />
                <FieldError id="reset-email-error" :message="form.errors.email" />
            </div>

            <div>
                <label for="reset-password" class="mb-1.5 block text-sm font-semibold">{{ t('auth.reset.newPassword') }}</label>
                <input id="reset-password" v-model="form.password" type="password" autocomplete="new-password" required :placeholder="t('auth.common.passwordPlaceholder')" aria-describedby="reset-password-help reset-password-error" :aria-invalid="Boolean(form.errors.password)" class="auth-control" />
                <p id="reset-password-help" class="mt-1.5 text-xs leading-5 text-admin-neutral">{{ t('auth.common.passwordHelp') }}</p>
                <FieldError id="reset-password-error" :message="form.errors.password" />
            </div>

            <div>
                <label for="reset-password-confirmation" class="mb-1.5 block text-sm font-semibold">{{ t('auth.reset.confirmPassword') }}</label>
                <input id="reset-password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required :placeholder="t('auth.common.passwordPlaceholder')" aria-describedby="reset-password-error" :aria-invalid="Boolean(form.errors.password)" class="auth-control" />
            </div>

            <button type="submit" :disabled="form.processing" class="auth-primary-button !mt-5">
                <svg v-if="form.processing" viewBox="0 0 24 24" fill="none" class="mr-2 size-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                {{ form.processing ? t('auth.reset.submitting') : t('auth.reset.submit') }}
            </button>
        </form>
    </AuthLayout>
</template>
