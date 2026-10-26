<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import MediaThumbnail from './MediaThumbnail.vue';
import MediaUploader from './MediaUploader.vue';

export interface PickerMedia {
    id: string;
    title: string | null;
    original_filename: string;
    thumbnail_url: string;
    width: number;
    height: number;
    alt_text: string | null;
}

defineProps<{ selectedIds?: string[]; allowUpload?: boolean; disabled?: boolean; maxImageMb: number }>();
const emit = defineEmits<{ select: [asset: PickerMedia] }>();
const assets = ref<PickerMedia[]>([]);
const search = ref('');
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const error = ref('');
const { t } = useI18n();

const load = async (requestedPage = 1) => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/admin/media', {
            params: { search: search.value, type: 'IMAGE', page: requestedPage },
            headers: { Accept: 'application/json' },
        });
        assets.value = data.data;
        page.value = data.current_page;
        lastPage.value = data.last_page;
    } catch {
        error.value = t('media.loadError');
    } finally {
        loading.value = false;
    }
};
const uploaded = (asset: Record<string, unknown>) => {
    emit('select', asset as unknown as PickerMedia);
    load(1);
};

onMounted(() => load());
</script>

<template>
    <div class="grid gap-4">
        <MediaUploader v-if="allowUpload && !disabled" :max-image-mb="maxImageMb" @uploaded="uploaded" />

        <div class="flex flex-col gap-2 sm:flex-row">
            <label class="min-w-0 flex-1">
                 <span class="sr-only">{{ t('media.searchLibrary') }}</span>
                 <input v-model="search" type="search" :disabled="disabled" :placeholder="t('media.searchPlaceholder')" class="admin-control w-full px-3 py-2 text-sm disabled:cursor-wait disabled:bg-slate-50" @keyup.enter.prevent="load(1)" />
            </label>
            <button type="button" :disabled="loading || disabled" class="min-h-9 rounded-md border border-admin-border-strong bg-admin-surface px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60" @click="load(1)">
                 {{ t('common.search') }}
            </button>
        </div>

        <p v-if="loading" role="status" class="text-sm text-admin-neutral">{{ t('media.loading') }}</p>
        <p v-else-if="error" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-3 py-2 text-sm text-admin-danger">{{ error }}</p>
        <p v-else-if="!assets.length" class="rounded-md border border-dashed border-admin-border-strong bg-admin-canvas px-4 py-5 text-center text-sm text-admin-neutral">{{ t('media.noMatch') }}</p>
        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            <button
                v-for="asset in assets"
                :key="asset.id"
                type="button"
                :disabled="disabled || selectedIds?.includes(asset.id)"
                :aria-label="t('media.select') + ': ' + (asset.title || asset.original_filename)"
                class="grid min-w-0 justify-items-center gap-2 rounded-md border border-admin-border bg-admin-surface p-2 text-center text-xs text-admin-text-secondary transition hover:border-admin-secondary hover:bg-blue-50/40 disabled:cursor-not-allowed disabled:opacity-40"
                @click="emit('select', asset)"
            >
                <MediaThumbnail :src="asset.thumbnail_url" :alt="asset.alt_text" />
                <span class="line-clamp-2 break-all">{{ asset.title || asset.original_filename }}</span>
                <span class="font-mono text-[10px] text-admin-neutral">{{ asset.width }}×{{ asset.height }}</span>
            </button>
        </div>

        <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-admin-border pt-3 text-xs">
            <button type="button" :disabled="page <= 1 || loading || disabled" class="min-h-9 rounded-md border border-admin-border bg-white px-3 font-semibold text-admin-text-secondary disabled:cursor-not-allowed disabled:opacity-40" @click="load(page - 1)">{{ t('pagination.previous') }}</button>
            <span class="font-mono text-admin-neutral">{{ page }} / {{ lastPage }}</span>
            <button type="button" :disabled="page >= lastPage || loading || disabled" class="min-h-9 rounded-md border border-admin-border bg-white px-3 font-semibold text-admin-text-secondary disabled:cursor-not-allowed disabled:opacity-40" @click="load(page + 1)">{{ t('pagination.next') }}</button>
        </div>
    </div>
</template>
