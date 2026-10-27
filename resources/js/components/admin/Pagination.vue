<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    links: PageLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
    preserveState?: boolean;
}>();

const hasNavigablePage = computed(() => Boolean(props.links.at(0)?.url || props.links.at(-1)?.url));
const { t } = useI18n();
const linkLabel = (label: string, index: number) => index === 0 ? t('pagination.previous') : index === props.links.length - 1 ? t('pagination.next') : label;
</script>

<template>
    <nav v-if="hasNavigablePage" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" :aria-label="t('pagination.label')">
        <p v-if="from !== undefined && to !== undefined && total !== undefined" class="text-xs text-admin-neutral">
            {{ t('pagination.showing', { from: from ?? 0, to: to ?? 0, total }) }}
        </p>
        <div class="flex flex-wrap items-center gap-1.5">
            <template v-for="(link, index) in links" :key="`${link.label}-${index}`">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :preserve-state="preserveState"
                    class="min-h-9 min-w-9 place-items-center rounded-md border px-2.5 text-xs font-medium transition"
                    :class="[
                        index > 0 && index < links.length - 1 && !link.active ? 'hidden sm:grid' : 'grid',
                        link.active ? 'border-admin-primary bg-admin-primary text-white' : 'border-admin-border bg-white text-admin-text-secondary hover:border-admin-border-strong hover:bg-slate-50',
                    ]"
                    :aria-current="link.active ? 'page' : undefined"
                    {{ linkLabel(link.label, index) }}
                />
                <span v-else class="min-h-9 min-w-9 cursor-not-allowed place-items-center rounded-md border border-admin-border bg-admin-canvas px-2.5 text-xs text-slate-400" :class="index > 0 && index < links.length - 1 ? 'hidden sm:grid' : 'grid'" aria-disabled="true">{{ linkLabel(link.label, index) }}</span>
            </template>
        </div>
    </nav>
</template>
