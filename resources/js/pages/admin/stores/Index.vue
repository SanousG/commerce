<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface StoreOption {
    id: string;
    name: string;
    slug: string;
    status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    currency: string;
    timezone: string;
    locale: string;
    selected: boolean;
}

defineProps<{
    stores: StoreOption[];
}>();

const page = usePage<PageProps>();
const canCreateStore = computed(() => page.props.currentTenantMembership?.permissions.includes('stores.create') ?? false);
const canUpdateStore = computed(() => page.props.currentTenantMembership?.permissions.includes('stores.update') ?? false);
const canSelectStore = computed(() => page.props.currentTenantMembership?.permissions.includes('stores.select') ?? false);
</script>

<template>
    <Head title="Stores" />

    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Company stores</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Choose a store.</h1>
                </div>
                <Link v-if="canCreateStore" href="/admin/stores/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-900">
                    Create store
                </Link>
            </div>

            <p v-if="page.props.status" class="mt-6 border border-emerald-800/25 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ page.props.status }}</p>

            <div v-if="stores.length" class="mt-10 grid gap-4 sm:grid-cols-2">
                <article v-for="store in stores" :key="store.id" class="flex min-h-56 flex-col justify-between border p-6" :class="store.selected ? 'border-emerald-700 bg-emerald-50' : 'border-[#17211b]/15 bg-[#f8f5ed]'">
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-mono text-xs tracking-[0.18em] text-[#637067] uppercase">{{ store.status }}</p>
                            <span v-if="store.selected" class="bg-emerald-800 px-2 py-1 text-xs font-semibold text-white uppercase">Current</span>
                        </div>
                        <h2 class="mt-3 break-words text-2xl font-semibold tracking-[-0.03em]">{{ store.name }}</h2>
                        <p class="mt-2 text-sm text-[#637067]">{{ store.slug }}</p>
                        <p class="mt-5 text-sm text-[#516057]">{{ store.currency }} / {{ store.timezone }} / {{ store.locale }}</p>
                    </div>
                    <div v-if="(canSelectStore && store.status === 'ACTIVE') || canUpdateStore" class="mt-8 grid gap-2 sm:grid-cols-2">
                        <Link v-if="canSelectStore && store.status === 'ACTIVE'" :href="`/admin/stores/${store.id}/select`" method="post" as="button" class="border border-[#17211b] px-4 py-3 text-center text-sm font-semibold transition hover:bg-[#17211b] hover:text-white">{{ store.selected ? 'Enter current store' : 'Enter store' }}</Link>
                        <Link v-if="canUpdateStore && store.status !== 'ARCHIVED'" :href="`/admin/stores/${store.id}/edit`" class="border border-[#17211b]/25 px-4 py-3 text-center text-sm font-semibold hover:border-[#17211b]">Edit settings</Link>
                    </div>
                    <p v-else class="mt-8 border border-[#17211b]/15 px-4 py-3 text-center text-sm text-[#637067]">Unavailable</p>
                </article>
            </div>

            <div v-else class="mt-10 border border-dashed border-[#17211b]/25 p-8 text-[#516057]">
                This company has no stores yet.
            </div>
        </section>
    </AdminLayout>
</template>
