<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const form = useForm({ name: '', code: '', contact_name: '', email: '', phone: '', website: '', notes: '' });
const submit = () => form.post('/admin/suppliers');
</script>

<template>
    <Head title="Create supplier" />
    <AdminLayout><section class="max-w-4xl"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Procurement directory</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create supplier.</h1>
        <form class="mt-10 grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-2 sm:p-8" @submit.prevent="submit">
            <div><label class="mb-2 block font-semibold">Name</label><input v-model="form.name" required maxlength="160" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.name" /></div><div><label class="mb-2 block font-semibold">Code</label><input v-model="form.code" required maxlength="40" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 font-mono" /><FieldError :message="form.errors.code" /></div>
            <div><label class="mb-2 block font-semibold">Contact name</label><input v-model="form.contact_name" maxlength="160" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.contact_name" /></div><div><label class="mb-2 block font-semibold">Email</label><input v-model="form.email" type="email" maxlength="254" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.email" /></div>
            <div><label class="mb-2 block font-semibold">Phone</label><input v-model="form.phone" type="tel" maxlength="40" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.phone" /></div><div><label class="mb-2 block font-semibold">Website</label><input v-model="form.website" type="url" maxlength="2048" placeholder="https://" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.website" /></div><div class="sm:col-span-2"><label class="mb-2 block font-semibold">Internal notes</label><textarea v-model="form.notes" rows="4" maxlength="10000" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.notes" /></div>
            <div class="flex flex-wrap gap-4 sm:col-span-2"><button :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Creating...' : 'Create supplier' }}</button><Link href="/admin/suppliers" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div>
        </form>
    </section></AdminLayout>
</template>
