<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();
const closeButton = ref<HTMLButtonElement | null>(null);
const drawer = ref<HTMLElement | null>(null);
const { t } = useI18n();

const focusable = () => Array.from(drawer.value?.querySelectorAll<HTMLElement>(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
) ?? []);

const handleKeydown = (event: KeyboardEvent) => {
    if (!props.open) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
        return;
    }
    if (event.key !== 'Tab') return;

    const elements = focusable();
    if (!elements.length) {
        event.preventDefault();
        return;
    }
    const first = elements[0];
    const last = elements[elements.length - 1];
    if (!drawer.value?.contains(document.activeElement)) {
        event.preventDefault();
        first.focus();
    } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

watch(() => props.open, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) void nextTick(() => closeButton.value?.focus());
});
onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition-opacity duration-200" leave-active-class="transition-opacity duration-150" enter-from-class="opacity-0" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-50 bg-slate-950/55 lg:hidden" aria-hidden="true" @click="emit('close')" />
        </Transition>
        <Transition enter-active-class="transition-transform duration-200 ease-out" leave-active-class="transition-transform duration-150 ease-in" enter-from-class="-translate-x-full" leave-to-class="-translate-x-full">
            <aside v-if="open" id="admin-mobile-navigation" ref="drawer" class="fixed inset-y-0 left-0 z-50 w-[min(20rem,88vw)] shadow-2xl lg:hidden" role="dialog" aria-modal="true" :aria-label="t('shell.adminNavigation')">
                <button ref="closeButton" type="button" class="absolute right-3 top-3 z-10 grid size-8 place-items-center rounded-md border border-admin-border bg-white text-admin-text-secondary hover:bg-slate-50" :aria-label="t('shell.closeNavigation')" @click="emit('close')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
                <AdminSidebar />
            </aside>
        </Transition>
    </Teleport>
</template>
