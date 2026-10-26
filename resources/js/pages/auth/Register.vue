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
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="name" class="mb-2 block text-sm font-semibold">{{ t('auth.register.name') }}</label>
                <input id="name" v-model="form.name" type="text" autocomplete="name" required autofocus aria-describedby="name-error" :aria-invalid="Boolean(form.errors.name)" class="w-full border border-[#17211b]/25 bg-white/70 px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15" />
                <FieldError id="name-error" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="mb-2 block text-sm font-semibold">{{ t('auth.common.email') }}</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" required aria-describedby="email-error" :aria-invalid="Boolean(form.errors.email)" class="w-full border border-[#17211b]/25 bg-white/70 px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15" />
                <FieldError id="email-error" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-semibold">{{ t('auth.common.password') }}</label>
                <input id="password" v-model="form.password" type="password" autocomplete="new-password" required aria-describedby="password-error" :aria-invalid="Boolean(form.errors.password)" class="w-full border border-[#17211b]/25 bg-white/70 px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15" />
                <FieldError id="password-error" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-semibold">{{ t('auth.register.confirmPassword') }}</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="w-full border border-[#17211b]/25 bg-white/70 px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15" />
            </div>

            <button type="submit" :disabled="form.processing" class="w-full bg-[#17211b] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? t('auth.register.submitting') : t('auth.register.submit') }}
            </button>
        </form>

        <p class="mt-7 text-sm text-[#516057]">
            {{ t('auth.register.existing') }}
            <Link href="/login" class="font-semibold text-emerald-800 underline-offset-4 hover:underline">{{ t('auth.register.login') }}</Link>
        </p>
    </AuthLayout>
</template>
