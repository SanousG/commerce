<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const form = useForm({
    name: '',
});

const submit = () => {
    form.post('/admin/tenants');
};
</script>

<template>
    <Head title="Create company" />

    <AdminLayout>
        <section class="max-w-2xl">
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">New workspace</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create your company.</h1>
            <p class="mt-5 max-w-xl leading-7 text-[#516057]">
                You will become the owner. Stores and commerce settings are intentionally configured later.
            </p>

            <form class="mt-10 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="submit">
                <label for="name" class="mb-2 block text-sm font-semibold">Company name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="organization"
                    required
                    autofocus
                    maxlength="120"
                    aria-describedby="name-error"
                    :aria-invalid="Boolean(form.errors.name)"
                    class="w-full border border-[#17211b]/25 bg-white px-4 py-3 outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15"
                />
                <FieldError id="name-error" :message="form.errors.name" />

                <div class="mt-7 flex flex-wrap items-center gap-4">
                    <button type="submit" :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-60">
                        {{ form.processing ? 'Creating company...' : 'Create company' }}
                    </button>
                    <Link href="/admin/tenants" class="text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline">
                        View my companies
                    </Link>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
