<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import MediaThumbnail from '../../../components/media/MediaThumbnail.vue';
import MediaUploader from '../../../components/media/MediaUploader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Asset { id: string; title: string | null; alt_text: string | null; original_filename: string; mime_type: string; size_bytes: number; width: number; height: number; usage_count: number; created_at: string; thumbnail_url: string }
interface Page<T> { data: T[]; links: Array<{ url: string | null; label: string; active: boolean }>; from: number | null; to: number | null; total: number }
const props = defineProps<{ media: Page<Asset>; filters: { search: string; type: string }; limits: { max_image_mb: number } }>();
const search = ref(props.filters.search);
const apply = () => router.get('/admin/media', { search: search.value, type: 'IMAGE' }, { preserveState: true });
const remove = (asset: Asset) => { if (confirm(`Delete ${asset.title || asset.original_filename}?`)) router.delete(`/admin/media/${asset.id}`); };
const uploaded = () => router.reload({ only: ['media'] });
const bytes = (value: number) => value < 1024 * 1024 ? `${Math.round(value / 1024)} KB` : `${(value / 1024 / 1024).toFixed(1)} MB`;
</script>

<template>
    <Head title="Media library" /><AdminLayout><section><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Tenant-wide assets</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Media library</h1><p class="mt-3 text-[#637067]">Private normalized images reusable across products. Maximum {{ limits.max_image_mb }} MB.</p>
        <div class="mt-8"><MediaUploader @uploaded="uploaded" /></div>
        <form class="mt-8 flex gap-2" @submit.prevent="apply"><input v-model="search" placeholder="Search title, ALT, or filename" class="min-w-0 flex-1 border bg-white px-4 py-3" /><button class="bg-[#17211b] px-5 py-3 font-semibold text-white">Search</button></form>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><article v-for="asset in media.data" :key="asset.id" class="flex gap-4 border border-[#17211b]/15 bg-[#f8f5ed] p-4"><MediaThumbnail :src="asset.thumbnail_url" :alt="asset.alt_text" size="lg" /><div class="min-w-0 flex-1"><h2 class="truncate font-semibold">{{ asset.title || asset.original_filename }}</h2><p class="truncate text-xs text-[#637067]">{{ asset.original_filename }}</p><p class="mt-2 text-xs">{{ asset.width }}×{{ asset.height }} · {{ bytes(asset.size_bytes) }} · {{ asset.mime_type }}</p><p class="mt-1 text-xs">Used {{ asset.usage_count }} time(s)</p><button class="mt-3 text-xs font-semibold text-red-800 disabled:opacity-40" :disabled="asset.usage_count > 0" @click="remove(asset)">Delete</button></div></article></div>
        <p v-if="!media.data.length" class="mt-8 text-[#637067]">No images found.</p><div class="mt-8 flex flex-wrap gap-2"><template v-for="link in media.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="border px-3 py-2 text-sm" :class="{ 'bg-[#17211b] text-white': link.active }" v-html="link.label" /><span v-else class="border px-3 py-2 text-sm opacity-40" v-html="link.label" /></template></div>
    </section></AdminLayout>
</template>
