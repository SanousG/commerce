<script setup lang="ts">
import axios from 'axios';
import { ref } from 'vue';

const emit = defineEmits<{ uploaded: [asset: Record<string, unknown>] }>();
const file = ref<File | null>(null);
const title = ref('');
const altText = ref('');
const progress = ref(0);
const busy = ref(false);
const error = ref('');
const preview = ref<string | null>(null);

const selectFile = (event: Event) => {
    const selected = (event.target as HTMLInputElement).files?.[0] ?? null;
    file.value = selected;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = selected ? URL.createObjectURL(selected) : null;
};
const upload = async () => {
    if (!file.value) return;
    busy.value = true; error.value = ''; progress.value = 0;
    const data = new FormData(); data.append('file', file.value); data.append('title', title.value); data.append('alt_text', altText.value);
    try {
        const response = await axios.post('/admin/media', data, { headers: { Accept: 'application/json' }, onUploadProgress: (event) => { progress.value = event.total ? Math.round((event.loaded / event.total) * 100) : 0; } });
        emit('uploaded', response.data); title.value = ''; altText.value = ''; file.value = null; progress.value = 0;
        if (preview.value) URL.revokeObjectURL(preview.value); preview.value = null;
    } catch (exception: unknown) {
        if (axios.isAxiosError(exception)) error.value = exception.response?.data?.errors?.file?.[0] ?? exception.response?.data?.message ?? 'Upload failed.';
        else error.value = 'Upload failed.';
    } finally { busy.value = false; }
};
</script>

<template>
    <form class="grid gap-3 border border-dashed border-[#17211b]/30 bg-[#f8f5ed] p-4" @submit.prevent="upload">
        <div class="flex flex-wrap items-center gap-4"><img v-if="preview" :src="preview" alt="Preview" class="size-20 object-cover" /><input type="file" accept="image/jpeg,image/png,image/webp" required @change="selectFile" /></div>
        <div class="grid gap-3 sm:grid-cols-2"><input v-model="title" maxlength="160" placeholder="Administrative title (optional)" class="border bg-white px-3 py-2" /><input v-model="altText" maxlength="1000" placeholder="Default ALT text (optional)" class="border bg-white px-3 py-2" /></div>
        <p v-if="error" class="text-sm text-red-800">{{ error }}</p><p v-if="busy" class="text-sm">Uploading… {{ progress }}%</p>
        <button :disabled="busy || !file" class="w-fit bg-[#17211b] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Upload image</button>
    </form>
</template>
