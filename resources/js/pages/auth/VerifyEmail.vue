<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusNotice from '../../components/StatusNotice.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import type { PageProps } from '../../types';

const page = usePage<PageProps>();
const sent = computed(() => page.props.status === 'verification-link-sent');
const { t } = useI18n();
const form = useForm({});

const resend = () => form.post('/email/verification-notification');
</script>

<template>
    <Head :title="t('auth.verify.head')" />

    <AuthLayout
        :eyebrow="t('auth.verify.eyebrow')"
        :title="t('auth.verify.title')"
        :subtitle="t('auth.verify.subtitle')"
    >
        <StatusNotice
            v-if="sent"
            :message="t('auth.verify.sent')"
        />

        <div class="mb-6 rounded-lg border border-admin-border bg-admin-canvas px-4 py-4">
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-blue-100 text-admin-secondary" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5"><path d="M4 6h16v12H4V6Z" stroke-width="1.7"/><path d="m4 7 8 6 8-6" stroke-width="1.7" stroke-linejoin="round"/></svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-admin-text">{{ t('auth.verify.checkInbox') }}</p>
                    <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('auth.verify.help') }}</p>
                </div>
            </div>
        </div>

        <button
            type="button"
            :disabled="form.processing"
            class="auth-primary-button"
            @click="resend"
        >
            <svg v-if="form.processing" viewBox="0 0 24 24" fill="none" class="mr-2 size-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            {{ form.processing ? t('auth.common.sending') : t('auth.verify.resend') }}
        </button>

        <div class="mt-6 border-t border-admin-border pt-5 text-center">
            <Link href="/logout" method="post" as="button" class="auth-link text-sm">
                {{ t('auth.common.logout') }}
            </Link>
        </div>
    </AuthLayout>
</template>
