<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../../components/FieldError.vue';
import AdminLayout from '../../../../layouts/AdminLayout.vue';

const form = useForm({ name: '', code: '', status: 'ACTIVE' as 'ACTIVE' | 'INACTIVE' | 'ARCHIVED' });
const submit = () => form.post('/admin/inventory/locations');
</script>

<template>
    <Head title="Create inventory location" />
    <AdminLayout><section class="max-w-2xl"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Stock control</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create location.</h1><form class="mt-10 grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="submit"><div><label class="mb-2 block font-semibold">Name</label><input v-model="form.name" required autofocus maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.name" /></div><div><label class="mb-2 block font-semibold">Code</label><input v-model="form.code" required maxlength="40" placeholder="MAIN" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 font-mono uppercase" /><FieldError :message="form.errors.code" /></div><div><label class="mb-2 block font-semibold">Status</label><select v-model="form.status" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option value="ACTIVE">ACTIVE</option><option value="INACTIVE">INACTIVE</option><option value="ARCHIVED">ARCHIVED</option></select><FieldError :message="form.errors.status" /></div><div class="flex flex-wrap gap-4"><button :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Creating...' : 'Create location' }}</button><Link href="/admin/inventory/locations" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div></form></section></AdminLayout>
</template>
