<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

const form = useForm({ first_name: '', last_name: '', email: '', phone: '', notes: '' });
const submit = () => form.post('/admin/customers');
</script>

<template>
    <Head title="Create customer" />
    <AdminLayout><section class="max-w-3xl"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Tenant customer</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create customer.</h1><p class="mt-4 text-[#637067]">Provide at least a name, email, or phone. A login account is not created.</p>
        <form class="mt-10 grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:grid-cols-2 sm:p-8" @submit.prevent="submit">
            <div><label class="mb-2 block font-semibold">First name</label><input v-model="form.first_name" maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.first_name" /></div>
            <div><label class="mb-2 block font-semibold">Last name</label><input v-model="form.last_name" maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.last_name" /></div>
            <div><label class="mb-2 block font-semibold">Email</label><input v-model="form.email" type="email" maxlength="254" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.email" /></div>
            <div><label class="mb-2 block font-semibold">Phone</label><input v-model="form.phone" type="tel" maxlength="32" placeholder="+51999111222" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.phone" /></div>
            <div class="sm:col-span-2"><label class="mb-2 block font-semibold">Internal notes</label><textarea v-model="form.notes" rows="5" maxlength="10000" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.notes" /></div>
            <div class="flex flex-wrap gap-4 sm:col-span-2"><button :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Creating...' : 'Create customer' }}</button><Link href="/admin/customers" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div>
        </form>
    </section></AdminLayout>
</template>
