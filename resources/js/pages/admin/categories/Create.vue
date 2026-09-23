<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
interface Parent { id: string; name: string }
defineProps<{ parents: Parent[] }>();
const form = useForm({ name: '', parent_id: null as string | null, position: 0 });
const submit = () => form.post('/admin/categories');
</script>
<template><Head title="Create category" /><AdminLayout><section class="max-w-2xl"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Catalog structure</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create category.</h1><form class="mt-10 grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="submit"><div><label class="mb-2 block font-semibold">Name</label><input v-model="form.name" required maxlength="120" autofocus class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.name" /></div><div><label class="mb-2 block font-semibold">Parent</label><select v-model="form.parent_id" class="w-full border bg-white px-4 py-3"><option :value="null">No parent</option><option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option></select><FieldError :message="form.errors.parent_id" /></div><div><label class="mb-2 block font-semibold">Position</label><input v-model="form.position" type="number" min="0" class="w-full border bg-white px-4 py-3" /></div><div class="flex gap-4"><button :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Create category</button><Link href="/admin/categories" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div></form></section></AdminLayout></template>
