<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Actor { id: string; name: string; email: string }
interface StoreOption { id: string; name: string }
interface ActivityRow {
    id: string;
    occurred_at: string;
    actor: Actor | null;
    actor_type: string;
    action: string;
    subject_type: string;
    subject_id: string | null;
    subject_label: string | null;
    metadata: Record<string, unknown> | null;
    store: StoreOption | null;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator<T> { data: T[]; links: PageLink[]; from: number | null; to: number | null; total: number; per_page: number }
interface ActivityFilters { action: string; subject_type: string; actor_id: string; store_id: string; date_from: string; date_to: string }

const props = defineProps<{
    activities: Paginator<ActivityRow>;
    filters: ActivityFilters;
    actions: string[];
    subjectTypes: string[];
    actors: Actor[];
    stores: StoreOption[];
}>();

const { locale, t, tm } = useI18n();
const filters = ref<ActivityFilters>({ ...props.filters });
const filtering = ref(false);
const expanded = ref(new Set<string>());

watch(() => props.filters, (value) => { filters.value = { ...value }; }, { deep: true });

const translatedMap = (key: 'actions' | 'subjects') => tm(`audit.${key}`) as Record<string, string>;
const actionLabel = (action: string) => translatedMap('actions')[action] ?? action.replaceAll('.', ' ').replaceAll('_', ' ');
const subjectLabel = (subject: string) => translatedMap('subjects')[subject] ?? subject.replaceAll('_', ' ');
const actorLabel = (activity: ActivityRow) => {
    if (activity.actor === null || activity.actor_type === 'SYSTEM') return t('audit.system');
    if (activity.actor.email === '' && activity.actor.name === 'Usuario eliminado') return t('audit.deletedUser');
    return activity.actor.name;
};
const formatDate = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const hasMetadata = (metadata: ActivityRow['metadata']) => metadata !== null && Object.keys(metadata).length > 0;
const formattedMetadata = (metadata: ActivityRow['metadata']) => JSON.stringify(metadata, null, 2);
const hasFilters = computed(() => Boolean(
    filters.value.action
    || filters.value.subject_type
    || (filters.value.actor_id && filters.value.actor_id !== 'all')
    || (filters.value.store_id && filters.value.store_id !== 'all')
    || filters.value.date_from
    || filters.value.date_to,
));

const query = () => Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value !== 'all'));
const applyFilters = () => router.get('/admin/activity', query(), {
    preserveState: true,
    replace: true,
    onStart: () => { filtering.value = true; },
    onFinish: () => { filtering.value = false; },
});
const clearFilters = () => {
    filters.value = { action: '', subject_type: '', actor_id: 'all', store_id: 'all', date_from: '', date_to: '' };
    applyFilters();
};
const toggleMetadata = (id: string) => expanded.value.has(id) ? expanded.value.delete(id) : expanded.value.add(id);
</script>

<template>
    <Head :title="t('audit.title')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.overview'), href: '/admin/dashboard' }, { label: t('audit.title') }]" />
            <PageHeader :title="t('audit.title')" :eyebrow="t('audit.eyebrow')" :description="t('audit.description')">
                <template #actions>
                    <div class="rounded-md border border-admin-border bg-admin-canvas px-3 py-2 text-right">
                        <p class="admin-label">{{ t('audit.ledger') }}</p>
                        <p class="mt-0.5 text-xs font-medium text-admin-text-secondary">{{ t('audit.perPage', { count: activities.per_page }) }}</p>
                    </div>
                </template>
            </PageHeader>

            <form class="admin-card grid gap-4 p-3 sm:grid-cols-2 sm:p-4 xl:grid-cols-6" role="search" @submit.prevent="applyFilters">
                <label class="grid min-w-0 gap-1.5" for="audit-action"><span class="admin-label">{{ t('audit.action') }}</span><select id="audit-action" v-model="filters.action" class="admin-control min-w-0 px-3 text-sm"><option value="">{{ t('audit.allActions') }}</option><option v-for="action in actions" :key="action" :value="action">{{ actionLabel(action) }}</option></select></label>
                <label class="grid min-w-0 gap-1.5" for="audit-subject"><span class="admin-label">{{ t('audit.resource') }}</span><select id="audit-subject" v-model="filters.subject_type" class="admin-control min-w-0 px-3 text-sm"><option value="">{{ t('audit.allResources') }}</option><option v-for="subjectType in subjectTypes" :key="subjectType" :value="subjectType">{{ subjectLabel(subjectType) }}</option></select></label>
                <label class="grid min-w-0 gap-1.5" for="audit-actor"><span class="admin-label">{{ t('audit.actor') }}</span><select id="audit-actor" v-model="filters.actor_id" class="admin-control min-w-0 px-3 text-sm"><option value="all">{{ t('audit.allActors') }}</option><option value="system">{{ t('audit.system') }}</option><option v-for="actor in actors" :key="actor.id" :value="actor.id">{{ actor.name }} · {{ actor.email }}</option></select></label>
                <label class="grid min-w-0 gap-1.5" for="audit-store"><span class="admin-label">{{ t('audit.store') }}</span><select id="audit-store" v-model="filters.store_id" class="admin-control min-w-0 px-3 text-sm"><option value="all">{{ t('audit.allStores') }}</option><option value="current">{{ t('common.currentStore') }}</option><option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option></select></label>
                <label class="grid min-w-0 gap-1.5" for="audit-from"><span class="admin-label">{{ t('common.from') }}</span><input id="audit-from" v-model="filters.date_from" type="date" class="admin-control min-w-0 px-3 text-sm" /></label>
                <label class="grid min-w-0 gap-1.5" for="audit-to"><span class="admin-label">{{ t('common.to') }}</span><input id="audit-to" v-model="filters.date_to" type="date" :min="filters.date_from || undefined" class="admin-control min-w-0 px-3 text-sm" /></label>
                <div class="flex gap-2 sm:col-span-2 xl:col-span-6 xl:justify-end">
                    <button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold disabled:opacity-60 sm:flex-none" @click="clearFilters">{{ t('audit.clearFilters') }}</button>
                    <button type="submit" :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60 sm:flex-none">{{ filtering ? t('common.loading') : t('audit.applyFilters') }}</button>
                </div>
            </form>

            <div v-if="activities.data.length" class="grid gap-2.5 md:hidden">
                <article v-for="activity in activities.data" :key="activity.id" class="admin-card min-w-0 p-4">
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-admin-primary">{{ actorLabel(activity) }}</p>
                            <p v-if="activity.actor?.email" class="truncate text-[11px] text-admin-neutral">{{ activity.actor.email }}</p>
                        </div>
                        <time class="shrink-0 text-right text-[11px] text-admin-neutral" :datetime="activity.occurred_at">{{ formatDate(activity.occurred_at) }}</time>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <span class="rounded border border-admin-info-border bg-admin-info-bg px-1.5 py-0.5 font-mono text-[10px] font-medium text-admin-info">{{ actionLabel(activity.action) }}</span>
                        <span v-if="activity.store" class="rounded border border-admin-border bg-admin-canvas px-1.5 py-0.5 text-[10px] font-medium text-admin-text-secondary">{{ activity.store.name }}</span>
                        <span v-else class="rounded border border-admin-border bg-admin-canvas px-1.5 py-0.5 text-[10px] font-medium text-admin-neutral">{{ t('audit.companyWide') }}</span>
                    </div>
                    <p class="mt-3 break-words text-sm font-semibold text-admin-text">{{ activity.subject_label ?? subjectLabel(activity.subject_type) }}</p>
                    <p class="mt-1 break-all font-mono text-[10px] text-admin-neutral">{{ subjectLabel(activity.subject_type) }}<span v-if="activity.subject_id"> · {{ activity.subject_id }}</span></p>
                    <button v-if="hasMetadata(activity.metadata)" type="button" class="mt-3 min-h-9 rounded-md border border-admin-border bg-white px-3 text-xs font-semibold text-admin-text-secondary" :aria-expanded="expanded.has(activity.id)" :aria-controls="`audit-metadata-${activity.id}`" @click="toggleMetadata(activity.id)">{{ expanded.has(activity.id) ? t('audit.hideDetails') : t('audit.viewDetails') }}</button>
                    <div v-if="expanded.has(activity.id)" :id="`audit-metadata-${activity.id}`" class="mt-3 min-w-0 rounded-md border border-admin-border bg-admin-canvas p-3">
                        <p class="admin-label mb-2">{{ t('audit.sanitizedMetadata') }}</p>
                        <pre class="max-w-full break-all font-mono text-[11px] leading-5 whitespace-pre-wrap text-admin-text-secondary">{{ formattedMetadata(activity.metadata) }}</pre>
                    </div>
                </article>
            </div>

            <div v-if="activities.data.length" class="admin-card hidden overflow-hidden md:block">
                <div class="overflow-x-auto">
                    <table class="admin-table w-full min-w-[980px] text-left text-xs">
                        <thead><tr><th class="px-4 py-3">{{ t('audit.date') }}</th><th class="px-4 py-3">{{ t('audit.actor') }}</th><th class="px-4 py-3">{{ t('audit.action') }}</th><th class="px-4 py-3">{{ t('audit.resource') }}</th><th class="px-4 py-3">{{ t('audit.store') }}</th><th class="px-4 py-3 text-right">{{ t('audit.details') }}</th></tr></thead>
                        <tbody>
                            <template v-for="activity in activities.data" :key="activity.id">
                                <tr class="border-t align-top">
                                    <td class="px-4 py-3 whitespace-nowrap"><time :datetime="activity.occurred_at">{{ formatDate(activity.occurred_at) }}</time></td>
                                    <td class="max-w-52 px-4 py-3"><p class="truncate font-semibold text-admin-text">{{ actorLabel(activity) }}</p><p v-if="activity.actor?.email" class="mt-0.5 truncate text-[10px] text-admin-neutral">{{ activity.actor.email }}</p></td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded border border-admin-info-border bg-admin-info-bg px-1.5 py-0.5 font-mono text-[10px] font-medium text-admin-info">{{ actionLabel(activity.action) }}</span></td>
                                    <td class="max-w-72 px-4 py-3"><p class="break-words font-semibold text-admin-text">{{ activity.subject_label ?? subjectLabel(activity.subject_type) }}</p><p class="mt-0.5 break-all font-mono text-[10px] text-admin-neutral">{{ subjectLabel(activity.subject_type) }}<span v-if="activity.subject_id"> · {{ activity.subject_id }}</span></p></td>
                                    <td class="max-w-44 px-4 py-3"><span class="break-words">{{ activity.store?.name ?? t('audit.companyWide') }}</span></td>
                                    <td class="px-4 py-3 text-right"><button v-if="hasMetadata(activity.metadata)" type="button" class="admin-link min-h-8 text-xs" :aria-expanded="expanded.has(activity.id)" :aria-controls="`audit-metadata-${activity.id}`" @click="toggleMetadata(activity.id)">{{ expanded.has(activity.id) ? t('audit.hide') : t('audit.view') }}</button><span v-else class="text-admin-neutral">—</span></td>
                                </tr>
                                <tr v-if="expanded.has(activity.id)" class="border-t bg-admin-canvas">
                                    <td colspan="6" class="px-4 py-3"><div :id="`audit-metadata-${activity.id}`" class="rounded-md border border-admin-border bg-white p-3"><p class="admin-label mb-2">{{ t('audit.sanitizedMetadata') }}</p><pre class="max-w-full break-all font-mono text-[11px] leading-5 whitespace-pre-wrap text-admin-text-secondary">{{ formattedMetadata(activity.metadata) }}</pre></div></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <EmptyState v-else :title="hasFilters ? t('audit.noMatch') : t('audit.empty')" :description="hasFilters ? t('audit.noMatchHelp') : t('audit.emptyHelp')">
                <template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('audit.clearFilters') }}</button></template>
            </EmptyState>

            <Pagination :links="activities.links" :from="activities.from" :to="activities.to" :total="activities.total" preserve-state />
        </section>
    </AdminLayout>
</template>
