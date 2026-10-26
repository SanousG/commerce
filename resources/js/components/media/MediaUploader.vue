<script setup lang="ts">
import axios from 'axios';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ maxImageMb: number }>();
const emit = defineEmits<{ uploaded: [asset: Record<string, unknown>] }>();
const file = ref<File | null>(null);
const title = ref('');
const altText = ref('');
const progress = ref(0);
const busy = ref(false);
const error = ref('');
const preview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const { t } = useI18n();

const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

const clearPreview = () => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
};

const selectFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const selected = input.files?.[0] ?? null;
    error.value = '';
    clearPreview();

    if (selected && selected.type && !allowedTypes.includes(selected.type)) {
        error.value = t('media.invalidType');
        input.value = '';
        file.value = null;
        return;
    }

    if (selected && selected.size > props.maxImageMb * 1024 * 1024) {
        error.value = t('media.tooLarge', { max: props.maxImageMb });
        input.value = '';
        file.value = null;
        return;
    }

    file.value = selected;
    preview.value = selected ? URL.createObjectURL(selected) : null;
};

const upload = async () => {
    if (!file.value || busy.value) return;

    busy.value = true;
    error.value = '';
    progress.value = 0;
    const data = new FormData();
    data.append('file', file.value);
    data.append('title', title.value);
    data.append('alt_text', altText.value);

    try {
        const response = await axios.post('/admin/media', data, {
            headers: { Accept: 'application/json' },
            onUploadProgress: (event) => { progress.value = event.total ? Math.round((event.loaded / event.total) * 100) : 0; },
        });
        emit('uploaded', response.data);
        title.value = '';
        altText.value = '';
        file.value = null;
        progress.value = 0;
        clearPreview();
        if (fileInput.value) fileInput.value.value = '';
    } catch (exception: unknown) {
        if (axios.isAxiosError(exception)) error.value = exception.response?.data?.errors?.file?.[0] ?? exception.response?.data?.message ?? t('media.uploadFailed');
        else error.value = t('media.uploadFailed');
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <form class="grid gap-4 rounded-md border border-dashed border-admin-border-strong bg-admin-canvas p-4" @submit.prevent="upload">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <img v-if="preview" :src="preview" :alt="t('media.preview')" class="size-20 rounded-md border border-admin-border object-cover" />
            <label class="min-w-0 flex-1 text-sm font-semibold text-admin-text">
                {{ t('media.imageFile') }}
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" required class="mt-1.5 block w-full min-w-0 text-xs text-admin-neutral file:mr-3 file:rounded-md file:border file:border-admin-border-strong file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-admin-text-secondary" @change="selectFile" />
                <span class="mt-1 block text-xs font-normal text-admin-neutral">{{ t('media.fileHelp', { max: maxImageMb }) }}</span>
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1.5 text-xs font-semibold text-admin-text-secondary">
                {{ t('media.uploadTitle') }} <span class="sr-only">{{ t('common.optional') }}</span>
                <input v-model="title" maxlength="160" :placeholder="t('common.optional')" class="admin-control w-full px-3 py-2 text-sm" />
            </label>
            <label class="grid gap-1.5 text-xs font-semibold text-admin-text-secondary">
                {{ t('media.uploadAlt') }} <span class="sr-only">{{ t('common.optional') }}</span>
                <input v-model="altText" maxlength="1000" :placeholder="t('common.optional')" class="admin-control w-full px-3 py-2 text-sm" />
            </label>
        </div>

        <p v-if="error" role="alert" class="text-sm font-medium text-admin-danger">{{ error }}</p>
        <p v-if="busy" role="status" class="text-sm text-admin-neutral">{{ t('media.uploading', { progress }) }}</p>
        <button :disabled="busy || !file" class="min-h-9 w-full rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-not-allowed disabled:opacity-50 sm:w-fit">
            {{ busy ? t('media.uploading', { progress }) : t('media.upload') }}
        </button>
    </form>
</template>
