<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface TenantOption {
    id: string;
    name: string;
    slug: string;
    role: 'OWNER' | 'ADMIN' | 'MEMBER';
}

defineProps<{
    tenants: TenantOption[];
}>();
</script>

<template>
    <Head title="Companies" />

    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Workspace access</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Choose a company.</h1>
                </div>
                <Link href="/admin/tenants/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-900">
                    Create company
                </Link>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <article v-for="tenant in tenants" :key="tenant.id" class="flex min-h-48 flex-col justify-between border border-[#17211b]/15 bg-[#f8f5ed] p-6">
                    <div>
                        <p class="font-mono text-xs tracking-[0.18em] text-[#637067] uppercase">{{ tenant.role }}</p>
                        <h2 class="mt-3 break-words text-2xl font-semibold tracking-[-0.03em]">{{ tenant.name }}</h2>
                        <p class="mt-2 text-sm text-[#637067]">{{ tenant.slug }}</p>
                    </div>
                    <Link
                        :href="`/admin/tenants/${tenant.id}/select`"
                        method="post"
                        as="button"
                        class="mt-8 w-full border border-[#17211b] px-4 py-3 text-sm font-semibold transition hover:bg-[#17211b] hover:text-white"
                    >
                        Enter company
                    </Link>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
