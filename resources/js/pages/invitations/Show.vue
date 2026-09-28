<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import type { PageProps } from '../../types';

defineProps<{ token: string | null; valid: boolean; invitation: { tenant: string; role: string; expires_at: string; email_matches: boolean } | null }>();
const page = usePage<PageProps>();
const form = useForm({});
const errors = computed(() => form.errors as Record<string, string | undefined>);
</script>

<template>
    <Head title="Company invitation" />
    <AuthLayout eyebrow="Team invitation" :title="valid ? `Join ${invitation?.tenant}` : 'Invitation unavailable'" :subtitle="valid ? `You were invited as ${invitation?.role}.` : 'This invitation is invalid, revoked, or expired.'">
        <template v-if="valid && !page.props.auth.user">
            <p class="text-sm leading-6 text-[#516057]">Sign in or create an account with the invited email address. You will return here after authentication and email verification.</p>
            <div class="mt-6 grid gap-3 sm:grid-cols-2"><Link href="/login" class="bg-[#17211b] px-5 py-3 text-center text-sm font-semibold text-white">Sign in</Link><Link href="/register" class="border border-[#17211b] px-5 py-3 text-center text-sm font-semibold">Create account</Link></div>
        </template>
        <template v-else-if="valid && invitation?.email_matches">
            <form @submit.prevent="form.post(`/invitations/${token}/accept`)"><button type="submit" :disabled="form.processing" class="w-full bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">Accept invitation</button><p v-if="errors.invitation" class="mt-3 text-sm text-red-700">{{ errors.invitation }}</p></form>
        </template>
        <p v-else-if="valid" class="border border-red-800/20 bg-red-50 p-4 text-sm text-red-800">This invitation was sent to another address.</p>
        <Link v-else :href="page.props.auth.user ? '/admin/tenants' : '/login'" class="block border border-[#17211b] px-5 py-3 text-center text-sm font-semibold">Continue</Link>
    </AuthLayout>
</template>
