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

        <button
            type="button"
            :disabled="form.processing"
            class="w-full bg-[#17211b] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-60"
            @click="resend"
        >
            {{ form.processing ? t('auth.common.sending') : t('auth.verify.resend') }}
        </button>

        <Link href="/logout" method="post" as="button" class="mt-6 text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline">
            {{ t('auth.common.logout') }}
        </Link>
    </AuthLayout>
</template>
