<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../components/FieldError.vue';
import StatusNotice from '../../components/StatusNotice.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import type { PageProps } from '../../types';

const page = usePage<PageProps>();
const status = computed(() => page.props.status);
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
    <Head title="Log in" />

    <AuthLayout
        eyebrow="Secure access"
        title="Welcome back."
        subtitle="Sign in to continue to your administrative workspace."
    >
        <StatusNotice :message="status" />

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-sm font-semibold">Email address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    autofocus
                    aria-describedby="email-error"
                    :aria-invalid="Boolean(form.errors.email)"
                    class="w-full border border-[#17211b]/25 bg-white/70 px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15"
                />
                <FieldError id="email-error" :message="form.errors.email" />
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-4">
                    <label for="password" class="text-sm font-semibold">Password</label>
                    <Link href="/forgot-password" class="text-sm font-medium text-emerald-800 underline-offset-4 hover:underline">
                        Forgot password?
                    </Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    aria-describedby="password-error"
                    :aria-invalid="Boolean(form.errors.password)"
                    class="w-full border border-[#17211b]/25 bg-white/70 px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15"
                />
                <FieldError id="password-error" :message="form.errors.password" />
            </div>

            <label class="flex w-fit items-center gap-3 text-sm text-[#39473f]">
                <input v-model="form.remember" type="checkbox" class="size-4 accent-emerald-800" />
                Remember me on this device
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full bg-[#17211b] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-60"
            >
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>

        <p class="mt-7 text-sm text-[#516057]">
            New here?
            <Link href="/register" class="font-semibold text-emerald-800 underline-offset-4 hover:underline">
                Create an account
            </Link>
        </p>
    </AuthLayout>
</template>
