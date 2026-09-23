<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import FieldError from '../../../components/FieldError.vue';
import type { PageProps } from '../../../types';

defineProps<{ carts: { id: string; token_hint: string; customer: string }[] }>();
const form = useForm({ cart_id: '', idempotency_key: crypto.randomUUID() });
const page = usePage<PageProps>();
const serverErrors = computed(() => page.props.errors as Record<string, string | undefined>);
const submit = () => form.post('/admin/checkouts');
</script>

<template>
    <Head title="Start checkout" />
    <AdminLayout>
        <section class="max-w-2xl">
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">No stock reserved yet</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Start checkout.</h1>
            <form class="mt-8 border border-[#17211b]/15 bg-white p-6" @submit.prevent="submit">
                <label for="cart" class="block text-sm font-semibold">Active cart</label><select id="cart" v-model="form.cart_id" required class="mt-2 w-full border border-[#17211b]/25 px-4 py-3"><option value="" disabled>Select cart</option><option v-for="cart in carts" :key="cart.id" :value="cart.id">{{ cart.token_hint }} / {{ cart.customer }}</option></select><FieldError :message="form.errors.cart_id ?? serverErrors.cart" />
                <div class="mt-6 flex gap-3"><button :disabled="form.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Starting...' : 'Continue' }}</button><Link href="/admin/checkouts" class="border border-[#17211b] px-5 py-3 text-sm font-semibold">Cancel</Link></div>
            </form>
        </section>
    </AdminLayout>
</template>
