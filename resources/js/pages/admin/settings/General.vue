<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

const props = defineProps<{ tenant: { name: string; slug: string; status: string } }>();
const page = usePage<PageProps>();
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('settings.manage') ?? false);
const form = useForm({ name: props.tenant.name });
</script>

<template>
    <Head title="Company settings" />
    <AdminLayout>
        <section class="max-w-3xl">
            <Link href="/admin/settings" class="text-sm font-semibold text-emerald-800 hover:underline">Settings</Link>
            <h1 class="mt-5 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Company.</h1>
            <p class="mt-4 text-[#516057]">Tenant settings describe the organization. Currency, timezone and locale belong to each operational Store.</p>

            <form class="mt-10 space-y-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="form.patch('/admin/settings/general', { preserveScroll: true })">
                <div>
                    <label for="company-name" class="mb-2 block text-sm font-semibold">Company name</label>
                    <input id="company-name" v-model="form.name" :disabled="!canManage" required maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 disabled:bg-black/5" />
                    <FieldError :message="form.errors.name" />
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-[#637067]">Slug</dt><dd class="mt-1 font-mono">{{ tenant.slug }}</dd></div>
                    <div><dt class="text-[#637067]">Status</dt><dd class="mt-1 font-semibold">{{ tenant.status }}</dd></div>
                </dl>
                <p v-if="page.props.status" class="text-sm font-semibold text-emerald-800">{{ page.props.status }}</p>
                <button v-if="canManage" type="submit" :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">Save company</button>
            </form>
        </section>
    </AdminLayout>
</template>
