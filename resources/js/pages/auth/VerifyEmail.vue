<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusNotice from '../../components/StatusNotice.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import type { PageProps } from '../../types';

const page = usePage<PageProps>();
const sent = computed(() => page.props.status === 'verification-link-sent');
const form = useForm({});

const resend = () => form.post('/email/verification-notification');
</script>

<template>
    <Head title="Verify email" />

    <AuthLayout
        eyebrow="One last check"
        title="Verify your email."
        subtitle="Open the link we sent to your inbox before entering the administrative workspace."
    >
        <StatusNotice
            v-if="sent"
            message="A new verification link has been sent to your email address."
        />

        <button
            type="button"
            :disabled="form.processing"
            class="w-full bg-[#17211b] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-60"
            @click="resend"
        >
            {{ form.processing ? 'Sending...' : 'Resend verification email' }}
        </button>

        <Link href="/logout" method="post" as="button" class="mt-6 text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline">
            Log out
        </Link>
    </AuthLayout>
</template>
