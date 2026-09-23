<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface RoleSummary {
    key: 'OWNER' | 'ADMIN' | 'MEMBER';
    name: string;
    permissions: string[];
}

defineProps<{
    tenant: { name: string };
    currentRole: RoleSummary['key'];
    permissions: string[];
    roles: RoleSummary[];
}>();
</script>

<template>
    <Head title="Access" />

    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Access control / {{ tenant.name }}</p>
            <div class="mt-4 grid gap-8 border-b border-[#17211b]/15 pb-10 lg:grid-cols-[1fr_auto] lg:items-end">
                <h1 class="max-w-3xl text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Your effective permissions.</h1>
                <div class="border border-emerald-800 bg-emerald-50 px-5 py-4">
                    <p class="text-xs font-semibold tracking-[0.16em] text-emerald-800 uppercase">Current role</p>
                    <p class="mt-1 text-xl font-semibold">{{ currentRole }}</p>
                </div>
            </div>

            <div class="mt-10 flex flex-wrap gap-2">
                <code v-for="permission in permissions" :key="permission" class="border border-[#17211b]/15 bg-[#f8f5ed] px-3 py-2 text-sm">
                    {{ permission }}
                </code>
            </div>

            <div class="mt-16 grid gap-px overflow-hidden border border-[#17211b]/15 bg-[#17211b]/15 lg:grid-cols-3">
                <article v-for="role in roles" :key="role.key" class="bg-[#f8f5ed] p-6">
                    <p class="font-mono text-xs tracking-[0.18em] text-emerald-800 uppercase">{{ role.key }}</p>
                    <h2 class="mt-3 text-2xl font-semibold">{{ role.name }}</h2>
                    <ul class="mt-6 space-y-2 text-sm text-[#516057]">
                        <li v-for="permission in role.permissions" :key="permission">{{ permission }}</li>
                    </ul>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
