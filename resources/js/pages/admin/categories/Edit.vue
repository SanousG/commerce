<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
interface Parent { id: string; name: string }
interface Category { id: string; name: string; slug: string; status: string; parent_id: string | null; position: number }
const props = defineProps<{ category: Category; parents: Parent[]; statuses: string[] }>();
const form = useForm({ name: props.category.name, status: props.category.status, parent_id: props.category.parent_id, position: props.category.position });
const submit = () => form.put(`/admin/categories/${props.category.id}`);
</script>
<template><Head :title="`Edit ${category.name}`" /><AdminLayout><section class="max-w-2xl"><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ category.slug }}</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Edit category.</h1><form class="mt-10 grid gap-5 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="submit"><div><label class="mb-2 block font-semibold">Name</label><input v-model="form.name" required maxlength="120" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.name" /></div><select v-model="form.status" class="w-full border bg-white px-4 py-3"><option v-for="status in statuses" :key="status">{{ status }}</option></select><select v-model="form.parent_id" class="w-full border bg-white px-4 py-3"><option :value="null">No parent</option><option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option></select><FieldError :message="form.errors.parent_id" /><input v-model="form.position" type="number" min="0" class="w-full border bg-white px-4 py-3" /><div class="flex gap-4"><button :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Save category</button><Link href="/admin/categories" class="py-3 text-sm font-semibold text-emerald-800">Back</Link></div></form></section></AdminLayout></template>
