<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import MediaPicker, { type PickerMedia } from '../../../components/media/MediaPicker.vue';
import MediaThumbnail from '../../../components/media/MediaThumbnail.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface SelectedMedia extends PickerMedia {}
interface StoreData { id: string; name: string; slug: string; currency: string; timezone: string; locale: string; updated_at: string }
interface StoreSettingsData {
    display_name: string | null; short_description: string | null;
    contact_email: string | null; contact_phone: string | null; whatsapp_phone: string | null;
    address_line_1: string | null; address_line_2: string | null; district: string | null;
    province: string | null; department: string | null; country_code: string; postal_code: string | null;
    social_links: Record<'facebook' | 'instagram' | 'tiktok' | 'youtube' | 'whatsapp', string | null>;
    logo_media_asset_id: string | null; favicon_media_asset_id: string | null;
    primary_color: string; secondary_color: string | null; updated_at: string;
    logo: SelectedMedia | null; favicon: SelectedMedia | null;
}

const props = defineProps<{
    store: StoreData;
    settings: StoreSettingsData;
    options: { currencies: string[]; countries: Array<{ value: string; label: string }> };
}>();
const page = usePage<PageProps>();
const canUpdate = computed(() => page.props.currentTenantMembership?.permissions.includes('stores.update') ?? false);
const canUpload = computed(() => page.props.currentTenantMembership?.permissions.includes('media.upload') ?? false);
const picker = ref<'logo' | 'favicon' | null>(null);
const logo = ref<SelectedMedia | null>(props.settings.logo);
const favicon = ref<SelectedMedia | null>(props.settings.favicon);
const channels = ['facebook', 'instagram', 'tiktok', 'youtube', 'whatsapp'] as const;

const form = useForm({
    display_name: props.settings.display_name,
    short_description: props.settings.short_description,
    contact_email: props.settings.contact_email,
    contact_phone: props.settings.contact_phone,
    whatsapp_phone: props.settings.whatsapp_phone,
    address_line_1: props.settings.address_line_1,
    address_line_2: props.settings.address_line_2,
    district: props.settings.district,
    province: props.settings.province,
    department: props.settings.department,
    country_code: props.settings.country_code,
    postal_code: props.settings.postal_code,
    social_links: props.settings.social_links,
    currency: props.store.currency,
    timezone: props.store.timezone,
    locale: props.store.locale,
    logo_media_asset_id: props.settings.logo_media_asset_id,
    favicon_media_asset_id: props.settings.favicon_media_asset_id,
    primary_color: props.settings.primary_color,
    secondary_color: props.settings.secondary_color,
    store_updated_at: props.store.updated_at,
    settings_updated_at: props.settings.updated_at,
});

const chooseMedia = (asset: PickerMedia) => {
    if (picker.value === 'logo') { logo.value = asset; form.logo_media_asset_id = asset.id; }
    if (picker.value === 'favicon') { favicon.value = asset; form.favicon_media_asset_id = asset.id; }
    picker.value = null;
};
const clearMedia = (kind: 'logo' | 'favicon') => {
    if (kind === 'logo') { logo.value = null; form.logo_media_asset_id = null; }
    else { favicon.value = null; form.favicon_media_asset_id = null; }
};
const save = () => form.patch(`/admin/stores/${props.store.id}/settings`, {
    preserveScroll: true,
    onSuccess: async () => {
        await nextTick();
        form.store_updated_at = props.store.updated_at;
        form.settings_updated_at = props.settings.updated_at;
    },
});
</script>

<template>
    <Head :title="`Settings · ${store.name}`" />
    <AdminLayout>
        <section class="max-w-5xl">
            <Link href="/admin/stores" class="text-sm font-semibold text-emerald-800 hover:underline">Stores</Link>
            <p class="mt-5 font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ store.slug }}</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ settings.display_name || store.name }}</h1>
            <p class="mt-4 max-w-3xl text-[#516057]">Commercial identity and administrative preferences for this Store. Company fiscal identity remains in Fiscal settings.</p>
            <p v-if="page.props.status" class="mt-6 border border-emerald-800/25 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ page.props.status }}</p>
            <p v-if="!canUpdate" class="mt-6 border border-amber-700/25 bg-amber-50 p-4 text-sm text-amber-900">You have read-only access to these settings.</p>

            <form class="mt-10 grid gap-8" @submit.prevent="save">
                <fieldset :disabled="!canUpdate || form.processing" class="grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 disabled:opacity-75 sm:grid-cols-2 sm:p-8">
                    <legend class="px-2 text-xl font-semibold">General</legend>
                    <div><label for="display-name" class="mb-2 block text-sm font-semibold">Display name</label><input id="display-name" v-model="form.display_name" maxlength="120" :placeholder="store.name" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.display_name" /></div>
                    <div class="sm:col-span-2"><label for="description" class="mb-2 block text-sm font-semibold">Short description</label><textarea id="description" v-model="form.short_description" maxlength="280" rows="3" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.short_description" /></div>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 disabled:opacity-75 sm:grid-cols-2 sm:p-8">
                    <legend class="px-2 text-xl font-semibold">Visual identity</legend>
                    <div class="grid content-start gap-3">
                        <span class="text-sm font-semibold">Logo</span>
                        <div v-if="logo" class="flex items-center gap-4"><MediaThumbnail :src="logo.thumbnail_url" :alt="logo.alt_text" size="lg" /><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ logo.title || logo.original_filename }}</p><button type="button" class="mt-2 text-sm text-red-700 underline" @click="clearMedia('logo')">Remove reference</button></div></div>
                        <button type="button" class="w-fit border px-4 py-2 text-sm font-semibold" @click="picker = picker === 'logo' ? null : 'logo'">Choose from Media</button>
                        <FieldError :message="form.errors.logo_media_asset_id" />
                    </div>
                    <div class="grid content-start gap-3">
                        <span class="text-sm font-semibold">Favicon</span>
                        <div v-if="favicon" class="flex items-center gap-4"><MediaThumbnail :src="favicon.thumbnail_url" :alt="favicon.alt_text" /><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ favicon.title || favicon.original_filename }}</p><button type="button" class="mt-2 text-sm text-red-700 underline" @click="clearMedia('favicon')">Remove reference</button></div></div>
                        <button type="button" class="w-fit border px-4 py-2 text-sm font-semibold" @click="picker = picker === 'favicon' ? null : 'favicon'">Choose from Media</button>
                        <FieldError :message="form.errors.favicon_media_asset_id" />
                    </div>
                    <div v-if="picker" class="border bg-white p-4 sm:col-span-2"><MediaPicker :selected-ids="[form.logo_media_asset_id, form.favicon_media_asset_id].filter((id): id is string => Boolean(id))" :allow-upload="canUpload" @select="chooseMedia" /></div>
                    <div><label for="primary-color" class="mb-2 block text-sm font-semibold">Primary color</label><div class="flex gap-2"><input v-model="form.primary_color" type="color" class="h-12 w-16 border bg-white p-1" /><input id="primary-color" v-model="form.primary_color" maxlength="7" class="min-w-0 flex-1 border bg-white px-4 py-3 font-mono uppercase" /></div><FieldError :message="form.errors.primary_color" /></div>
                    <div><label for="secondary-color" class="mb-2 block text-sm font-semibold">Secondary color (optional)</label><input id="secondary-color" v-model="form.secondary_color" maxlength="7" placeholder="#D8F36A" class="w-full border bg-white px-4 py-3 font-mono uppercase" /><FieldError :message="form.errors.secondary_color" /></div>
                    <p class="text-sm text-[#637067] sm:col-span-2">Media masters stay private. These references prepare future storefront delivery through purpose-built derivatives; they do not create public file URLs.</p>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 disabled:opacity-75 sm:grid-cols-2 sm:p-8">
                    <legend class="px-2 text-xl font-semibold">Contact and address</legend>
                    <div><label for="email" class="mb-2 block text-sm font-semibold">Email</label><input id="email" v-model="form.contact_email" type="email" maxlength="254" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.contact_email" /></div>
                    <div><label for="phone" class="mb-2 block text-sm font-semibold">Phone</label><input id="phone" v-model="form.contact_phone" placeholder="+51987654321" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.contact_phone" /></div>
                    <div><label for="whatsapp-phone" class="mb-2 block text-sm font-semibold">WhatsApp phone</label><input id="whatsapp-phone" v-model="form.whatsapp_phone" placeholder="+51987654321" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.whatsapp_phone" /></div>
                    <div><label for="country" class="mb-2 block text-sm font-semibold">Country</label><select id="country" v-model="form.country_code" class="w-full border bg-white px-4 py-3"><option v-for="country in options.countries" :key="country.value" :value="country.value">{{ country.label }}</option></select><FieldError :message="form.errors.country_code" /></div>
                    <div class="sm:col-span-2"><label for="address-1" class="mb-2 block text-sm font-semibold">Address line 1</label><input id="address-1" v-model="form.address_line_1" maxlength="255" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.address_line_1" /></div>
                    <div class="sm:col-span-2"><label for="address-2" class="mb-2 block text-sm font-semibold">Address line 2 / reference</label><input id="address-2" v-model="form.address_line_2" maxlength="255" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.address_line_2" /></div>
                    <div><label for="department" class="mb-2 block text-sm font-semibold">Department</label><input id="department" v-model="form.department" maxlength="120" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.department" /></div>
                    <div><label for="province" class="mb-2 block text-sm font-semibold">Province</label><input id="province" v-model="form.province" maxlength="120" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.province" /></div>
                    <div><label for="district" class="mb-2 block text-sm font-semibold">District</label><input id="district" v-model="form.district" maxlength="120" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.district" /></div>
                    <div><label for="postal-code" class="mb-2 block text-sm font-semibold">Postal code</label><input id="postal-code" v-model="form.postal_code" maxlength="32" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.postal_code" /></div>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 disabled:opacity-75 sm:grid-cols-2 sm:p-8">
                    <legend class="px-2 text-xl font-semibold">Social links</legend>
                    <div v-for="channel in channels" :key="channel"><label :for="`social-${channel}`" class="mb-2 block text-sm font-semibold capitalize">{{ channel }}</label><input :id="`social-${channel}`" v-model="form.social_links[channel]" type="url" placeholder="https://" maxlength="500" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors[`social_links.${channel}`]" /></div>
                    <p class="text-sm text-[#637067] sm:col-span-2">Only HTTPS links on each network's official domains are accepted.</p>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="grid gap-6 border border-[#17211b]/15 bg-[#f8f5ed] p-6 disabled:opacity-75 sm:grid-cols-3 sm:p-8">
                    <legend class="px-2 text-xl font-semibold">Regionalization</legend>
                    <div><label for="currency" class="mb-2 block text-sm font-semibold">Currency</label><select id="currency" v-model="form.currency" class="w-full border bg-white px-4 py-3"><option v-for="currency in options.currencies" :key="currency">{{ currency }}</option></select><FieldError :message="form.errors.currency" /></div>
                    <div><label for="locale" class="mb-2 block text-sm font-semibold">Locale</label><input id="locale" v-model="form.locale" placeholder="es_PE" maxlength="10" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.locale" /></div>
                    <div><label for="timezone" class="mb-2 block text-sm font-semibold">Timezone</label><input id="timezone" v-model="form.timezone" placeholder="America/Lima" class="w-full border bg-white px-4 py-3" /><FieldError :message="form.errors.timezone" /></div>
                    <p class="text-sm text-[#637067] sm:col-span-3">Currency remains the Store's pricing authority. This setting does not convert prices or enable transactional multi-currency.</p>
                </fieldset>

                <section class="border border-dashed border-[#17211b]/25 p-6 sm:p-8">
                    <h2 class="text-xl font-semibold">Commercial options</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="bg-black/5 p-4"><p class="font-semibold">Guest checkout</p><p class="mt-2 text-sm text-[#637067]">Pending a public checkout contract. No setting is stored.</p></div>
                        <div class="bg-black/5 p-4"><p class="font-semibold">Buyer notes</p><p class="mt-2 text-sm text-[#637067]">Not supported by the current Checkout snapshot.</p></div>
                        <div class="bg-black/5 p-4"><p class="font-semibold">Order auto-confirm</p><p class="mt-2 text-sm text-[#637067]">Not configurable. Current Order transitions remain unchanged.</p></div>
                    </div>
                </section>

                <div v-if="canUpdate" class="sticky bottom-4 flex justify-end"><button type="submit" :disabled="form.processing" class="bg-[#17211b] px-6 py-3 text-sm font-semibold text-white shadow-lg disabled:opacity-60">{{ form.processing ? 'Saving…' : 'Save store settings' }}</button></div>
            </form>
        </section>
    </AdminLayout>
</template>
