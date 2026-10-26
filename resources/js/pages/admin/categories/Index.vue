<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface CategoryRow {
    id: string;
    name: string;
    slug: string;
    status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    position: number;
    parent: string | null;
    products_count: number;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    categories: {
        data: CategoryRow[];
        links: PageLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    };
}>();

const page = usePage<PageProps>();
const archivingId = ref<string | null>(null);
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('categories.create'));
const canUpdate = computed(() => permissions.value.includes('categories.update'));
const canArchive = computed(() => permissions.value.includes('categories.archive'));
const { t } = useI18n();

const archive = (category: CategoryRow) => {
    if (!window.confirm(t('categories.archiveConfirm', { name: category.name }))) return;

    archivingId.value = category.id;
    router.patch(`/admin/categories/${category.id}/archive`, {}, {
        preserveScroll: true,
        onFinish: () => { archivingId.value = null; },
    });
};
</script>

<template>
    <Head :title="t('categories.title')" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-[90rem] space-y-5 sm:space-y-6">
            <Breadcrumbs :items="[{ label: t('nav.catalog') }, { label: t('categories.title') }]" />

            <PageHeader :title="t('categories.title')" :eyebrow="t('common.allCompany')" :description="t('categories.description')">
                <template #actions>
                    <Link v-if="canCreate" href="/admin/categories/create" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white transition hover:bg-admin-primary-hover">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>
                        {{ t('categories.create') }}
                    </Link>
                </template>
            </PageHeader>

            <StatusNotice :message="page.props.status" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <template v-if="categories.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs">
                        <thead>
                            <tr>
                                <th class="w-[28%] p-3">{{ t('categories.category') }}</th>
                                <th class="w-[22%] p-3">{{ t('categories.parent') }}</th>
                                <th class="w-[10%] p-3 text-right">{{ t('categories.position') }}</th>
                                <th class="w-[13%] p-3">{{ t('common.status') }}</th>
                                <th class="w-[12%] p-3 text-right">{{ t('categories.products') }}</th>
                                <th class="w-[15%] p-3 text-right">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-admin-border">
                            <tr v-for="category in categories.data" :key="category.id">
                                <td class="p-3">
                                    <p class="truncate text-sm font-semibold text-admin-text" :title="category.name">{{ category.name }}</p>
                                    <p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral" :title="category.slug">{{ category.slug }}</p>
                                </td>
                                <td class="p-3">
                                    <span v-if="category.parent" class="text-admin-text-secondary">{{ category.parent }}</span>
                                    <span v-else class="text-admin-neutral">{{ t('categories.root') }}</span>
                                </td>
                                <td class="p-3 text-right font-mono font-semibold tabular-nums text-admin-text-secondary">{{ category.position }}</td>
                                <td class="p-3"><StatusBadge :status="category.status" /></td>
                                <td class="p-3 text-right font-mono font-semibold tabular-nums text-admin-text">{{ category.products_count }}</td>
                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <Link :href="`/admin/categories/${category.id}/edit`" class="admin-link">{{ canUpdate ? t('common.edit') : t('common.view') }}</Link>
                                        <button
                                            v-if="canArchive && category.status !== 'ARCHIVED'"
                                            type="button"
                                            :disabled="archivingId === category.id"
                                            class="font-semibold text-admin-danger hover:underline disabled:cursor-wait disabled:opacity-50"
                                            @click="archive(category)"
                                        >
                                            {{ archivingId === category.id ? t('common.archiving') : t('common.archive') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-2.5 md:hidden">
                    <article v-for="category in categories.data" :key="`mobile-${category.id}`" class="admin-card p-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold leading-5 text-admin-text">{{ category.name }}</h2>
                                <p class="mt-0.5 truncate font-mono text-[10px] text-admin-neutral" :title="category.slug">{{ category.slug }}</p>
                            </div>
                            <StatusBadge :status="category.status" />
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2.5 border-y border-admin-border py-2.5">
                            <div class="col-span-2">
                                <dt class="admin-label">{{ t('categories.parent') }}</dt>
                                <dd class="mt-1 truncate text-xs text-admin-text-secondary" :title="category.parent ?? t('categories.root')">{{ category.parent ?? t('categories.root') }}</dd>
                            </div>
                            <div>
                                <dt class="admin-label">{{ t('categories.position') }}</dt>
                                <dd class="mt-1 font-mono text-xs font-semibold tabular-nums text-admin-text-secondary">{{ category.position }}</dd>
                            </div>
                            <div>
                                <dt class="admin-label">{{ t('categories.products') }}</dt>
                                <dd class="mt-1 font-mono text-xs font-semibold tabular-nums text-admin-text-secondary">{{ category.products_count }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3 flex items-center justify-end gap-4">
                            <Link :href="`/admin/categories/${category.id}/edit`" class="admin-link text-xs">{{ canUpdate ? t('categories.edit') : t('common.view') }}</Link>
                            <button
                                v-if="canArchive && category.status !== 'ARCHIVED'"
                                type="button"
                                :disabled="archivingId === category.id"
                                class="text-xs font-semibold text-admin-danger disabled:cursor-wait disabled:opacity-50"
                                @click="archive(category)"
                            >
                                {{ archivingId === category.id ? t('common.archiving') : t('common.archive') }}
                            </button>
                        </div>
                    </article>
                </div>
            </template>

            <EmptyState v-else :title="t('categories.none')" :description="t('categories.noneHelp')">
                <template v-if="canCreate" #actions>
                    <Link href="/admin/categories/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover">{{ t('categories.create') }}</Link>
                </template>
            </EmptyState>

            <Pagination :links="categories.links" :from="categories.from" :to="categories.to" :total="categories.total" />
        </section>
    </AdminLayout>
</template>
