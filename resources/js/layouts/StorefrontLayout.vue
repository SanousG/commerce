<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface PublicStore {
    publicHandle: string;
    name: string;
    description: string | null;
    currency: string;
    timezone: string;
    locale: string;
    branding: {
        primaryColor: string;
        secondaryColor: string | null;
    };
}

const props = defineProps<{ store: PublicStore }>();
const { t } = useI18n();
const theme = computed(() => ({
    '--storefront-primary': props.store.branding.primaryColor,
    '--storefront-secondary': props.store.branding.secondaryColor ?? props.store.branding.primaryColor,
}));
</script>

<template>
    <div :style="theme" class="min-h-screen bg-stone-50 text-stone-950">
        <a
            href="#storefront-main-content"
            class="sr-only z-50 rounded-md bg-white px-4 py-2 font-semibold text-stone-950 shadow focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
        >
            {{ t('storefront.skipToContent') }}
        </a>

        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex min-h-20 max-w-6xl items-center px-4 sm:px-6 lg:px-8">
                <span class="h-8 w-1 rounded-full bg-[var(--storefront-primary)]" aria-hidden="true" />
                <p class="ml-4 text-lg font-semibold tracking-tight sm:text-xl">{{ store.name }}</p>
            </div>
        </header>

        <main id="storefront-main-content" tabindex="-1">
            <slot />
        </main>

        <footer class="border-t border-stone-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-6 text-sm text-stone-600 sm:px-6 lg:px-8">
                <p class="font-medium text-stone-800">{{ store.name }}</p>
                <p>{{ t('storefront.foundationNotice') }}</p>
            </div>
        </footer>
    </div>
</template>
