<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const props = defineProps<{ store: { id: string; name: string; slug: string; status: 'ACTIVE' | 'INACTIVE'; currency: string; timezone: string; locale: string } }>();
const form = useForm({ name: props.store.name, status: props.store.status, currency: props.store.currency, timezone: props.store.timezone, locale: props.store.locale });
</script>

<template>
    <Head :title="`Edit ${store.name}`" />
    <AdminLayout>
        <section class="max-w-3xl">
            <Link href="/admin/stores" class="text-sm font-semibold text-emerald-800 hover:underline">Stores</Link>
            <h1 class="mt-5 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Store settings.</h1>
            <p class="mt-3 text-[#516057]">{{ store.slug }}. This Store is the commercial authority for currency, timezone and locale.</p>
            <form class="mt-10 grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-2 sm:p-8" @submit.prevent="form.patch(`/admin/stores/${store.id}`)">
                <div class="sm:col-span-2"><label for="store-name" class="mb-2 block text-sm font-semibold">Name</label><input id="store-name" v-model="form.name" required maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.name" /></div>
                <div><label for="store-status" class="mb-2 block text-sm font-semibold">Status</label><select id="store-status" v-model="form.status" class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select><FieldError :message="form.errors.status" /></div>
                <div><label for="store-currency" class="mb-2 block text-sm font-semibold">Currency</label><select id="store-currency" v-model="form.currency" class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="PEN">PEN</option><option value="USD">USD</option><option value="EUR">EUR</option></select><FieldError :message="form.errors.currency" /></div>
                <div><label for="store-timezone" class="mb-2 block text-sm font-semibold">Timezone</label><input id="store-timezone" v-model="form.timezone" required placeholder="America/Lima" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.timezone" /></div>
                <div><label for="store-locale" class="mb-2 block text-sm font-semibold">Locale</label><input id="store-locale" v-model="form.locale" required placeholder="es_PE" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.locale" /></div>
                <div class="sm:col-span-2"><button type="submit" :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">Save store</button></div>
            </form>
        </section>
    </AdminLayout>
</template>
