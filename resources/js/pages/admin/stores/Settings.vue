<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
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
    pickup_enabled: boolean; pickup_inventory_location_id: string | null; pickup_instructions: string | null;
    logo: SelectedMedia | null; favicon: SelectedMedia | null;
}
interface StorefrontData { status: 'PUBLISHED' | 'UNPUBLISHED'; public_handle: string; theme: 'Essential'; public_url: string | null }
interface PickupReadiness { pickupAvailable: boolean; paymentAvailable: boolean; available: boolean; restrictions: string[]; paymentSettingsUrl: string | null }

const props = defineProps<{
    store: StoreData;
    settings: StoreSettingsData;
    options: { currencies: string[]; countries: Array<{ value: string; label: string }>; inventoryLocations: Array<{ value: string; label: string }> };
    mediaLimits: { max_image_mb: number };
    storefront: StorefrontData | null;
    pickupReadiness: PickupReadiness;
}>();
const page = usePage<PageProps>();
const { t } = useI18n();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canUpdate = computed(() => permissions.value.includes('stores.update'));
const canUpload = computed(() => permissions.value.includes('media.upload'));
const statusMessage = computed(() => {
    const status = page.props.status;
    if (!status) return null;

    if (status === 'Store settings saved.') return t('stores.flash.saved');
    if (status === 'Storefront published.') return t('stores.settingsPage.storefront.published');
    if (status === 'Storefront unpublished.') return t('stores.settingsPage.storefront.unpublished');
    return status;
});
const picker = ref<'logo' | 'favicon' | null>(null);
const logo = ref<SelectedMedia | null>(props.settings.logo);
const favicon = ref<SelectedMedia | null>(props.settings.favicon);
const channels = ['facebook', 'instagram', 'tiktok', 'youtube', 'whatsapp'] as const;

const form = useForm({
    display_name: props.settings.display_name, short_description: props.settings.short_description,
    contact_email: props.settings.contact_email, contact_phone: props.settings.contact_phone,
    whatsapp_phone: props.settings.whatsapp_phone, address_line_1: props.settings.address_line_1,
    address_line_2: props.settings.address_line_2, district: props.settings.district,
    province: props.settings.province, department: props.settings.department,
    country_code: props.settings.country_code, postal_code: props.settings.postal_code,
    social_links: { ...props.settings.social_links }, currency: props.store.currency,
    timezone: props.store.timezone, locale: props.store.locale,
    logo_media_asset_id: props.settings.logo_media_asset_id,
    favicon_media_asset_id: props.settings.favicon_media_asset_id,
    primary_color: props.settings.primary_color, secondary_color: props.settings.secondary_color,
    pickup_enabled: props.settings.pickup_enabled,
    pickup_inventory_location_id: props.settings.pickup_inventory_location_id,
    pickup_instructions: props.settings.pickup_instructions,
    store_updated_at: props.store.updated_at, settings_updated_at: props.settings.updated_at,
});
const publicationForm = useForm<{ storefront: null }>({ storefront: null });
const setStorefrontPublication = (publish: boolean) => {
    if (!canUpdate.value || !props.storefront || publicationForm.processing) return;
    const message = publish
        ? t('stores.settingsPage.storefront.publishConfirm')
        : t('stores.settingsPage.storefront.unpublishConfirm');
    if (!window.confirm(message)) return;

    publicationForm.post(`/admin/stores/${props.store.id}/settings/storefront/${publish ? 'publish' : 'unpublish'}`, {
        preserveScroll: true,
    });
};
const pickerSelectedIds = computed(() => {
    const selectedId = picker.value === 'logo' ? form.logo_media_asset_id : form.favicon_media_asset_id;
    return selectedId ? [selectedId] : [];
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
const save = () => {
    if (!canUpdate.value || form.processing) return;
    form.patch(`/admin/stores/${props.store.id}/settings`, {
        preserveScroll: true,
        onSuccess: async () => {
            await nextTick();
            form.store_updated_at = props.store.updated_at;
            form.settings_updated_at = props.settings.updated_at;
        },
    });
};
</script>

<template>
    <Head :title="t('stores.settingsPage.head', { name: store.name })" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-6xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('stores.title'), href: '/admin/stores' }, { label: t('stores.settingsPage.title') }]" />
            <PageHeader :title="settings.display_name || store.name" :eyebrow="t('stores.settingsPage.eyebrow', { slug: store.slug })" :description="t('stores.settingsPage.description')">
                <template v-if="canUpdate" #actions><Link :href="`/admin/stores/${store.id}/edit`" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold sm:w-auto">{{ t('stores.edit') }}</Link></template>
            </PageHeader>

            <p v-if="statusMessage" role="status" class="rounded-md border border-admin-success-border bg-admin-success-bg px-4 py-3 text-sm font-medium text-admin-success">{{ statusMessage }}</p>
            <div v-if="!canUpdate" class="rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3 text-sm text-amber-800"><p class="font-semibold">{{ t('stores.settingsPage.readOnlyTitle') }}</p><p class="mt-0.5 text-xs leading-5">{{ t('stores.settingsPage.readOnlyHelp') }}</p></div>
            <div v-else class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3 text-xs leading-5 text-admin-info"><p class="font-semibold">{{ t('stores.settingsPage.concurrencyTitle') }}</p><p class="mt-0.5">{{ t('stores.settingsPage.concurrencyHelp') }}</p></div>

            <section class="admin-card grid gap-4 p-4 sm:p-5" aria-labelledby="storefront-heading">
                <div class="border-b border-admin-border pb-4"><p class="admin-label">{{ t('stores.settingsPage.storefront.section') }}</p><h2 id="storefront-heading" class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.settingsPage.storefront.title') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.storefront.help') }}</p></div>
                <template v-if="storefront">
                    <dl class="grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="admin-label">{{ t('stores.settingsPage.storefront.status') }}</dt><dd class="mt-1 font-semibold">{{ t(`status.${storefront.status}`) }}</dd></div>
                        <div><dt class="admin-label">{{ t('stores.settingsPage.storefront.handle') }}</dt><dd class="mt-1 break-all font-mono">{{ storefront.public_handle }}</dd></div>
                        <div><dt class="admin-label">{{ t('stores.settingsPage.storefront.theme') }}</dt><dd class="mt-1 font-semibold">{{ storefront.theme }}</dd></div>
                    </dl>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <a v-if="storefront.public_url" :href="storefront.public_url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-9 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold">{{ t('stores.settingsPage.storefront.open') }}</a>
                        <button v-if="canUpdate && storefront.status === 'UNPUBLISHED'" type="button" :disabled="publicationForm.processing" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60" @click="setStorefrontPublication(true)">{{ publicationForm.processing ? t('stores.settingsPage.storefront.processing') : t('stores.settingsPage.storefront.publish') }}</button>
                        <button v-if="canUpdate && storefront.status === 'PUBLISHED'" type="button" :disabled="publicationForm.processing" class="min-h-9 rounded-md border border-admin-danger px-4 text-sm font-semibold text-admin-danger disabled:opacity-60" @click="setStorefrontPublication(false)">{{ publicationForm.processing ? t('stores.settingsPage.storefront.processing') : t('stores.settingsPage.storefront.unpublish') }}</button>
                    </div>
                    <FieldError id="storefront-error" :message="publicationForm.errors['storefront']" />
                </template>
                <p v-else class="text-sm text-admin-neutral">{{ t('stores.settingsPage.storefront.notPrepared') }}</p>
            </section>

            <form class="grid gap-4" @submit.prevent="save">
                <fieldset :disabled="!canUpdate || form.processing" class="admin-card grid min-w-0 gap-4 p-4 disabled:opacity-75 sm:grid-cols-2 sm:p-5">
                    <legend class="sr-only">{{ t('stores.settingsPage.general') }}</legend>
                    <div class="border-b border-admin-border pb-4 sm:col-span-2"><p class="admin-label">{{ t('stores.settingsPage.generalSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.settingsPage.general') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.generalHelp') }}</p></div>
                    <label for="display-name" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.displayName') }}</span><input id="display-name" v-model="form.display_name" maxlength="120" :placeholder="store.name" aria-describedby="display-name-help display-name-error" :aria-invalid="Boolean(form.errors.display_name)" class="admin-control w-full px-3 text-sm" /><span id="display-name-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.displayNameHelp') }}</span><FieldError id="display-name-error" :message="form.errors.display_name" /></label>
                    <label for="description" class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('stores.settingsPage.shortDescription') }}</span><textarea id="description" v-model="form.short_description" maxlength="280" rows="3" aria-describedby="description-help description-error" :aria-invalid="Boolean(form.errors.short_description)" class="admin-control min-h-24 w-full px-3 py-2 text-sm" /><span id="description-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.shortDescriptionHelp') }}</span><FieldError id="description-error" :message="form.errors.short_description" /></label>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="admin-card grid min-w-0 gap-4 p-4 disabled:opacity-75 sm:grid-cols-2 sm:p-5">
                    <legend class="sr-only">{{ t('stores.settingsPage.pickup.title') }}</legend>
                    <div class="border-b border-admin-border pb-4 sm:col-span-2"><p class="admin-label">{{ t('stores.settingsPage.pickup.section') }}</p><h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.settingsPage.pickup.title') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.pickup.help') }}</p></div>
                    <label class="flex items-start justify-between gap-4 rounded-md border border-admin-border bg-admin-canvas p-3.5 sm:col-span-2"><span><span class="block text-sm font-semibold">{{ t('stores.settingsPage.pickup.enabled') }}</span><span class="mt-1 block text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.pickup.enabledHelp') }}</span></span><input v-model="form.pickup_enabled" type="checkbox" class="mt-0.5 size-4 shrink-0 accent-admin-primary" /></label>
                    <label for="pickup-location" class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('stores.settingsPage.pickup.inventoryLocation') }}</span><select id="pickup-location" v-model="form.pickup_inventory_location_id" :required="form.pickup_enabled" aria-describedby="pickup-location-help pickup-location-error" :aria-invalid="Boolean(form.errors.pickup_inventory_location_id)" class="admin-control w-full px-3 text-sm"><option :value="null">{{ t('stores.settingsPage.pickup.selectLocation') }}</option><option v-for="location in options.inventoryLocations" :key="location.value" :value="location.value">{{ location.label }}</option></select><span id="pickup-location-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.pickup.inventoryHelp') }}</span><FieldError id="pickup-location-error" :message="form.errors.pickup_inventory_location_id" /></label>
                    <label for="pickup-instructions" class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('stores.settingsPage.pickup.instructions') }} <span class="normal-case text-admin-neutral">({{ t('common.optional') }})</span></span><textarea id="pickup-instructions" v-model="form.pickup_instructions" maxlength="1000" rows="3" aria-describedby="pickup-instructions-help pickup-instructions-error" :aria-invalid="Boolean(form.errors.pickup_instructions)" class="admin-control min-h-24 w-full px-3 py-2 text-sm" /><span id="pickup-instructions-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.pickup.instructionsHelp') }}</span><FieldError id="pickup-instructions-error" :message="form.errors.pickup_instructions" /></label>
                    <div class="grid gap-2 rounded-md border p-3 text-xs leading-5 sm:col-span-2" :class="pickupReadiness.available ? 'border-admin-success-border bg-admin-success-bg text-admin-success' : 'border-admin-warning-border bg-admin-warning-bg text-amber-900'">
                        <p class="font-semibold">{{ pickupReadiness.available ? t('stores.settingsPage.pickup.ready') : t('stores.settingsPage.pickup.notReady') }}</p>
                        <p>{{ t('stores.settingsPage.pickup.addressSource') }}</p>
                        <p v-if="!pickupReadiness.paymentAvailable">{{ t('stores.settingsPage.pickup.paymentRequired') }} <Link v-if="pickupReadiness.paymentSettingsUrl" :href="pickupReadiness.paymentSettingsUrl" class="font-semibold underline">{{ t('stores.settingsPage.pickup.openPayments') }}</Link></p>
                    </div>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="admin-card grid min-w-0 gap-4 p-4 disabled:opacity-75 sm:grid-cols-2 sm:p-5">
                    <legend class="sr-only">{{ t('stores.settingsPage.branding') }}</legend>
                    <div class="border-b border-admin-border pb-4 sm:col-span-2"><p class="admin-label">{{ t('stores.settingsPage.brandingSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.settingsPage.branding') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.brandingHelp', { max: mediaLimits.max_image_mb }) }}</p></div>
                    <div class="grid content-start gap-2 rounded-md border border-admin-border bg-admin-canvas p-3">
                        <span class="admin-label">{{ t('stores.settingsPage.logo') }}</span>
                        <div v-if="logo" class="flex min-w-0 items-center gap-3"><MediaThumbnail :src="logo.thumbnail_url" :alt="logo.alt_text" size="lg" /><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ logo.title || logo.original_filename }}</p><button v-if="canUpdate" type="button" class="mt-1 text-xs font-semibold text-admin-danger underline" @click="clearMedia('logo')">{{ t('stores.settingsPage.removeReference') }}</button></div></div>
                        <p v-else class="text-xs text-admin-neutral">{{ t('stores.settingsPage.noLogo') }}</p>
                        <button v-if="canUpdate" type="button" :aria-expanded="picker === 'logo'" class="min-h-9 w-fit rounded-md border border-admin-border-strong bg-white px-3 text-sm font-semibold" @click="picker = picker === 'logo' ? null : 'logo'">{{ t('stores.settingsPage.chooseMedia') }}</button>
                        <FieldError id="logo-error" :message="form.errors.logo_media_asset_id" />
                    </div>
                    <div class="grid content-start gap-2 rounded-md border border-admin-border bg-admin-canvas p-3">
                        <span class="admin-label">{{ t('stores.settingsPage.favicon') }}</span>
                        <div v-if="favicon" class="flex min-w-0 items-center gap-3"><MediaThumbnail :src="favicon.thumbnail_url" :alt="favicon.alt_text" /><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ favicon.title || favicon.original_filename }}</p><button v-if="canUpdate" type="button" class="mt-1 text-xs font-semibold text-admin-danger underline" @click="clearMedia('favicon')">{{ t('stores.settingsPage.removeReference') }}</button></div></div>
                        <p v-else class="text-xs text-admin-neutral">{{ t('stores.settingsPage.noFavicon') }}</p>
                        <button v-if="canUpdate" type="button" :aria-expanded="picker === 'favicon'" class="min-h-9 w-fit rounded-md border border-admin-border-strong bg-white px-3 text-sm font-semibold" @click="picker = picker === 'favicon' ? null : 'favicon'">{{ t('stores.settingsPage.chooseMedia') }}</button>
                        <FieldError id="favicon-error" :message="form.errors.favicon_media_asset_id" />
                    </div>
                    <div v-if="picker && canUpdate" class="rounded-md border border-admin-border bg-white p-3 sm:col-span-2"><p class="mb-3 text-sm font-semibold">{{ picker === 'logo' ? t('stores.settingsPage.chooseLogo') : t('stores.settingsPage.chooseFavicon') }}</p><MediaPicker :selected-ids="pickerSelectedIds" :allow-upload="canUpload" :disabled="form.processing" :max-image-mb="mediaLimits.max_image_mb" @select="chooseMedia" /></div>
                    <label for="primary-color" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.primaryColor') }}</span><span class="flex min-w-0 gap-2"><input v-model="form.primary_color" type="color" :aria-label="t('stores.settingsPage.primaryColorPicker')" class="h-9 w-12 shrink-0 rounded border border-admin-border bg-white p-1" /><input id="primary-color" v-model="form.primary_color" required maxlength="7" aria-describedby="primary-color-error" :aria-invalid="Boolean(form.errors.primary_color)" class="admin-control min-w-0 flex-1 px-3 font-mono text-sm uppercase" /></span><FieldError id="primary-color-error" :message="form.errors.primary_color" /></label>
                    <label for="secondary-color" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.secondaryColor') }} <span class="normal-case text-admin-neutral">({{ t('common.optional') }})</span></span><input id="secondary-color" v-model="form.secondary_color" maxlength="7" placeholder="#D8F36A" aria-describedby="secondary-color-error" :aria-invalid="Boolean(form.errors.secondary_color)" class="admin-control w-full px-3 font-mono text-sm uppercase" /><FieldError id="secondary-color-error" :message="form.errors.secondary_color" /></label>
                    <p class="text-xs leading-5 text-admin-neutral sm:col-span-2">{{ t('stores.settingsPage.privateMediaHelp') }}</p>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="admin-card grid min-w-0 gap-4 p-4 disabled:opacity-75 sm:grid-cols-2 sm:p-5">
                    <legend class="sr-only">{{ t('stores.settingsPage.contactAddress') }}</legend>
                    <div class="border-b border-admin-border pb-4 sm:col-span-2"><p class="admin-label">{{ t('stores.settingsPage.contactSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.settingsPage.contactAddress') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.contactHelp') }}</p></div>
                    <label for="email" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.email') }}</span><input id="email" v-model="form.contact_email" type="email" maxlength="254" autocomplete="email" aria-describedby="email-error" :aria-invalid="Boolean(form.errors.contact_email)" class="admin-control w-full px-3 text-sm" /><FieldError id="email-error" :message="form.errors.contact_email" /></label>
                    <label for="phone" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.phone') }}</span><input id="phone" v-model="form.contact_phone" type="tel" placeholder="+51987654321" autocomplete="tel" aria-describedby="phone-help phone-error" :aria-invalid="Boolean(form.errors.contact_phone)" class="admin-control w-full px-3 text-sm" /><span id="phone-help" class="text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.phoneHelp') }}</span><FieldError id="phone-error" :message="form.errors.contact_phone" /></label>
                    <label for="whatsapp-phone" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.whatsappPhone') }}</span><input id="whatsapp-phone" v-model="form.whatsapp_phone" type="tel" placeholder="+51987654321" aria-describedby="whatsapp-phone-error" :aria-invalid="Boolean(form.errors.whatsapp_phone)" class="admin-control w-full px-3 text-sm" /><FieldError id="whatsapp-phone-error" :message="form.errors.whatsapp_phone" /></label>
                    <label for="country" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.country') }}</span><select id="country" v-model="form.country_code" required aria-describedby="country-error" :aria-invalid="Boolean(form.errors.country_code)" class="admin-control w-full px-3 text-sm"><option v-for="country in options.countries" :key="country.value" :value="country.value">{{ country.value === 'PE' ? t('stores.settingsPage.peru') : country.label }}</option></select><FieldError id="country-error" :message="form.errors.country_code" /></label>
                    <label for="address-1" class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('stores.settingsPage.address1') }}</span><input id="address-1" v-model="form.address_line_1" maxlength="255" autocomplete="address-line1" aria-describedby="address-1-error" :aria-invalid="Boolean(form.errors.address_line_1)" class="admin-control w-full px-3 text-sm" /><FieldError id="address-1-error" :message="form.errors.address_line_1" /></label>
                    <label for="address-2" class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('stores.settingsPage.address2') }}</span><input id="address-2" v-model="form.address_line_2" maxlength="255" autocomplete="address-line2" aria-describedby="address-2-error" :aria-invalid="Boolean(form.errors.address_line_2)" class="admin-control w-full px-3 text-sm" /><FieldError id="address-2-error" :message="form.errors.address_line_2" /></label>
                    <label for="department" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.department') }}</span><input id="department" v-model="form.department" maxlength="120" autocomplete="address-level1" aria-describedby="department-error" :aria-invalid="Boolean(form.errors.department)" class="admin-control w-full px-3 text-sm" /><FieldError id="department-error" :message="form.errors.department" /></label>
                    <label for="province" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.province') }}</span><input id="province" v-model="form.province" maxlength="120" autocomplete="address-level2" aria-describedby="province-error" :aria-invalid="Boolean(form.errors.province)" class="admin-control w-full px-3 text-sm" /><FieldError id="province-error" :message="form.errors.province" /></label>
                    <label for="district" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.district') }}</span><input id="district" v-model="form.district" maxlength="120" autocomplete="address-level3" aria-describedby="district-error" :aria-invalid="Boolean(form.errors.district)" class="admin-control w-full px-3 text-sm" /><FieldError id="district-error" :message="form.errors.district" /></label>
                    <label for="postal-code" class="grid gap-1.5"><span class="admin-label">{{ t('stores.settingsPage.postalCode') }}</span><input id="postal-code" v-model="form.postal_code" maxlength="32" autocomplete="postal-code" aria-describedby="postal-code-error" :aria-invalid="Boolean(form.errors.postal_code)" class="admin-control w-full px-3 text-sm" /><FieldError id="postal-code-error" :message="form.errors.postal_code" /></label>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="admin-card grid min-w-0 gap-4 p-4 disabled:opacity-75 sm:grid-cols-2 sm:p-5">
                    <legend class="sr-only">{{ t('stores.settingsPage.socialLinks') }}</legend>
                    <div class="border-b border-admin-border pb-4 sm:col-span-2"><p class="admin-label">{{ t('stores.settingsPage.socialSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.settingsPage.socialLinks') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.socialHelp') }}</p></div>
                    <label v-for="channel in channels" :key="channel" :for="`social-${channel}`" class="grid min-w-0 gap-1.5"><span class="admin-label">{{ t(`stores.settingsPage.channels.${channel}`) }}</span><input :id="`social-${channel}`" v-model="form.social_links[channel]" type="url" inputmode="url" placeholder="https://" maxlength="500" :aria-describedby="`social-${channel}-error`" :aria-invalid="Boolean(form.errors[`social_links.${channel}`])" class="admin-control min-w-0 w-full px-3 text-sm" /><FieldError :id="`social-${channel}-error`" :message="form.errors[`social_links.${channel}`]" /></label>
                </fieldset>

                <fieldset :disabled="!canUpdate || form.processing" class="admin-card grid min-w-0 gap-4 p-4 disabled:opacity-75 sm:grid-cols-3 sm:p-5">
                    <legend class="sr-only">{{ t('stores.settingsPage.regionalization') }}</legend>
                    <div class="border-b border-admin-border pb-4 sm:col-span-3"><p class="admin-label">{{ t('stores.settingsPage.regionalSection') }}</p><h2 class="mt-1 text-base font-semibold text-admin-primary">{{ t('stores.settingsPage.regionalization') }}</h2><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('stores.settingsPage.regionalHelp') }}</p></div>
                    <label for="currency" class="grid gap-1.5"><span class="admin-label">{{ t('stores.currency') }}</span><select id="currency" v-model="form.currency" required aria-describedby="currency-error" :aria-invalid="Boolean(form.errors.currency)" class="admin-control w-full px-3 text-sm"><option v-for="currency in options.currencies" :key="currency" :value="currency">{{ t(`stores.currencies.${currency}`) }}</option></select><FieldError id="currency-error" :message="form.errors.currency" /></label>
                    <label for="locale" class="grid gap-1.5"><span class="admin-label">{{ t('stores.locale') }}</span><input id="locale" v-model="form.locale" required placeholder="es_PE" maxlength="10" aria-describedby="locale-error" :aria-invalid="Boolean(form.errors.locale)" class="admin-control w-full px-3 text-sm" /><FieldError id="locale-error" :message="form.errors.locale" /></label>
                    <label for="timezone" class="grid gap-1.5"><span class="admin-label">{{ t('stores.timezone') }}</span><input id="timezone" v-model="form.timezone" required placeholder="America/Lima" aria-describedby="timezone-error" :aria-invalid="Boolean(form.errors.timezone)" class="admin-control w-full px-3 text-sm" /><FieldError id="timezone-error" :message="form.errors.timezone" /></label>
                    <p class="text-xs leading-5 text-admin-neutral sm:col-span-3">{{ t('stores.settingsPage.currencyBoundary') }}</p>
                </fieldset>

                <div v-if="canUpdate" class="sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-md border border-admin-border bg-white/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-admin-neutral" aria-live="polite">{{ form.processing ? t('stores.settingsPage.processingHelp') : t('stores.settingsPage.saveHelp') }}</p>
                    <button type="submit" :disabled="form.processing" class="min-h-9 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60">{{ form.processing ? t('stores.settingsPage.saving') : t('stores.settingsPage.save') }}</button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
