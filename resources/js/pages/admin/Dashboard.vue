<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import type { PageProps } from '../../types';

const page = usePage<PageProps>();
const membership = computed(() => page.props.currentTenantMembership);
const canCreateStore = computed(() => membership.value?.permissions.includes('stores.create') ?? false);
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout v-slot="{ user, currentTenant, currentStore }">
        <section class="max-w-3xl">
            <p class="mb-4 font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">
                Active workspace
            </p>
            <h1 class="break-words text-4xl font-semibold tracking-[-0.045em] sm:text-6xl">
                {{ currentStore?.name }}
            </h1>
            <p class="mt-5 max-w-xl text-base leading-7 text-[#516057]">
                Hello, {{ user?.name }}. Your session is operating within this company and store context.
            </p>

            <dl class="mt-12 grid gap-px overflow-hidden border border-[#17211b]/15 bg-[#17211b]/15 sm:grid-cols-3">
                <div class="bg-[#f8f5ed] p-6">
                    <dt class="text-xs font-semibold tracking-[0.18em] text-[#637067] uppercase">Store</dt>
                    <dd class="mt-3 break-words font-medium">{{ currentStore?.name }}</dd>
                </div>
                <div class="bg-[#f8f5ed] p-6">
                    <dt class="text-xs font-semibold tracking-[0.18em] text-[#637067] uppercase">Company</dt>
                    <dd class="mt-3 break-words font-medium">{{ currentTenant?.name }}</dd>
                </div>
                <div class="bg-[#f8f5ed] p-6">
                    <dt class="text-xs font-semibold tracking-[0.18em] text-[#637067] uppercase">Your role</dt>
                    <dd class="mt-3 break-all font-medium">{{ membership?.role }}</dd>
                </div>
            </dl>

            <div class="mt-8 flex flex-wrap gap-3">
                <Link href="/admin/tenants" class="border border-[#17211b]/25 px-5 py-3 text-sm font-semibold transition hover:border-emerald-800 hover:text-emerald-800">
                    Change company
                </Link>
                <Link href="/admin/tenants/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-900">
                    Create company
                </Link>
                <Link href="/admin/stores" class="border border-[#17211b]/25 px-5 py-3 text-sm font-semibold transition hover:border-emerald-800 hover:text-emerald-800">
                    Change store
                </Link>
                <Link v-if="canCreateStore" href="/admin/stores/create" class="bg-emerald-800 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-900">
                    Create store
                </Link>
            </div>
        </section>
    </AdminLayout>
</template>
