<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface CategoryChoice { id: string; name: string; parent_id: string | null }
defineProps<{ categories: CategoryChoice[] }>();

const form = useForm({ name: '', description: '', sku: '', barcode: '', categories: [] as string[] });
const submit = () => form.post('/admin/products');
</script>

<template>
    <Head title="Create product" />
    <AdminLayout>
        <section class="max-w-3xl">
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Catalog entry</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Create a product.</h1>
            <form class="mt-10 grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 sm:p-8" @submit.prevent="submit">
                <div><label for="name" class="mb-2 block text-sm font-semibold">Name</label><input id="name" v-model="form.name" required maxlength="160" autofocus class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.name" /></div>
                <div><label for="description" class="mb-2 block text-sm font-semibold">Description</label><textarea id="description" v-model="form.description" rows="4" maxlength="10000" class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="form.errors.description" /></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label for="sku" class="mb-2 block text-sm font-semibold">Default SKU</label><input id="sku" v-model="form.sku" required maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 font-mono" /><FieldError :message="form.errors.sku" /></div><div><label for="barcode" class="mb-2 block text-sm font-semibold">Barcode (optional)</label><input id="barcode" v-model="form.barcode" maxlength="120" class="w-full border border-[#17211b]/25 bg-white px-4 py-3 font-mono" /><FieldError :message="form.errors.barcode" /></div></div>
                <fieldset v-if="categories.length"><legend class="mb-3 text-sm font-semibold">Categories</legend><div class="grid gap-2 sm:grid-cols-2"><label v-for="category in categories" :key="category.id" class="flex items-center gap-3 border border-[#17211b]/15 bg-white p-3"><input v-model="form.categories" type="checkbox" :value="category.id" />{{ category.name }}</label></div><FieldError :message="form.errors.categories" /></fieldset>
                <div class="flex gap-4"><button :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Creating...' : 'Create product' }}</button><Link href="/admin/products" class="py-3 text-sm font-semibold text-emerald-800">Cancel</Link></div>
            </form>
        </section>
    </AdminLayout>
</template>
