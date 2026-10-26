<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminHeader from '../components/admin/AdminHeader.vue';
import AdminSidebar from '../components/admin/AdminSidebar.vue';
import MobileDrawer from '../components/admin/MobileDrawer.vue';
import type { PageProps } from '../types';

const page = usePage<PageProps>();
const mobileMenuOpen = ref(false);
const menuOpener = ref<HTMLButtonElement | null>(null);
const mainContent = ref<HTMLElement | null>(null);
const user = computed(() => page.props.auth.user);
const currentTenant = computed(() => page.props.currentTenant);
const currentStore = computed(() => page.props.currentStore);
const { t } = useI18n();

const openMobileMenu = (opener: HTMLButtonElement) => {
    menuOpener.value = opener;
    opener.blur();
    mobileMenuOpen.value = true;
};
const closeMobileMenu = async () => {
    mobileMenuOpen.value = false;
    await nextTick();
    menuOpener.value?.focus();
};

watch(() => page.url, async () => {
    mobileMenuOpen.value = false;
    await nextTick();
    mainContent.value?.focus({ preventScroll: true });
});
</script>

<template>
    <div :inert="mobileMenuOpen" :aria-hidden="mobileMenuOpen ? 'true' : undefined" class="min-h-screen bg-admin-canvas text-admin-text lg:grid lg:grid-cols-[15.5rem_minmax(0,1fr)]">
        <a href="#admin-main-content" class="sr-only z-[60] rounded-md bg-white px-4 py-2 font-semibold text-admin-primary focus:not-sr-only focus:fixed focus:left-4 focus:top-4">{{ t('shell.skipToContent') }}</a>
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-[15.5rem] border-r border-admin-border lg:block">
            <AdminSidebar />
        </aside>

        <div class="min-w-0 lg:col-start-2">
            <AdminHeader :mobile-menu-open="mobileMenuOpen" @open-menu="openMobileMenu" />
            <main id="admin-main-content" ref="mainContent" tabindex="-1" class="min-w-0 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <slot :user="user" :current-tenant="currentTenant" :current-store="currentStore" />
            </main>
        </div>

    </div>
    <MobileDrawer :open="mobileMenuOpen" @close="closeMobileMenu" />
</template>
