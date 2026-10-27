<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface NotificationRow {
    id: string;
    type: string;
    severity: string;
    title: string;
    message: string;
    action_url: string | null;
    read_at: string | null;
    resolved_at: string | null;
    dismissed_at: string | null;
    created_at: string;
}

interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator<T> { data: T[]; links: PageLink[]; from: number | null; to: number | null; total: number; per_page: number }
interface NotificationFilters { unread: boolean; active: boolean; resolved: boolean; severity: string; type: string }

const props = defineProps<{
    notifications: Paginator<NotificationRow>;
    filters: NotificationFilters;
    types: string[];
    severities: string[];
}>();

const { t, te, locale } = useI18n();
const filters = ref<NotificationFilters>({ ...props.filters });
const filtering = ref(false);
const pendingAction = ref<string | null>(null);
const hasFilters = computed(() => Object.values(props.filters).some(Boolean));

watch(() => props.filters, (value) => { filters.value = { ...value }; }, { deep: true });

const enumLabel = (group: 'types' | 'severities', value: string) => te(`notifications.${group}.${value}`)
    ? t(`notifications.${group}.${value}`)
    : value.replaceAll('_', ' ');
const formatDate = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const isActive = (notification: NotificationRow) => !notification.resolved_at && !notification.dismissed_at;
const state = (notification: NotificationRow) => notification.resolved_at ? 'resolved' : notification.dismissed_at ? 'dismissed' : 'active';
const query = () => Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value !== false));

const severityTone = (severity: string) => {
    if (severity === 'CRITICAL') return 'border-admin-danger-border bg-admin-danger-bg text-admin-danger';
    if (severity === 'WARNING') return 'border-admin-warning-border bg-admin-warning-bg text-amber-700';
    return 'border-admin-info-border bg-admin-info-bg text-admin-info';
};
const stateTone = (value: string) => {
    if (value === 'resolved') return 'border-admin-success-border bg-admin-success-bg text-admin-success';
    if (value === 'dismissed') return 'border-admin-border bg-slate-50 text-admin-neutral';
    return 'border-admin-warning-border bg-admin-warning-bg text-amber-700';
};

const applyFilters = () => router.get('/admin/notifications', query(), {
    preserveState: true,
    replace: true,
    onStart: () => { filtering.value = true; },
    onFinish: () => { filtering.value = false; },
});
const clearFilters = () => {
    filters.value = { unread: false, active: false, resolved: false, severity: '', type: '' };
    applyFilters();
};
const mutate = (key: string, url: string) => {
    if (pendingAction.value !== null) return;
    pendingAction.value = key;
    router.post(url, {}, { preserveScroll: true, onFinish: () => { pendingAction.value = null; } });
};
const markRead = (id: string) => mutate(`read:${id}`, `/admin/notifications/${id}/read`);
const dismiss = (notification: NotificationRow) => {
    if (window.confirm(t('notifications.dismissConfirm'))) mutate(`dismiss:${notification.id}`, `/admin/notifications/${notification.id}/dismiss`);
};
const markAllRead = () => {
    if (window.confirm(t('notifications.readAllConfirm'))) mutate('read-all', '/admin/notifications/read-all');
};
</script>

<template>
    <Head :title="t('notifications.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.overview'), href: '/admin/dashboard' }, { label: t('notifications.title') }]" />
            <PageHeader :title="t('notifications.title')" :eyebrow="t('notifications.personalInbox')" :description="t('notifications.description')">
                <template #actions>
                    <div class="grid gap-2 sm:flex">
                        <Link href="/admin/settings/notifications" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold hover:bg-slate-50">{{ t('notifications.preferences') }}</Link>
                        <button type="button" :disabled="pendingAction !== null" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" @click="markAllRead">
                            {{ pendingAction === 'read-all' ? t('notifications.markingAllRead') : t('notifications.markAllRead') }}
                        </button>
                    </div>
                </template>
            </PageHeader>

            <form class="admin-card grid gap-4 p-3 sm:p-4 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,1fr)_auto] lg:items-end" role="search" @submit.prevent="applyFilters">
                <fieldset class="grid gap-2 sm:grid-cols-3">
                    <legend class="admin-label mb-2">{{ t('notifications.visibility') }}</legend>
                    <label class="flex min-h-9 items-center gap-2 rounded-md border border-admin-border px-3 text-sm"><input v-model="filters.unread" type="checkbox" class="size-4 accent-admin-secondary" />{{ t('notifications.unread') }}</label>
                    <label class="flex min-h-9 items-center gap-2 rounded-md border border-admin-border px-3 text-sm"><input v-model="filters.active" type="checkbox" class="size-4 accent-admin-secondary" />{{ t('notifications.active') }}</label>
                    <label class="flex min-h-9 items-center gap-2 rounded-md border border-admin-border px-3 text-sm"><input v-model="filters.resolved" type="checkbox" class="size-4 accent-admin-secondary" />{{ t('notifications.resolved') }}</label>
                </fieldset>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1.5" for="notification-severity"><span class="admin-label">{{ t('notifications.severity') }}</span><select id="notification-severity" v-model="filters.severity" class="admin-control w-full px-3 text-sm"><option value="">{{ t('notifications.allSeverities') }}</option><option v-for="severity in severities" :key="severity" :value="severity">{{ enumLabel('severities', severity) }}</option></select></label>
                    <label class="grid gap-1.5" for="notification-type"><span class="admin-label">{{ t('notifications.type') }}</span><select id="notification-type" v-model="filters.type" class="admin-control w-full px-3 text-sm"><option value="">{{ t('notifications.allTypes') }}</option><option v-for="type in types" :key="type" :value="type">{{ enumLabel('types', type) }}</option></select></label>
                </div>
                <div class="flex gap-2">
                    <button type="submit" :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60">{{ filtering ? t('common.loading') : t('notifications.applyFilters') }}</button>
                    <button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold" @click="clearFilters">{{ t('common.clear') }}</button>
                </div>
            </form>

            <div class="sr-only" aria-live="polite">{{ pendingAction ? t('notifications.processingAction') : '' }}</div>
            <div v-if="notifications.data.length" class="grid gap-2.5">
                <article v-for="notification in notifications.data" :key="notification.id" class="admin-card min-w-0 p-4 sm:p-5" :class="{ 'border-admin-border-strong shadow-sm': !notification.read_at }">
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded border px-1.5 py-0.5 font-mono text-[10px] font-medium" :class="severityTone(notification.severity)">{{ enumLabel('severities', notification.severity) }}</span>
                                <span class="rounded border px-1.5 py-0.5 font-mono text-[10px] font-medium" :class="stateTone(state(notification))">{{ t(`notifications.states.${state(notification)}`) }}</span>
                                <span v-if="!notification.read_at" class="rounded border border-admin-info-border bg-admin-info-bg px-1.5 py-0.5 font-mono text-[10px] font-medium text-admin-info">{{ t('notifications.new') }}</span>
                                <span class="min-w-0 truncate text-[11px] font-medium text-admin-neutral" :title="enumLabel('types', notification.type)">{{ enumLabel('types', notification.type) }}</span>
                            </div>
                            <h2 class="mt-2 break-words text-base font-semibold text-admin-primary">{{ notification.title }}</h2>
                            <p class="mt-1 break-words text-sm leading-5 text-admin-text-secondary">{{ notification.message }}</p>
                            <time class="mt-3 block text-[11px] text-admin-neutral" :datetime="notification.created_at">{{ formatDate(notification.created_at) }}</time>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex md:max-w-72 md:flex-wrap md:justify-end">
                            <Link v-if="notification.action_url" :href="notification.action_url" class="col-span-2 inline-flex min-h-9 items-center justify-center rounded-md bg-admin-primary px-3 text-xs font-semibold text-white sm:col-span-1">{{ t('notifications.openDetail') }}</Link>
                            <button v-if="!notification.read_at" type="button" :disabled="pendingAction !== null" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-3 text-xs font-semibold disabled:opacity-60" @click="markRead(notification.id)">{{ pendingAction === `read:${notification.id}` ? t('notifications.markingRead') : t('notifications.markRead') }}</button>
                            <button v-if="isActive(notification)" type="button" :disabled="pendingAction !== null" class="min-h-9 rounded-md border border-admin-border bg-white px-3 text-xs font-semibold text-admin-text-secondary disabled:opacity-60" @click="dismiss(notification)">{{ pendingAction === `dismiss:${notification.id}` ? t('notifications.dismissing') : t('notifications.dismiss') }}</button>
                        </div>
                    </div>
                </article>
            </div>
            <EmptyState v-else :title="hasFilters ? t('notifications.noMatch') : t('notifications.empty')" :description="hasFilters ? t('notifications.noMatchHelp') : t('notifications.emptyHelp')">
                <template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('notifications.clearFilters') }}</button></template>
            </EmptyState>
            <Pagination :links="notifications.links" :from="notifications.from" :to="notifications.to" :total="notifications.total" preserve-state />
        </section>
    </AdminLayout>
</template>
