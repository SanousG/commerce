<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import MediaThumbnail from '../../../components/media/MediaThumbnail.vue';
import MediaUploader from '../../../components/media/MediaUploader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Asset {
    id: string;
    type: string;
    title: string | null;
    alt_text: string | null;
    original_filename: string;
    mime_type: string;
    extension: string;
    size_bytes: number;
    width: number;
    height: number;
    checksum: string;
    created_at: string;
    usage_count: number;
    thumbnail_url: string;
    file_url: string;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface MediaPage {
    data: Asset[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    media: MediaPage;
    filters: { search: string; type: string };
    limits: { max_image_mb: number };
}>();

const page = usePage<PageProps>();
const { t, locale } = useI18n();

const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpload = computed(() => permissions.value.includes('media.upload'));
const canManage = computed(() => permissions.value.includes('media.manage'));
const canDelete = computed(() => permissions.value.includes('media.delete'));

const search = ref(props.filters.search);
const uploadOpen = ref(false);
const notice = ref<string | null>(null);
const actionError = ref<string | null>(null);
const deletingId = ref<string | null>(null);
const selected = ref<Asset | null>(null);
const detail = computed(() => selected.value as Asset);
const detailsDialog = ref<HTMLDialogElement | null>(null);
const form = useForm({ title: '', alt_text: '' });

const displayNotice = computed(() => notice.value);

watch(() => page.url, () => {
    notice.value = null;
    actionError.value = null;
});

const applySearch = () => {
    notice.value = null;
    actionError.value = null;
    router.get('/admin/media', { search: search.value, type: 'IMAGE' }, { preserveState: true, preserveScroll: true, replace: true });
};

const clearSearch = () => {
    search.value = '';
    applySearch();
};

const openUpload = () => {
    actionError.value = null;
    uploadOpen.value = !uploadOpen.value;
};

const uploaded = () => {
    notice.value = t('media.uploaded');
    actionError.value = null;
    uploadOpen.value = false;
    router.reload({ only: ['media'] });
};

const openDetails = (asset: Asset) => {
    selected.value = asset;
    actionError.value = null;
    form.title = asset.title ?? '';
    form.alt_text = asset.alt_text ?? '';
    form.clearErrors();
    detailsDialog.value?.showModal();
};

const closeDetails = () => {
    detailsDialog.value?.close();
};

const onDialogClose = () => {
    selected.value = null;
    form.reset();
    form.clearErrors();
};

const saveDetails = () => {
    if (!selected.value || !canManage.value) return;

    notice.value = null;
    form.patch(`/admin/media/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = t('media.updated');
            detailsDialog.value?.close();
        },
    });
};

const remove = async (asset: Asset) => {
    if (!canDelete.value) return;
    if (!window.confirm(t('media.deleteConfirm'))) return;

    deletingId.value = asset.id;
    actionError.value = null;
    notice.value = null;

    try {
        await axios.delete(`/admin/media/${asset.id}`, { headers: { Accept: 'application/json' } });
        notice.value = t('media.deleted');
        if (selected.value?.id === asset.id) detailsDialog.value?.close();
        router.reload({ only: ['media'] });
    } catch (exception: unknown) {
        const status = axios.isAxiosError(exception) ? exception.response?.status : undefined;
        actionError.value = status === 409 ? t('media.deleteInUse') : t('media.deleteError');
    } finally {
        deletingId.value = null;
    }
};

const formatBytes = (value: number) => {
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
};

const formatDimensions = (asset: Asset) => `${asset.width} × ${asset.height}`;

const formatDate = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(value));
</script>

<template>
    <Head :title="t('media.title')" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-[90rem] space-y-5 sm:space-y-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('media.title') }]" />

            <PageHeader :title="t('media.title')" :eyebrow="t('common.allCompany')" :description="t('media.description', { max: limits.max_image_mb })">
                <template #actions>
                    <button
                        v-if="canUpload"
                        type="button"
                        :aria-expanded="uploadOpen"
                        aria-controls="media-upload"
                        class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover"
                        @click="openUpload"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0L7 9m5-5 5 5M5 20h14" />
                        </svg>
                        {{ uploadOpen ? t('common.cancel') : t('media.upload') }}
                    </button>
                </template>
            </PageHeader>

            <StatusNotice :message="displayNotice ?? undefined" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <div v-if="actionError && !selected" role="alert" class="flex items-start justify-between gap-3 rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3 text-sm text-admin-danger">
                <p>{{ actionError }}</p>
                <button type="button" :aria-label="t('media.close')" class="shrink-0 text-base leading-none" @click="actionError = null">×</button>
            </div>

            <section v-if="uploadOpen && canUpload" id="media-upload" class="admin-card p-4 sm:p-5">
                <h2 class="text-sm font-semibold text-admin-text">{{ t('media.upload') }}</h2>
                <div class="mt-3">
                    <MediaUploader :max-image-mb="limits.max_image_mb" @uploaded="uploaded" />
                </div>
            </section>

            <form class="flex flex-col gap-2 sm:flex-row sm:items-center" role="search" @submit.prevent="applySearch">
                <label class="min-w-0 flex-1">
                    <span class="sr-only">{{ t('media.searchLibrary') }}</span>
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('media.searchPlaceholder')"
                        class="admin-control w-full px-3 py-2 text-sm"
                    />
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="min-h-9 rounded-md bg-admin-secondary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary">
                        {{ t('common.search') }}
                    </button>
                    <button v-if="filters.search" type="button" class="min-h-9 rounded-md border border-admin-border-strong px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-admin-canvas" @click="clearSearch">
                        {{ t('media.clearSearch') }}
                    </button>
                </div>
            </form>

            <template v-if="media.data.length">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                    <article v-for="asset in media.data" :key="asset.id" class="admin-card flex min-w-0 flex-col overflow-hidden">
                        <MediaThumbnail fluid :src="asset.thumbnail_url" :alt="asset.alt_text" />
                        <div class="grid min-w-0 gap-1 p-2.5 sm:p-3">
                            <h2 class="truncate text-sm font-semibold leading-5 text-admin-text" :title="asset.title || asset.original_filename">
                                {{ asset.title || asset.original_filename }}
                            </h2>
                            <p class="truncate font-mono text-[10px] text-admin-neutral" :title="asset.original_filename">{{ asset.original_filename }}</p>
                            <p class="truncate font-mono text-[10px] text-admin-neutral">{{ formatDimensions(asset) }} · {{ formatBytes(asset.size_bytes) }}</p>
                            <span
                                class="mt-1 inline-flex w-fit items-center rounded border px-1.5 py-0.5 font-mono text-[10px] font-medium"
                                :class="asset.usage_count > 0 ? 'border-admin-warning-border bg-admin-warning-bg text-amber-700' : 'border-admin-border bg-slate-50 text-admin-text-secondary'"
                            >
                                {{ asset.usage_count > 0 ? t('media.usageCount', { count: asset.usage_count }) : t('media.unused') }}
                            </span>
                            <div class="mt-1.5 flex flex-wrap items-center justify-end gap-x-3 gap-y-1.5">
                                <button type="button" class="text-xs font-semibold text-admin-secondary hover:text-admin-primary" @click="openDetails(asset)">
                                    {{ t('media.details') }}
                                </button>
                                <button
                                    v-if="canDelete"
                                    type="button"
                                    :disabled="deletingId === asset.id"
                                    class="text-xs font-semibold text-admin-danger hover:underline disabled:cursor-wait disabled:opacity-50"
                                    @click="remove(asset)"
                                >
                                    {{ deletingId === asset.id ? t('media.deleting') : t('media.delete') }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <Pagination :links="media.links" :from="media.from" :to="media.to" :total="media.total" />
            </template>

            <EmptyState
                v-else
                :title="filters.search ? t('media.noResultsTitle') : t('media.emptyTitle')"
                :description="filters.search ? t('media.noResultsHelp') : t('media.emptyHelp')"
            >
                <template #actions>
                    <button v-if="filters.search" type="button" class="inline-flex min-h-9 items-center rounded-md border border-admin-border-strong px-4 text-sm font-semibold text-admin-text-secondary hover:bg-admin-canvas" @click="clearSearch">
                        {{ t('media.clearSearch') }}
                    </button>
                    <button v-else-if="canUpload" type="button" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover" @click="uploadOpen = true">
                        {{ t('media.upload') }}
                    </button>
                </template>
            </EmptyState>
        </section>

        <dialog
            ref="detailsDialog"
            aria-labelledby="media-details-title"
            class="m-auto max-h-[90vh] w-[calc(100vw-1.5rem)] max-w-[34rem] overflow-y-auto rounded-lg border border-admin-border bg-admin-surface p-0 text-admin-text shadow-xl backdrop:bg-slate-900/40"
            @close="onDialogClose"
        >
            <form v-if="selected" @submit.prevent="saveDetails">
                <header class="flex items-start justify-between gap-3 border-b border-admin-border px-4 py-4 sm:px-5">
                    <div class="min-w-0">
                        <p class="admin-label">{{ t('media.detailsTitle') }}</p>
                        <h2 id="media-details-title" class="mt-1 truncate text-lg font-semibold" :title="detail.title || detail.original_filename">
                            {{ detail.title || detail.original_filename }}
                        </h2>
                    </div>
                    <button type="button" class="shrink-0 text-xl leading-none text-admin-neutral hover:text-admin-text" :aria-label="t('media.close')" @click="closeDetails">×</button>
                </header>

                <div class="grid gap-5 px-4 py-4 sm:px-5">
                    <MediaThumbnail fluid :src="detail.thumbnail_url" :alt="detail.alt_text" class="mx-auto max-w-xs" />

                    <div v-if="actionError" role="alert" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-3 py-2 text-xs text-admin-danger">
                        {{ actionError }}
                    </div>

                    <div class="grid gap-3">
                        <label class="grid gap-1.5 text-xs font-semibold text-admin-text-secondary">
                            {{ t('media.editTitle') }}
                            <input
                                v-model="form.title"
                                maxlength="160"
                                :disabled="!canManage"
                                :aria-invalid="Boolean(form.errors.title)"
                                :aria-describedby="form.errors.title ? 'media-title-error' : undefined"
                                class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50"
                            />
                        </label>
                        <FieldError id="media-title-error" :message="form.errors.title" />

                        <label class="grid gap-1.5 text-xs font-semibold text-admin-text-secondary">
                            {{ t('media.editAlt') }}
                            <textarea
                                v-model="form.alt_text"
                                rows="3"
                                maxlength="1000"
                                :disabled="!canManage"
                                :aria-invalid="Boolean(form.errors.alt_text)"
                                :aria-describedby="form.errors.alt_text ? 'media-alt-error' : undefined"
                                class="admin-control w-full px-3 py-2 text-sm disabled:bg-slate-50"
                            />
                        </label>
                        <FieldError id="media-alt-error" :message="form.errors.alt_text" />
                    </div>

                    <section>
                        <h3 class="admin-label">{{ t('media.technicalMetadata') }}</h3>
                        <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="text-admin-neutral">{{ t('media.originalFilename') }}</dt>
                                <dd class="break-all font-mono text-admin-text-secondary">{{ detail.original_filename }}</dd>
                            </div>
                            <div>
                                <dt class="text-admin-neutral">{{ t('media.mimeType') }}</dt>
                                <dd class="font-mono text-admin-text-secondary">{{ detail.mime_type }}</dd>
                            </div>
                            <div>
                                <dt class="text-admin-neutral">{{ t('media.dimensions') }}</dt>
                                <dd class="font-mono text-admin-text-secondary">{{ formatDimensions(detail) }}</dd>
                            </div>
                            <div>
                                <dt class="text-admin-neutral">{{ t('media.fileSize') }}</dt>
                                <dd class="font-mono text-admin-text-secondary">{{ formatBytes(detail.size_bytes) }}</dd>
                            </div>
                            <div>
                                <dt class="text-admin-neutral">{{ t('media.createdAt') }}</dt>
                                <dd class="text-admin-text-secondary">{{ formatDate(detail.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-admin-neutral">{{ t('media.catalogReferences') }}</dt>
                                <dd class="font-mono text-admin-text-secondary">{{ detail.usage_count }}</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-admin-neutral">{{ t('media.catalogReferencesHelp', { count: detail.usage_count }) }}</p>
                    </section>

                    <section class="rounded-md border border-admin-border bg-admin-canvas p-3">
                        <h3 class="admin-label">{{ t('media.deletionPolicy') }}</h3>
                        <p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('media.deletionBlocked') }}</p>
                    </section>
                </div>

                <footer class="flex flex-col-reverse gap-2 border-t border-admin-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <button
                        v-if="canDelete"
                        type="button"
                        :disabled="deletingId === detail.id"
                        class="min-h-9 rounded-md border border-admin-danger-border px-4 text-sm font-semibold text-admin-danger transition hover:bg-admin-danger-bg disabled:cursor-wait disabled:opacity-50"
                        @click="remove(detail)"
                    >
                        {{ deletingId === detail.id ? t('media.deleting') : t('media.delete') }}
                    </button>
                    <div class="flex justify-end gap-2 sm:ml-auto">
                        <button type="button" class="min-h-9 rounded-md border border-admin-border-strong px-4 text-sm font-semibold text-admin-text-secondary transition hover:bg-admin-canvas" @click="closeDetails">
                            {{ t('common.cancel') }}
                        </button>
                        <button v-if="canManage" type="submit" :disabled="form.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-50">
                            {{ form.processing ? t('common.saving') : t('common.saveChanges') }}
                        </button>
                    </div>
                </footer>
            </form>
        </dialog>
    </AdminLayout>
</template>
