<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PreferenceProps {
    preferences: { email_enabled: boolean; muted_types: string[] };
    types: string[];
    mandatoryTypes: string[];
}

const props = defineProps<PreferenceProps>();
const page = usePage<PageProps>();
const saved = computed(() => page.props.status === 'Notification preferences saved.');

const form = useForm({
    email_enabled: props.preferences.email_enabled,
    muted_types: [...props.preferences.muted_types],
});

const typeLabel = (type: string) => type.replaceAll('_', ' ').toLowerCase();
const isMuted = (type: string) => form.muted_types.includes(type);
const toggleMuted = (type: string, muted: boolean) => {
    form.muted_types = muted
        ? [...form.muted_types, type]
        : form.muted_types.filter((value) => value !== type);
};
const onMuteToggle = (type: string, event: Event) => {
    toggleMuted(type, (event.target as HTMLInputElement).checked);
};

const submit = () => {
    form.patch('/admin/settings/notifications', { preserveScroll: true });
};
</script>

<template>
    <Head title="Notification preferences" />

    <AdminLayout>
        <section class="max-w-3xl">
            <Link href="/admin/notifications" class="text-sm font-semibold text-emerald-800 hover:underline">Notificaciones</Link>
            <h1 class="mt-5 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Preferencias.</h1>
            <p class="mt-4 text-[#516057]">
                Elige qué avisos quieres ver en el panel y si deseas recibirlos también por correo. La auditoría de la empresa no se ve afectada por estas preferencias.
            </p>

            <form class="mt-10 space-y-8 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="submit">
                <label class="flex items-start gap-3">
                    <input v-model="form.email_enabled" type="checkbox" class="mt-1 size-4 accent-emerald-800" />
                    <span>
                        <span class="block text-sm font-semibold">Recibir también por correo electrónico</span>
                        <span class="mt-1 block text-sm text-[#637067]">Se envían a la dirección de tu cuenta, fuera de la operación comercial.</span>
                    </span>
                </label>
                <FieldError :message="form.errors.email_enabled" />

                <fieldset class="space-y-3">
                    <legend class="text-sm font-semibold uppercase tracking-[0.12em] text-[#637067]">Silenciar tipos de aviso</legend>
                    <p class="text-sm text-[#637067]">Los tipos obligatorios no se pueden silenciar.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="type in types"
                            :key="type"
                            class="flex items-center gap-3 border border-[#17211b]/15 bg-white px-3 py-2.5 text-sm"
                            :class="{ 'opacity-50': mandatoryTypes.includes(type) }"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-emerald-800"
                                :checked="isMuted(type)"
                                :disabled="mandatoryTypes.includes(type)"
                                @change="onMuteToggle(type, $event)"
                            />
                            <span class="capitalize">{{ typeLabel(type) }}</span>
                        </label>
                    </div>
                    <FieldError :message="form.errors.muted_types" />
                </fieldset>

                <p v-if="saved" class="text-sm font-semibold text-emerald-800">Preferencias guardadas.</p>
                <button type="submit" :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">
                    {{ form.processing ? 'Guardando…' : 'Guardar preferencias' }}
                </button>
            </form>
        </section>
    </AdminLayout>
</template>
