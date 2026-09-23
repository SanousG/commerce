<script setup lang="ts">
defineProps<{
    eyebrow: string;
    title: string;
    subtitle: string;
}>();
</script>

<template>
    <main class="min-h-screen bg-[#f1eee6] lg:grid lg:grid-cols-[0.9fr_1.1fr]">
        <section
            class="relative hidden min-h-screen overflow-hidden bg-[#17211b] px-12 py-10 text-[#f1eee6] lg:flex lg:flex-col lg:justify-between xl:px-20"
        >
            <div class="absolute -right-24 top-32 h-72 w-72 rounded-full border border-[#d8f36a]/30" />
            <div class="absolute -right-2 top-54 h-28 w-28 rounded-full bg-[#d8f36a]" />

            <p class="text-xs font-semibold tracking-[0.26em] text-[#d8f36a] uppercase">
                Commerce / Admin
            </p>

            <div class="relative max-w-xl">
                <p class="mb-6 font-mono text-xs tracking-[0.22em] text-white/55 uppercase">
                    One operation. Clear control.
                </p>
                <h2 class="text-5xl leading-[1.03] font-semibold tracking-[-0.05em] xl:text-7xl">
                    The quiet side of running commerce.
                </h2>
            </div>

            <p class="max-w-sm text-sm leading-6 text-white/55">
                A focused workspace for the people building and operating your business.
            </p>
        </section>

        <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <p class="mb-12 text-xs font-semibold tracking-[0.24em] text-emerald-900 uppercase lg:hidden">
                    Commerce / Admin
                </p>

                <header class="mb-9">
                    <p class="mb-3 font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">
                        {{ eyebrow }}
                    </p>
                    <h1 class="text-4xl font-semibold tracking-[-0.045em] text-[#17211b]">
                        {{ title }}
                    </h1>
                    <p class="mt-3 max-w-sm text-sm leading-6 text-[#516057]">
                        {{ subtitle }}
                    </p>
                </header>

                <slot />
            </div>
        </section>
    </main>
</template>
