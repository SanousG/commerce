<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface NotificationFilters {
    unread: boolean;
    active: boolean;
    resolved: boolean;
    severity: string;
    type: string;
}

const props = defineProps<{
    notifications: Paginator<NotificationRow>;
    filters: NotificationFilters;
    types: string[];
    severities: string[];
}>();

const filters = ref<NotificationFilters>({ ...props.filters });
const pendingAction = ref<string | null>(null);

const severityLabels: Record<string, string> = {
    info: 'Información',
    success: 'Correcto',
    warning: 'Advertencia',
    error: 'Error',
    critical: 'Crítica',
};

const severityLabel = (severity: string) => severityLabels[severity.toLowerCase()] ?? severity.replaceAll('_', ' ');
const typeLabel = (type: string) => type.replaceAll('_', ' ');
const formatDate = (value: string) => new Date(value).toLocaleString('es');
const isActive = (notification: NotificationRow) => !notification.resolved_at && !notification.dismissed_at;

const severityClass = (severity: string) => {
    const value = severity.toLowerCase();
    if (value === 'critical' || value === 'error') return 'border-l-red-700';
    if (value === 'warning') return 'border-l-amber-500';
    if (value === 'success') return 'border-l-emerald-700';
    return 'border-l-sky-700';
};

const applyFilters = () => {
    router.get('/admin/notifications', { ...filters.value }, { preserveState: true, replace: true });
};

const mutate = (key: string, url: string) => {
    pendingAction.value = key;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => pendingAction.value = null,
    });
};

const markRead = (id: string) => mutate(`read:${id}`, `/admin/notifications/${id}/read`);
const dismiss = (id: string) => mutate(`dismiss:${id}`, `/admin/notifications/${id}/dismiss`);
const markAllRead = () => mutate('read-all', '/admin/notifications/read-all');
</script>

<template>
    <Head title="Notificaciones" />

    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Centro de avisos</p>
            <div class="mt-4 flex flex-wrap items-end justify-between gap-5 border-b border-[#17211b]/15 pb-8">
                <div>
                    <h1 class="text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Notificaciones</h1>
                    <p class="mt-3 max-w-2xl text-[#637067]">Alertas operativas y tareas que requieren tu atención.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link href="/admin/settings/notifications" class="border border-[#17211b]/25 bg-white px-4 py-3 text-sm font-semibold">Preferencias</Link>
                    <button type="button" :disabled="pendingAction === 'read-all'" class="border border-[#17211b] bg-white px-4 py-3 text-sm font-semibold disabled:opacity-50" @click="markAllRead">
                        {{ pendingAction === 'read-all' ? 'Marcando…' : 'Marcar todas como leídas' }}
                    </button>
                </div>
            </div>

            <form class="mt-8 grid gap-5 border border-[#17211b]/15 bg-white p-5 lg:grid-cols-[1fr_1fr_auto] lg:items-end" @submit.prevent="applyFilters">
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="filters.unread" type="checkbox" class="size-4 accent-emerald-800" /> No leídas</label>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="filters.active" type="checkbox" class="size-4 accent-emerald-800" /> Activas</label>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="filters.resolved" type="checkbox" class="size-4 accent-emerald-800" /> Resueltas</label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-xs font-semibold uppercase">
                        Severidad
                        <select v-model="filters.severity" class="border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case outline-none focus:border-emerald-700">
                            <option value="">Todas</option>
                            <option v-for="severity in severities" :key="severity" :value="severity">{{ severityLabel(severity) }}</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5 text-xs font-semibold uppercase">
                        Tipo
                        <select v-model="filters.type" class="border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case outline-none focus:border-emerald-700">
                            <option value="">Todos</option>
                            <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
                        </select>
                    </label>
                </div>
                <button class="bg-[#17211b] px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Aplicar filtros</button>
            </form>

            <div v-if="notifications.data.length" class="mt-8 space-y-4">
                <article v-for="notification in notifications.data" :key="notification.id" class="border border-l-4 border-[#17211b]/15 bg-white p-5 sm:p-6" :class="[severityClass(notification.severity), !notification.read_at ? 'shadow-[inset_0_0_0_1px_rgba(6,95,70,0.15)]' : 'opacity-85']">
                    <div class="grid gap-5 sm:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[10px] font-semibold tracking-[0.12em] uppercase">{{ severityLabel(notification.severity) }}</span>
                                <span class="text-[#17211b]/25">/</span>
                                <span class="text-xs text-[#637067] capitalize">{{ typeLabel(notification.type) }}</span>
                                <span v-if="!notification.read_at" class="bg-[#d8f36a] px-2 py-1 text-[10px] font-semibold tracking-wide uppercase">Nueva</span>
                                <span v-if="notification.resolved_at" class="bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-900 uppercase">Resuelta</span>
                                <span v-else-if="notification.dismissed_at" class="bg-[#17211b]/10 px-2 py-1 text-[10px] font-semibold uppercase">Descartada</span>
                                <span v-else class="bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-900 uppercase">Activa</span>
                            </div>
                            <h2 class="mt-3 text-xl font-semibold tracking-[-0.02em] sm:text-2xl">{{ notification.title }}</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-[#516057]">{{ notification.message }}</p>
                            <time class="mt-4 block text-xs text-[#637067]">{{ formatDate(notification.created_at) }}</time>
                        </div>
                        <div class="flex flex-wrap items-start gap-2 sm:max-w-48 sm:justify-end">
                            <Link v-if="notification.action_url" :href="notification.action_url" class="bg-[#17211b] px-4 py-2.5 text-sm font-semibold text-white">Ver detalle</Link>
                            <button v-if="!notification.read_at" type="button" :disabled="pendingAction !== null" class="border border-[#17211b]/25 px-4 py-2.5 text-sm font-semibold disabled:opacity-50" @click="markRead(notification.id)">Marcar leída</button>
                            <button v-if="isActive(notification)" type="button" :disabled="pendingAction !== null" class="px-4 py-2.5 text-sm font-semibold text-[#637067] underline disabled:opacity-50" @click="dismiss(notification.id)">Descartar</button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-8 border border-[#17211b]/15 bg-white p-10 text-center text-[#637067]">No tienes notificaciones pendientes.</div>

            <div v-if="notifications.data.length" class="mt-6 flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-[#637067]">{{ notifications.from }}–{{ notifications.to }} de {{ notifications.total }} · 25 por página</p>
                <nav class="flex flex-wrap gap-2" aria-label="Paginación de notificaciones">
                    <template v-for="link in notifications.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20 bg-white'" v-html="link.label" />
                        <span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </section>
    </AdminLayout>
</template>
