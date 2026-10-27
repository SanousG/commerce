<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
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

defineProps<{ stores: StoreOption[] }>();

const page = usePage<PageProps>();
const { t } = useI18n();
const selectingStore = ref<string | null>(null);
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreateStore = computed(() => permissions.value.includes('stores.create'));
const canUpdateStore = computed(() => permissions.value.includes('stores.update'));
const canSelectStore = computed(() => permissions.value.includes('stores.select'));
const statusMessage = computed(() => {
    const status = page.props.status;
    if (!status) return null;

    return status === 'Store settings updated.' ? t('stores.flash.updated') : status;
});

const selectStore = (store: StoreOption) => {
    if (selectingStore.value !== null || store.status !== 'ACTIVE' || !canSelectStore.value) return;
    selectingStore.value = store.id;
    router.post(`/admin/stores/${store.id}/select`, {}, {
        onFinish: () => { selectingStore.value = null; },
    });
};
</script>

<template>
    <Head :title="t('stores.title')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-6xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('stores.title') }]" />
            <PageHeader :title="t('stores.title')" :eyebrow="t('stores.eyebrow')" :description="t('stores.description')">
                <template v-if="canCreateStore" #actions>
                    <Link href="/admin/stores/create" class="inline-flex min-h-9 w-full items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white sm:w-auto">{{ t('stores.create') }}</Link>
                </template>
            </PageHeader>

            <p v-if="statusMessage" role="status" class="rounded-md border border-admin-success-border bg-admin-success-bg px-4 py-3 text-sm font-medium text-admin-success">{{ statusMessage }}</p>

            <div v-if="stores.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="store in stores" :key="store.id" class="admin-card flex min-w-0 flex-col p-4 sm:p-5" :class="store.selected ? 'ring-2 ring-admin-secondary/40' : ''">
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="admin-label">{{ t('stores.store') }}</p>
                            <h2 class="mt-1.5 break-words text-lg font-semibold text-admin-primary">{{ store.name }}</h2>
                            <p class="mt-1 break-all font-mono text-[11px] text-admin-neutral">{{ store.slug }}</p>
                        </div>
                        <div class="grid shrink-0 justify-items-end gap-1.5">
                            <StatusBadge :status="store.status" />
                            <span v-if="store.selected" class="rounded bg-admin-primary px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ t('common.currentStore') }}</span>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-admin-border pt-4 text-xs">
                        <div><dt class="admin-label">{{ t('stores.currency') }}</dt><dd class="mt-1 font-mono text-admin-text-secondary">{{ store.currency }}</dd></div>
                        <div><dt class="admin-label">{{ t('stores.locale') }}</dt><dd class="mt-1 font-mono text-admin-text-secondary">{{ store.locale }}</dd></div>
                        <div class="col-span-2"><dt class="admin-label">{{ t('stores.timezone') }}</dt><dd class="mt-1 break-all font-mono text-admin-text-secondary">{{ store.timezone }}</dd></div>
                    </dl>

                    <div class="mt-auto grid gap-2 border-t border-admin-border pt-4 sm:grid-cols-2">
                        <button v-if="canSelectStore && store.status === 'ACTIVE'" type="button" :disabled="selectingStore !== null" class="min-h-9 rounded-md bg-admin-primary px-3 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60 sm:col-span-2" @click="selectStore(store)">
                            {{ selectingStore === store.id ? t('stores.selecting') : (store.selected ? t('stores.enterCurrent') : t('stores.select')) }}
                        </button>
                        <Link :href="`/admin/stores/${store.id}/settings`" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-3 text-sm font-semibold text-admin-text-secondary">{{ t('stores.openSettings') }}</Link>
                        <Link v-if="canUpdateStore && store.status !== 'ARCHIVED'" :href="`/admin/stores/${store.id}/edit`" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-3 text-sm font-semibold text-admin-text-secondary">{{ t('stores.edit') }}</Link>
                    </div>
                    <p v-if="store.status !== 'ACTIVE'" class="mt-3 text-xs leading-5 text-admin-neutral">{{ t('stores.inactiveSelectionHelp') }}</p>
                </article>
            </div>

            <EmptyState v-else :title="t('stores.empty')" :description="canCreateStore ? t('stores.emptyHelp') : t('stores.emptyReadOnlyHelp')">
                <template v-if="canCreateStore" #actions><Link href="/admin/stores/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('stores.create') }}</Link></template>
            </EmptyState>

            <div class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3 text-xs leading-5 text-admin-info">
                <p class="font-semibold">{{ t('stores.contextTitle') }}</p>
                <p class="mt-0.5">{{ t('stores.contextHelp') }}</p>
            </div>
        </section>
    </AdminLayout>
</template>
