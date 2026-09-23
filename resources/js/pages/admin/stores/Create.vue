<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const form = useForm({
    name: '',
    currency: 'PEN',
    timezone: 'America/Lima',
    locale: 'es_PE',
});

const submit = () => {
    form.post('/admin/stores');
};
</script>

<template>
    <Head title="Create store" />

    <AdminLayout>
        <section class="max-w-3xl">
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">New storefront</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create a store.</h1>
            <p class="mt-5 max-w-xl leading-7 text-[#516057]">
                Set the regional defaults for this store. Catalog and storefront configuration come later.
            </p>

            <form class="mt-10 grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-2 sm:p-8" @submit.prevent="submit">
                <div class="sm:col-span-2">
                    <label for="name" class="mb-2 block text-sm font-semibold">Store name</label>
                    <input id="name" v-model="form.name" type="text" required autofocus maxlength="120" autocomplete="organization" aria-describedby="name-error" :aria-invalid="Boolean(form.errors.name)" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15" />
                    <FieldError id="name-error" :message="form.errors.name" />
                </div>

                <div>
                    <label for="currency" class="mb-2 block text-sm font-semibold">Currency</label>
                    <select id="currency" v-model="form.currency" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700">
                        <option value="PEN">PEN / Peruvian sol</option>
                        <option value="USD">USD / US dollar</option>
                        <option value="EUR">EUR / Euro</option>
                    </select>
                    <FieldError :message="form.errors.currency" />
                </div>

                <div>
                    <label for="timezone" class="mb-2 block text-sm font-semibold">Timezone</label>
                    <select id="timezone" v-model="form.timezone" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700">
                        <option value="America/Lima">America/Lima</option>
                        <option value="America/New_York">America/New_York</option>
                        <option value="Europe/Madrid">Europe/Madrid</option>
                        <option value="UTC">UTC</option>
                    </select>
                    <FieldError :message="form.errors.timezone" />
                </div>

                <div>
                    <label for="locale" class="mb-2 block text-sm font-semibold">Locale</label>
                    <select id="locale" v-model="form.locale" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3 outline-none focus:border-emerald-700">
                        <option value="es_PE">es_PE</option>
                        <option value="en_US">en_US</option>
                        <option value="es_ES">es_ES</option>
                    </select>
                    <FieldError :message="form.errors.locale" />
                </div>

                <div class="flex flex-wrap items-end gap-4 sm:col-span-2">
                    <button type="submit" :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-60">
                        {{ form.processing ? 'Creating store...' : 'Create store' }}
                    </button>
                    <Link href="/admin/stores" class="py-3 text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline">View stores</Link>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
