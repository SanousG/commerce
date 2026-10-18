<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Actor {
    id: string;
    name: string;
    email: string;
}

interface StoreOption {
    id: string;
    name: string;
}

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

interface ActivityFilters {
    action: string;
    subject_type: string;
    actor_id: string;
    store_id: string;
    date_from: string;
    date_to: string;
}

const props = defineProps<{
    activities: Paginator<ActivityRow>;
    filters: ActivityFilters;
    actions: string[];
    subjectTypes: string[];
    actors: Actor[];
    stores: StoreOption[];
}>();

const filters = ref<ActivityFilters>({ ...props.filters });
const expanded = ref(new Set<string>());

const actionLabels: Record<string, string> = {
    created: 'Creó',
    updated: 'Actualizó',
    deleted: 'Eliminó',
    restored: 'Restauró',
    viewed: 'Consultó',
    exported: 'Exportó',
    imported: 'Importó',
    approved: 'Aprobó',
    rejected: 'Rechazó',
    cancelled: 'Canceló',
    completed: 'Completó',
    assigned: 'Asignó',
    unassigned: 'Desasignó',
    invited: 'Invitó',
    removed: 'Retiró',
    logged_in: 'Inició sesión',
    logged_out: 'Cerró sesión',
};

const humanAction = (action: string) => actionLabels[action.toLowerCase()] ?? action.replaceAll('_', ' ');
const humanSubject = (subjectType: string) => subjectType.split('\\').pop()?.replaceAll('_', ' ') ?? subjectType;
const formatDate = (value: string) => new Date(value).toLocaleString('es');
const hasMetadata = (metadata: ActivityRow['metadata']) => metadata !== null && Object.keys(metadata).length > 0;
const formattedMetadata = (metadata: ActivityRow['metadata']) => JSON.stringify(metadata, null, 2);

const applyFilters = () => {
    router.get('/admin/activity', { ...filters.value }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    filters.value = { action: '', subject_type: '', actor_id: '', store_id: '', date_from: '', date_to: '' };
    applyFilters();
};

const toggleMetadata = (id: string) => {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }
};
</script>

<template>
    <Head title="Actividad" />

    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Auditoría</p>
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 border-b border-[#17211b]/15 pb-8">
                <div>
                    <h1 class="text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Actividad</h1>
                    <p class="mt-3 max-w-2xl text-[#637067]">Consulta los cambios y acciones realizados dentro de la organización.</p>
                </div>
                <p class="font-mono text-xs text-[#637067]">50 por página</p>
            </div>

            <form class="mt-8 grid gap-4 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-2 xl:grid-cols-6" @submit.prevent="applyFilters">
                <label class="grid gap-1.5 text-xs font-semibold uppercase">
                    Acción
                    <select v-model="filters.action" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case outline-none focus:border-emerald-700">
                        <option value="">Todas</option>
                        <option v-for="action in actions" :key="action" :value="action">{{ humanAction(action) }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">
                    Recurso
                    <select v-model="filters.subject_type" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case outline-none focus:border-emerald-700">
                        <option value="">Todos</option>
                        <option v-for="subjectType in subjectTypes" :key="subjectType" :value="subjectType">{{ humanSubject(subjectType) }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">
                    Usuario
                    <select v-model="filters.actor_id" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case outline-none focus:border-emerald-700">
                        <option value="">Todos</option>
                        <option v-for="actor in actors" :key="actor.id" :value="actor.id">{{ actor.name }} · {{ actor.email }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">
                    Tienda
                    <select v-model="filters.store_id" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case outline-none focus:border-emerald-700">
                        <option value="">Todas</option>
                        <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">
                    Desde
                    <input v-model="filters.date_from" type="date" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2 text-sm font-normal normal-case outline-none focus:border-emerald-700" />
                </label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">
                    Hasta
                    <input v-model="filters.date_to" type="date" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2 text-sm font-normal normal-case outline-none focus:border-emerald-700" />
                </label>
                <div class="flex gap-3 sm:col-span-2 xl:col-span-6 xl:justify-end">
                    <button type="button" class="border border-[#17211b]/25 px-4 py-2.5 text-sm font-semibold" @click="clearFilters">Limpiar</button>
                    <button class="bg-[#17211b] px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Aplicar filtros</button>
                </div>
            </form>

            <div v-if="activities.data.length" class="mt-8 grid gap-4 md:hidden">
                <article v-for="activity in activities.data" :key="activity.id" class="border border-[#17211b]/15 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ activity.actor?.name ?? activity.actor_type }}</p>
                            <p v-if="activity.actor" class="mt-0.5 text-xs text-[#637067]">{{ activity.actor.email }}</p>
                        </div>
                        <time class="shrink-0 text-right text-xs text-[#637067]">{{ formatDate(activity.occurred_at) }}</time>
                    </div>
                    <p class="mt-4 text-sm"><span class="font-semibold text-emerald-800">{{ humanAction(activity.action) }}</span> {{ activity.subject_label ?? humanSubject(activity.subject_type) }}</p>
                    <p class="mt-1 font-mono text-xs text-[#637067]">{{ humanSubject(activity.subject_type) }}<span v-if="activity.subject_id"> / {{ activity.subject_id }}</span></p>
                    <p v-if="activity.store" class="mt-3 text-xs text-[#637067]">Tienda: {{ activity.store.name }}</p>
                    <button v-if="hasMetadata(activity.metadata)" type="button" class="mt-4 text-xs font-semibold text-emerald-800 underline" :aria-expanded="expanded.has(activity.id)" @click="toggleMetadata(activity.id)">
                        {{ expanded.has(activity.id) ? 'Ocultar detalles' : 'Ver detalles' }}
                    </button>
                    <pre v-if="expanded.has(activity.id)" class="mt-3 overflow-x-auto bg-[#17211b] p-4 text-xs whitespace-pre-wrap text-white">{{ formattedMetadata(activity.metadata) }}</pre>
                </article>
            </div>

            <div v-if="activities.data.length" class="mt-8 hidden overflow-x-auto border border-[#17211b]/15 bg-white md:block">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-[#17211b] text-white">
                        <tr><th class="p-4">Fecha</th><th class="p-4">Usuario</th><th class="p-4">Acción</th><th class="p-4">Recurso</th><th class="p-4">Tienda</th><th class="p-4">Detalles</th></tr>
                    </thead>
                    <tbody>
                        <template v-for="activity in activities.data" :key="activity.id">
                            <tr class="border-t border-[#17211b]/10 align-top">
                                <td class="p-4 text-xs whitespace-nowrap">{{ formatDate(activity.occurred_at) }}</td>
                                <td class="p-4"><p class="font-semibold">{{ activity.actor?.name ?? activity.actor_type }}</p><p v-if="activity.actor" class="mt-1 text-xs text-[#637067]">{{ activity.actor.email }}</p></td>
                                <td class="p-4 font-semibold text-emerald-800">{{ humanAction(activity.action) }}</td>
                                <td class="p-4"><p class="font-semibold">{{ activity.subject_label ?? humanSubject(activity.subject_type) }}</p><p class="mt-1 font-mono text-xs text-[#637067]">{{ humanSubject(activity.subject_type) }}<span v-if="activity.subject_id"> / {{ activity.subject_id }}</span></p></td>
                                <td class="p-4">{{ activity.store?.name ?? 'General' }}</td>
                                <td class="p-4"><button v-if="hasMetadata(activity.metadata)" type="button" class="font-semibold text-emerald-800 underline" :aria-expanded="expanded.has(activity.id)" @click="toggleMetadata(activity.id)">{{ expanded.has(activity.id) ? 'Ocultar' : 'Ver' }}</button><span v-else class="text-[#637067]">—</span></td>
                            </tr>
                            <tr v-if="expanded.has(activity.id)" class="border-t border-[#17211b]/10 bg-[#f8f5ed]">
                                <td colspan="6" class="p-4"><pre class="overflow-x-auto bg-[#17211b] p-4 text-xs whitespace-pre-wrap text-white">{{ formattedMetadata(activity.metadata) }}</pre></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div v-else class="mt-8 border border-[#17211b]/15 bg-white p-10 text-center text-[#637067]">No hay actividad registrada para este filtro.</div>

            <div v-if="activities.data.length" class="mt-6 flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-[#637067]">{{ activities.from }}–{{ activities.to }} de {{ activities.total }}</p>
                <nav class="flex flex-wrap gap-2" aria-label="Paginación de actividad">
                    <template v-for="link in activities.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20 bg-white'" v-html="link.label" />
                        <span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </section>
    </AdminLayout>
</template>
