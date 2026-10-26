<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PageProps } from '../../types';

const emit = defineEmits<{ openMenu: [opener: HTMLButtonElement] }>();
defineProps<{ mobileMenuOpen: boolean }>();

const page = usePage<PageProps>();
const currentTenant = computed(() => page.props.currentTenant);
const currentStore = computed(() => page.props.currentStore);
const unread = computed(() => page.props.unreadNotificationsCount ?? 0);
const { t, locale } = useI18n();
const savingLocale = ref(false);
const openMenu = (event: MouseEvent) => emit('openMenu', event.currentTarget as HTMLButtonElement);
const changeLocale = (event: Event) => {
    const selected = (event.target as HTMLSelectElement).value as 'es-PE' | 'en';
    const previous = locale.value;
    locale.value = selected;
    document.documentElement.lang = selected;
    router.patch('/admin/locale', { locale: selected }, {
        preserveScroll: true,
        onStart: () => { savingLocale.value = true; },
        onError: () => { locale.value = previous; document.documentElement.lang = previous; },
        onFinish: () => { savingLocale.value = false; },
    });
};
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center border-b border-admin-border bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
        <button type="button" class="mr-3 grid size-9 place-items-center rounded-md border border-admin-border text-admin-text-secondary hover:bg-slate-50 lg:hidden" :aria-label="t('shell.openNavigation')" aria-controls="admin-mobile-navigation" :aria-expanded="mobileMenuOpen" @click="openMenu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" /></svg>
        </button>
        <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold text-admin-text">{{ currentTenant?.name ?? 'Commerce' }}</p>
            <p class="truncate text-[10px] text-admin-neutral">{{ currentStore ? `${currentStore.name} · ${currentStore.currency}` : t('common.workspace') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <label class="relative">
                <span class="sr-only">{{ t('locale.label') }}</span>
                <select :value="locale" :disabled="savingLocale" class="h-9 rounded-md border border-admin-border bg-white px-2 text-xs font-semibold text-admin-text-secondary disabled:cursor-wait disabled:opacity-60 sm:px-3" :aria-label="savingLocale ? t('locale.saving') : t('locale.label')" @change="changeLocale">
                    <option value="es-PE">{{ t('locale.es') }}</option>
                    <option value="en">{{ t('locale.en') }}</option>
                </select>
            </label>
            <Link href="/admin/notifications" class="relative grid size-9 place-items-center rounded-md border border-admin-border text-admin-text-secondary hover:bg-slate-50" :aria-label="unread ? t('shell.unreadNotifications', { count: unread }) : t('shell.notifications')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17H9m9-2v-4a6 6 0 1 0-12 0v4l-2 2h16l-2-2Zm-8 5h4" /></svg>
                <span v-if="unread" class="absolute -right-1 -top-1 min-w-4 rounded-full bg-admin-primary px-1 text-center text-[9px] font-semibold leading-4 text-white">{{ unread > 99 ? '99+' : unread }}</span>
            </Link>
        </div>
    </header>
</template>
