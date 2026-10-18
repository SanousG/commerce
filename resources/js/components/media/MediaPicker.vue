<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref } from 'vue';
import MediaThumbnail from './MediaThumbnail.vue';
import MediaUploader from './MediaUploader.vue';

export interface PickerMedia { id: string; title: string | null; original_filename: string; thumbnail_url: string; width: number; height: number; alt_text: string | null }
defineProps<{ selectedIds?: string[]; allowUpload?: boolean }>();
const emit = defineEmits<{ select: [asset: PickerMedia] }>();
const assets = ref<PickerMedia[]>([]); const search = ref(''); const page = ref(1); const lastPage = ref(1); const loading = ref(false);
const load = async (requestedPage = 1) => { loading.value = true; try { const { data } = await axios.get('/admin/media', { params: { search: search.value, type: 'IMAGE', page: requestedPage }, headers: { Accept: 'application/json' } }); assets.value = data.data; page.value = data.current_page; lastPage.value = data.last_page; } finally { loading.value = false; } };
const uploaded = (asset: Record<string, unknown>) => { emit('select', asset as unknown as PickerMedia); load(1); };
onMounted(() => load());
</script>

<template>
    <div class="grid gap-4">
        <MediaUploader v-if="allowUpload" @uploaded="uploaded" />
        <div class="flex gap-2"><input v-model="search" placeholder="Search library" class="min-w-0 flex-1 border bg-white px-3 py-2" @keyup.enter="load(1)" /><button type="button" class="border px-4 py-2 text-sm font-semibold" @click="load(1)">Search</button></div>
        <p v-if="loading" class="text-sm text-[#637067]">Loading images…</p>
        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4"><button v-for="asset in assets" :key="asset.id" type="button" :disabled="selectedIds?.includes(asset.id)" class="grid justify-items-center gap-2 border bg-white p-2 text-center text-xs disabled:opacity-40" @click="emit('select', asset)"><MediaThumbnail :src="asset.thumbnail_url" :alt="asset.alt_text" /><span class="line-clamp-2">{{ asset.title || asset.original_filename }}</span></button></div>
        <div class="flex items-center justify-between text-sm"><button type="button" :disabled="page <= 1" @click="load(page - 1)">Previous</button><span>{{ page }} / {{ lastPage }}</span><button type="button" :disabled="page >= lastPage" @click="load(page + 1)">Next</button></div>
    </div>
</template>
