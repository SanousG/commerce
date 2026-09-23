<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../components/FieldError.vue';
import StatusNotice from '../../components/StatusNotice.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import type { PageProps } from '../../types';

const page = usePage<PageProps>();
const status = computed(() => page.props.status);
const form = useForm({ email: '' });

const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Forgot password" />

    <AuthLayout
        eyebrow="Account recovery"
        title="Reset your password."
        subtitle="Enter your email. If it matches an account, we will send recovery instructions."
    >
        <StatusNotice :message="status" />

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-sm font-semibold">Email address</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" required autofocus aria-describedby="email-error" :aria-invalid="Boolean(form.errors.email)" class="w-full border border-[#17211b]/25 bg-white/70 px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15" />
                <FieldError id="email-error" :message="form.errors.email" />
            </div>

            <button type="submit" :disabled="form.processing" class="w-full bg-[#17211b] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Sending...' : 'Send reset instructions' }}
            </button>
        </form>

        <Link href="/login" class="mt-7 inline-block text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline">
            Back to sign in
        </Link>
    </AuthLayout>
</template>
