<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface IssuerProfile { id: string; country_code: string; tax_id: string; legal_name: string; trade_name: string | null; fiscal_address_line_1: string; fiscal_address_line_2: string | null; district: string | null; province: string | null; region: string | null; postal_code: string | null; ubigeo: string | null; status: string }
interface StoreSummary { id: string; name: string }
interface FiscalSeries { id: string; document_type: string; series: string; number_padding: number; next_number: number; status: string }

const props = defineProps<{ issuerProfile: IssuerProfile | null; store: StoreSummary | null; series: FiscalSeries[]; documentTypes: string[]; statuses: string[] }>();
const page = usePage<PageProps>();
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('fiscal_settings.manage') ?? false);
const status = computed(() => page.props.status);
const profile = props.issuerProfile;
const profileForm = useForm({
    country_code: profile?.country_code ?? '', tax_id: profile?.tax_id ?? '', legal_name: profile?.legal_name ?? '', trade_name: profile?.trade_name ?? '',
    fiscal_address_line_1: profile?.fiscal_address_line_1 ?? '', fiscal_address_line_2: profile?.fiscal_address_line_2 ?? '', district: profile?.district ?? '',
    province: profile?.province ?? '', region: profile?.region ?? '', postal_code: profile?.postal_code ?? '', ubigeo: profile?.ubigeo ?? '', status: profile?.status ?? props.statuses[0] ?? '',
});
const seriesForm = useForm({ document_type: props.documentTypes[0] ?? '', series: '', number_padding: 8, status: props.statuses[0] ?? '' });
const seriesForms = props.series.map((item) => ({ id: item.id, nextNumber: item.next_number, form: useForm({ document_type: item.document_type, series: item.series, number_padding: item.number_padding, status: item.status }) }));
const saveProfile = () => profileForm.patch('/admin/settings/fiscal', { preserveScroll: true });
const createSeries = () => seriesForm.post('/admin/fiscal-series', { preserveScroll: true, onSuccess: () => seriesForm.reset('series') });
const updateSeries = (entry: (typeof seriesForms)[number]) => entry.form.patch(`/admin/fiscal-series/${entry.id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Fiscal settings" />
    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Fiscal configuration</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Issuer &amp; series.</h1>
            <div class="mt-6 border border-amber-800/30 bg-amber-50 p-5 text-sm text-amber-950"><strong>Configuration only.</strong> This area does not store provider credentials and does not transmit documents to SUNAT.</div>
            <p v-if="status" class="mt-5 border border-emerald-700 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ status }}</p>

            <form class="mt-10 border border-[#17211b]/15 bg-white p-5 sm:p-8" @submit.prevent="saveProfile">
                <div class="flex flex-wrap items-baseline justify-between gap-3"><h2 class="text-3xl font-semibold">Issuer profile</h2><span class="font-mono text-xs text-[#637067]">Tenant snapshot source</span></div>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold">Country code<input v-model="profileForm.country_code" :disabled="!canManage" required maxlength="2" placeholder="PE" class="mt-2 w-full border px-3 py-3 font-mono uppercase disabled:bg-stone-100" /><FieldError :message="profileForm.errors.country_code" /></label>
                    <label class="text-sm font-semibold">Tax ID<input v-model="profileForm.tax_id" :disabled="!canManage" required maxlength="80" class="mt-2 w-full border px-3 py-3 font-mono disabled:bg-stone-100" /><FieldError :message="profileForm.errors.tax_id" /></label>
                    <label class="text-sm font-semibold">Status<select v-model="profileForm.status" :disabled="!canManage" required class="mt-2 w-full border bg-white px-3 py-3 font-normal disabled:bg-stone-100"><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select><FieldError :message="profileForm.errors.status" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Legal name<input v-model="profileForm.legal_name" :disabled="!canManage" required maxlength="200" class="mt-2 w-full border px-3 py-3 font-normal disabled:bg-stone-100" /><FieldError :message="profileForm.errors.legal_name" /></label>
                    <label class="text-sm font-semibold">Trade name<input v-model="profileForm.trade_name" :disabled="!canManage" maxlength="200" class="mt-2 w-full border px-3 py-3 font-normal disabled:bg-stone-100" /><FieldError :message="profileForm.errors.trade_name" /></label>
                    <label class="text-sm font-semibold sm:col-span-2">Fiscal address<input v-model="profileForm.fiscal_address_line_1" :disabled="!canManage" required maxlength="255" class="mt-2 w-full border px-3 py-3 font-normal disabled:bg-stone-100" /><FieldError :message="profileForm.errors.fiscal_address_line_1" /></label>
                    <label class="text-sm font-semibold">Address line 2<input v-model="profileForm.fiscal_address_line_2" :disabled="!canManage" maxlength="255" class="mt-2 w-full border px-3 py-3 font-normal disabled:bg-stone-100" /><FieldError :message="profileForm.errors.fiscal_address_line_2" /></label>
                    <label v-for="field in ([['district', 'District', 120], ['province', 'Province', 120], ['region', 'Region', 120], ['postal_code', 'Postal code', 32], ['ubigeo', 'Ubigeo', 20]] as const)" :key="field[0]" class="text-sm font-semibold">{{ field[1] }}<input v-model="profileForm[field[0]]" :disabled="!canManage" :maxlength="field[2]" class="mt-2 w-full border px-3 py-3 font-normal disabled:bg-stone-100" /><FieldError :message="profileForm.errors[field[0]]" /></label>
                </div>
                <button v-if="canManage" :disabled="profileForm.processing" class="mt-6 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-40">Save issuer profile</button>
            </form>

            <section class="mt-12">
                <div class="flex flex-wrap items-baseline justify-between gap-3"><div><h2 class="text-3xl font-semibold">Document series</h2><p class="mt-2 text-sm text-[#637067]">{{ store ? `Numbering for ${store.name}` : 'Select an active store to configure numbering.' }}</p></div><span class="font-mono text-xs text-[#637067]">Next number is server-managed</span></div>
                <div v-if="store" class="mt-5 grid gap-4">
                    <form v-for="entry in seriesForms" :key="entry.id" class="grid gap-4 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_8rem_9rem_9rem_auto] lg:items-end" @submit.prevent="updateSeries(entry)">
                        <label class="text-sm font-semibold">Document type<select v-model="entry.form.document_type" :disabled="!canManage || entry.nextNumber > 1" required class="mt-2 w-full border bg-white px-3 py-2 font-normal disabled:bg-stone-100"><option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option></select></label>
                        <label class="text-sm font-semibold">Series<input v-model="entry.form.series" :disabled="!canManage || entry.nextNumber > 1" required maxlength="40" class="mt-2 w-full border px-3 py-2 font-mono uppercase disabled:bg-stone-100" /></label>
                        <label class="text-sm font-semibold">Padding<input v-model.number="entry.form.number_padding" :disabled="!canManage || entry.nextNumber > 1" required type="number" min="1" max="20" class="mt-2 w-full border px-3 py-2 font-mono font-normal disabled:bg-stone-100" /></label>
                        <div><p class="text-sm font-semibold">Next number</p><p class="mt-2 border bg-stone-100 px-3 py-2 font-mono">{{ entry.nextNumber }}</p></div>
                        <label class="text-sm font-semibold">Status<select v-model="entry.form.status" :disabled="!canManage" required class="mt-2 w-full border bg-white px-3 py-2 font-normal disabled:bg-stone-100"><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                        <button v-if="canManage" :disabled="entry.form.processing" class="bg-[#17211b] px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Save</button>
                        <FieldError class="lg:col-span-6" :message="entry.form.errors.document_type ?? entry.form.errors.series ?? entry.form.errors.number_padding ?? entry.form.errors.status" />
                    </form>
                    <p v-if="!series.length" class="border border-dashed border-[#17211b]/25 p-8 text-[#637067]">No fiscal series configured for this store.</p>
                </div>
                <form v-if="canManage && store" class="mt-5 grid gap-4 border border-dashed border-[#17211b]/30 bg-[#f8f5ed] p-5 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_8rem_9rem_auto] lg:items-end" @submit.prevent="createSeries">
                    <label class="text-sm font-semibold">Document type<select v-model="seriesForm.document_type" required class="mt-2 w-full border bg-white px-3 py-2 font-normal"><option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option></select></label>
                    <label class="text-sm font-semibold">New series<input v-model="seriesForm.series" required maxlength="40" class="mt-2 w-full border bg-white px-3 py-2 font-mono uppercase" /></label>
                    <label class="text-sm font-semibold">Padding<input v-model.number="seriesForm.number_padding" required type="number" min="1" max="20" class="mt-2 w-full border bg-white px-3 py-2 font-mono font-normal" /></label>
                    <label class="text-sm font-semibold">Status<select v-model="seriesForm.status" required class="mt-2 w-full border bg-white px-3 py-2 font-normal"><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                    <button :disabled="seriesForm.processing" class="bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Add series</button>
                    <FieldError class="lg:col-span-5" :message="seriesForm.errors.document_type ?? seriesForm.errors.series ?? seriesForm.errors.number_padding ?? seriesForm.errors.status" />
                </form>
            </section>
        </section>
    </AdminLayout>
</template>
