<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
    eyebrow: string;
    title: string;
    subtitle: string;
}>();

const { t } = useI18n();
</script>

<template>
    <main class="min-h-screen bg-admin-canvas lg:grid lg:grid-cols-[minmax(22rem,0.82fr)_minmax(32rem,1.18fr)]">
        <section
            class="relative hidden min-h-screen overflow-hidden bg-admin-primary px-10 py-10 text-white lg:flex lg:flex-col lg:justify-between xl:px-16"
        >
            <div class="pointer-events-none absolute inset-0 opacity-40" aria-hidden="true">
                <div class="absolute -right-24 top-24 size-80 rounded-full border border-sky-400/25" />
                <div class="absolute -right-8 top-40 size-48 rounded-full border border-blue-500/30" />
                <div class="absolute inset-x-0 bottom-0 h-64 bg-gradient-to-t from-blue-950/50 to-transparent" />
            </div>

            <div class="relative flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-lg bg-admin-secondary shadow-lg shadow-blue-950/30" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="size-5"><path d="M5 7.5 12 4l7 3.5v9L12 20l-7-3.5v-9Z" stroke="currentColor" stroke-width="1.8"/><path d="m5 7.5 7 3.5 7-3.5M12 11v9" stroke="currentColor" stroke-width="1.8"/></svg>
                </span>
                <div>
                    <p class="text-sm font-bold tracking-[0.16em] uppercase">Commerce</p>
                    <p class="mt-0.5 text-[10px] font-semibold tracking-[0.14em] text-slate-400 uppercase">{{ t('auth.layout.product') }}</p>
                </div>
            </div>

            <div class="relative max-w-lg py-12">
                <p class="mb-5 font-mono text-xs font-medium tracking-[0.18em] text-sky-300 uppercase">
                    {{ t('auth.layout.tagline') }}
                </p>
                <h2 class="text-4xl leading-[1.08] font-semibold tracking-[-0.04em] xl:text-5xl">
                    {{ t('auth.layout.title') }}
                </h2>
                <p class="mt-5 max-w-md text-sm leading-6 text-slate-300">
                    {{ t('auth.layout.description') }}
                </p>

                <ul class="mt-10 grid gap-4" role="list">
                    <li v-for="item in ['security', 'tenancy', 'operations']" :key="item" class="flex items-start gap-3 text-sm text-slate-200">
                        <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-sky-400/15 text-sky-300" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" class="size-3"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        {{ t(`auth.layout.points.${item}`) }}
                    </li>
                </ul>
            </div>

            <p class="relative flex items-center gap-2 text-xs leading-5 text-slate-400">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2m-11 0h12v10H6V10Z" stroke-width="1.7"/></svg>
                {{ t('auth.layout.securityNote') }}
            </p>
        </section>

        <section class="flex min-h-screen flex-col px-4 py-5 sm:px-8 sm:py-7 lg:px-12 xl:px-20">
            <div class="mx-auto flex w-full max-w-xl items-center justify-between lg:hidden">
                <div class="flex items-center gap-2.5 lg:hidden">
                    <span class="grid size-9 place-items-center rounded-lg bg-admin-primary text-white" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" class="size-4.5"><path d="M5 7.5 12 4l7 3.5v9L12 20l-7-3.5v-9Z" stroke="currentColor" stroke-width="1.8"/><path d="m5 7.5 7 3.5 7-3.5M12 11v9" stroke="currentColor" stroke-width="1.8"/></svg>
                    </span>
                    <span class="text-sm font-bold tracking-[0.12em] text-admin-primary uppercase">Commerce</span>
                </div>
            </div>

            <div class="mx-auto flex w-full max-w-xl flex-1 items-center py-8 sm:py-12">
                <div class="w-full rounded-xl border border-admin-border bg-white px-5 py-7 shadow-[0_20px_55px_-32px_rgba(15,23,42,0.35)] sm:px-9 sm:py-9">
                    <header class="mb-7">
                        <p class="mb-3 font-mono text-[11px] font-semibold tracking-[0.16em] text-admin-tertiary uppercase">
                            {{ eyebrow }}
                        </p>
                        <h1 class="text-3xl font-semibold tracking-[-0.035em] text-admin-primary sm:text-[2rem]">
                            {{ title }}
                        </h1>
                        <p class="mt-3 max-w-lg text-sm leading-6 text-admin-neutral">
                            {{ subtitle }}
                        </p>
                    </header>

                    <slot />
                </div>
            </div>

            <p class="text-center text-[11px] leading-5 text-admin-neutral">{{ t('auth.layout.footer') }}</p>
        </section>
    </main>
</template>
