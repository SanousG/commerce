<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';
interface Category { id: string; name: string; slug: string; status: string; position: number; parent: string | null; products_count: number }
interface PageLink { url: string | null; label: string; active: boolean }
defineProps<{ categories: { data: Category[]; links: PageLink[] } }>();
const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('categories.create'));
const canUpdate = computed(() => permissions.value.includes('categories.update'));
const canArchive = computed(() => permissions.value.includes('categories.archive'));
const archive = (category: Category) => { if (window.confirm(`Archive ${category.name}?`)) router.patch(`/admin/categories/${category.id}/archive`); };
</script>
<template><Head title="Categories" /><AdminLayout><section><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Catalog structure</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Categories.</h1></div><Link v-if="canCreate" href="/admin/categories/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Create category</Link></div><div class="mt-8 grid gap-3"><article v-for="category in categories.data" :key="category.id" class="flex flex-wrap items-center justify-between gap-4 border border-[#17211b]/15 bg-[#f8f5ed] p-5"><div><h2 class="text-xl font-semibold">{{ category.name }}</h2><p class="mt-1 text-sm text-[#637067]">{{ category.parent ? `Under ${category.parent}` : 'Root' }} / {{ category.status }} / {{ category.products_count }} products</p></div><div class="flex gap-4"><Link v-if="canUpdate" :href="`/admin/categories/${category.id}/edit`" class="font-semibold text-emerald-800">Edit</Link><button v-if="canArchive && category.status !== 'ARCHIVED'" class="font-semibold text-red-800" @click="archive(category)">Archive</button></div></article><p v-if="!categories.data.length" class="border border-dashed p-8 text-[#637067]">No categories yet.</p></div><nav class="mt-6 flex gap-2"><template v-for="link in categories.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : ''" v-html="link.label" /></template></nav></section></AdminLayout></template>
