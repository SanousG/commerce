<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface IssuerProfile { id: string; country_code: string; tax_id: string; legal_name: string; trade_name: string | null; fiscal_address_line_1: string; fiscal_address_line_2: string | null; district: string | null; province: string | null; region: string | null; postal_code: string | null; ubigeo: string | null; status: string }
interface StoreSummary { id: string; name: string }
interface FiscalSeries { id: string; document_type: string; series: string; number_padding: number; next_number: number; status: string }

const props = defineProps<{ issuerProfile: IssuerProfile | null; store: StoreSummary | null; series: FiscalSeries[]; documentTypes: string[]; statuses: string[] }>();
const page = usePage<PageProps>();
const { t, te } = useI18n();
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('fiscal_settings.manage') ?? false);
const notice = computed(() => {
    const messages: Record<string, string> = {
        'Fiscal issuer profile updated.': 'fiscal.notices.profileUpdated',
        'Fiscal series created.': 'fiscal.notices.seriesCreated',
        'Fiscal series updated.': 'fiscal.notices.seriesUpdated',
    };
    return page.props.status ? t(messages[page.props.status] ?? 'fiscal.notices.completed') : undefined;
});
const profile = props.issuerProfile;
const profileForm = useForm({
    country_code: profile?.country_code ?? '', tax_id: profile?.tax_id ?? '', legal_name: profile?.legal_name ?? '', trade_name: profile?.trade_name ?? '',
    fiscal_address_line_1: profile?.fiscal_address_line_1 ?? '', fiscal_address_line_2: profile?.fiscal_address_line_2 ?? '', district: profile?.district ?? '',
    province: profile?.province ?? '', region: profile?.region ?? '', postal_code: profile?.postal_code ?? '', ubigeo: profile?.ubigeo ?? '', status: profile?.status ?? props.statuses[0] ?? '',
});
const seriesForm = useForm({ document_type: props.documentTypes[0] ?? '', series: '', number_padding: 8, status: props.statuses[0] ?? '' });
const seriesForms = props.series.map((item) => ({ id: item.id, nextNumber: item.next_number, form: useForm({ document_type: item.document_type, series: item.series, number_padding: item.number_padding, status: item.status }) }));
const statusLabel = (value: string) => te(`status.${value}`) ? t(`status.${value}`) : value;
const typeLabel = (value: string) => te(`fiscal.types.${value}`) ? t(`fiscal.types.${value}`) : value;
const profileError = (field: keyof typeof profileForm.errors, fallback: string) => profileForm.errors[field] ? t(fallback) : undefined;
const seriesError = (errors: Partial<Record<string, string>>, field: string, fallback: string) => {
    if (!errors[field]) return undefined;
    if (field === 'status' && errors[field]?.includes('active series')) return t('fiscal.errors.activeSeries');
    if (field === 'series' && errors[field]?.includes('taken')) return t('fiscal.errors.seriesUnique');
    return t(fallback);
};
const saveProfile = () => profileForm.patch('/admin/settings/fiscal', { preserveScroll: true });
const createSeries = () => seriesForm.post('/admin/fiscal-series', { preserveScroll: true, onSuccess: () => seriesForm.reset('series') });
const updateSeries = (entry: (typeof seriesForms)[number]) => entry.form.patch(`/admin/fiscal-series/${entry.id}`, { preserveScroll: true });
</script>

<template>
    <Head :title="t('fiscal.settingsTitle')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.compliance') }, { label: t('fiscal.settingsTitle') }]" />
            <PageHeader :title="t('fiscal.settingsTitle')" :eyebrow="t('fiscal.tenantAndStoreScope')" :description="t('fiscal.settingsDescription')" />
            <p v-if="notice" class="rounded-md border border-admin-success-border bg-admin-success-bg p-3 text-sm font-medium text-admin-success" role="status">{{ notice }}</p>
            <aside class="rounded-md border border-admin-warning-border bg-admin-warning-bg p-4">
                <p class="text-sm font-semibold text-admin-text">{{ t('fiscal.configurationOnly') }}</p>
                <p class="mt-1 text-xs leading-5 text-admin-text-secondary">{{ t('fiscal.configurationOnlyHelp') }}</p>
            </aside>
            <aside v-if="!canManage" class="rounded-md border border-admin-info-border bg-admin-info-bg p-4">
                <p class="text-sm font-semibold text-admin-text">{{ t('fiscal.readOnlyTitle') }}</p>
                <p class="mt-1 text-xs leading-5 text-admin-text-secondary">{{ t('fiscal.readOnlyHelp') }}</p>
            </aside>

            <form class="admin-card grid gap-5 p-4 sm:p-6" @submit.prevent="saveProfile">
                <div class="flex flex-col gap-1 border-b border-admin-border pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="admin-label text-admin-secondary">{{ t('fiscal.issuerSection') }}</p><h2 class="mt-1 text-lg font-semibold">{{ t('fiscal.issuerProfile') }}</h2><p class="mt-1 max-w-3xl text-sm text-admin-neutral">{{ t('fiscal.issuerHelp') }}</p></div>
                    <StatusBadge :status="profileForm.status" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.countryCode') }}</span><input v-model="profileForm.country_code" :disabled="!canManage" required maxlength="2" placeholder="PE" class="admin-control w-full px-3 font-mono text-sm uppercase disabled:bg-admin-canvas" /><FieldError :message="profileError('country_code', 'fiscal.errors.countryCode')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.taxId') }}</span><input v-model="profileForm.tax_id" :disabled="!canManage" required maxlength="80" class="admin-control w-full px-3 font-mono text-sm disabled:bg-admin-canvas" /><FieldError :message="profileError('tax_id', 'fiscal.errors.taxId')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select v-model="profileForm.status" :disabled="!canManage" required class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas"><option v-for="value in statuses" :key="value" :value="value">{{ statusLabel(value) }}</option></select><FieldError :message="profileError('status', 'fiscal.errors.status')" /></label>
                    <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('fiscal.legalName') }}</span><input v-model="profileForm.legal_name" :disabled="!canManage" required maxlength="200" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="profileError('legal_name', 'fiscal.errors.legalName')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.tradeName') }} <span class="normal-case text-admin-neutral">({{ t('common.optional') }})</span></span><input v-model="profileForm.trade_name" :disabled="!canManage" maxlength="200" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="profileError('trade_name', 'fiscal.errors.tradeName')" /></label>
                    <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('fiscal.fiscalAddress') }}</span><input v-model="profileForm.fiscal_address_line_1" :disabled="!canManage" required maxlength="255" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="profileError('fiscal_address_line_1', 'fiscal.errors.address')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.addressLine2') }}</span><input v-model="profileForm.fiscal_address_line_2" :disabled="!canManage" maxlength="255" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="profileError('fiscal_address_line_2', 'fiscal.errors.address')" /></label>
                    <label v-for="field in ([['district', 'fiscal.district', 120], ['province', 'fiscal.province', 120], ['region', 'fiscal.region', 120], ['postal_code', 'fiscal.postalCode', 32], ['ubigeo', 'fiscal.ubigeo', 20]] as const)" :key="field[0]" class="grid gap-1.5"><span class="admin-label">{{ t(field[1]) }}</span><input v-model="profileForm[field[0]]" :disabled="!canManage" :maxlength="field[2]" class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas" /><FieldError :message="profileForm.errors[field[0]] ? t('fiscal.errors.optionalAddress') : undefined" /></label>
                </div>
                <div v-if="canManage" class="flex justify-end"><button :disabled="profileForm.processing || !profileForm.isDirty" class="min-h-10 w-full rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">{{ profileForm.processing ? t('common.saving') : t('fiscal.saveIssuer') }}</button></div>
            </form>

            <section class="grid gap-3 border-t border-admin-border pt-6" aria-labelledby="series-heading">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><p class="admin-label text-admin-secondary">{{ t('fiscal.seriesSection') }}</p><h2 id="series-heading" class="mt-1 text-lg font-semibold">{{ t('fiscal.documentSeries') }}</h2><p class="mt-1 max-w-3xl text-sm text-admin-neutral">{{ store ? t('fiscal.seriesHelp', { store: store.name }) : t('fiscal.noStoreHelp') }}</p></div><p class="font-mono text-xs text-admin-neutral">{{ t('fiscal.nextNumberManaged') }}</p></div>
                <aside v-if="!store" class="rounded-md border border-admin-warning-border bg-admin-warning-bg p-4"><p class="text-sm font-semibold">{{ t('fiscal.noStore') }}</p><p class="mt-1 text-xs text-admin-text-secondary">{{ t('fiscal.noStoreHelp') }}</p></aside>
                <template v-else-if="seriesForms.length">
                    <div class="admin-card hidden overflow-hidden lg:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[20%] p-3">{{ t('fiscal.documentType') }}</th><th class="w-[18%] p-3">{{ t('fiscal.series') }}</th><th class="w-[14%] p-3">{{ t('fiscal.padding') }}</th><th class="w-[16%] p-3">{{ t('fiscal.nextNumber') }}</th><th class="w-[16%] p-3">{{ t('common.status') }}</th><th class="w-[16%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><template v-for="entry in seriesForms" :key="entry.id"><tr>
                        <td class="p-3"><select v-model="entry.form.document_type" :disabled="!canManage || entry.nextNumber > 1" required class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas"><option v-for="value in documentTypes" :key="value" :value="value">{{ typeLabel(value) }}</option></select></td>
                        <td class="p-3"><input v-model="entry.form.series" :disabled="!canManage || entry.nextNumber > 1" required maxlength="40" class="admin-control w-full px-2 font-mono text-xs uppercase disabled:bg-admin-canvas" /></td>
                        <td class="p-3"><input v-model.number="entry.form.number_padding" :disabled="!canManage || entry.nextNumber > 1" required type="number" min="1" max="20" class="admin-control w-full px-2 font-mono text-xs disabled:bg-admin-canvas" /></td>
                        <td class="p-3 font-mono tabular-nums">{{ entry.nextNumber }}</td>
                        <td class="p-3"><select v-model="entry.form.status" :disabled="!canManage" required class="admin-control w-full px-2 text-xs disabled:bg-admin-canvas"><option v-for="value in statuses" :key="value" :value="value">{{ statusLabel(value) }}</option></select></td>
                        <td class="p-3 text-right"><button v-if="canManage" :disabled="entry.form.processing || !entry.form.isDirty" class="min-h-9 rounded-md bg-admin-primary px-3 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" @click="updateSeries(entry)">{{ entry.form.processing ? t('common.saving') : t('common.save') }}</button><StatusBadge v-else :status="entry.form.status" /></td>
                    </tr><tr v-if="entry.form.hasErrors"><td colspan="6" class="bg-admin-danger-bg px-3 py-2"><FieldError :message="seriesError(entry.form.errors, 'document_type', 'fiscal.errors.documentType') ?? seriesError(entry.form.errors, 'series', 'fiscal.errors.series') ?? seriesError(entry.form.errors, 'number_padding', 'fiscal.errors.padding') ?? seriesError(entry.form.errors, 'status', 'fiscal.errors.status')" /></td></tr></template></tbody></table></div>
                    <div class="grid gap-3 lg:hidden"><form v-for="entry in seriesForms" :key="`mobile-${entry.id}`" class="admin-card grid min-w-0 gap-3 p-4 sm:grid-cols-2" @submit.prevent="updateSeries(entry)"><div class="flex items-start justify-between gap-3 sm:col-span-2"><div class="min-w-0"><p class="admin-label">{{ typeLabel(entry.form.document_type) }}</p><p class="mt-1 truncate font-mono font-semibold">{{ entry.form.series }}</p></div><StatusBadge :status="entry.form.status" /></div>
                        <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.documentType') }}</span><select v-model="entry.form.document_type" :disabled="!canManage || entry.nextNumber > 1" required class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas"><option v-for="value in documentTypes" :key="value" :value="value">{{ typeLabel(value) }}</option></select></label>
                        <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.series') }}</span><input v-model="entry.form.series" :disabled="!canManage || entry.nextNumber > 1" required maxlength="40" class="admin-control w-full px-3 font-mono text-sm uppercase disabled:bg-admin-canvas" /></label>
                        <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.padding') }}</span><input v-model.number="entry.form.number_padding" :disabled="!canManage || entry.nextNumber > 1" required type="number" min="1" max="20" class="admin-control w-full px-3 font-mono text-sm disabled:bg-admin-canvas" /></label>
                        <div class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.nextNumber') }}</span><p class="min-h-10 rounded-md border border-admin-border bg-admin-canvas px-3 py-2 font-mono text-sm tabular-nums">{{ entry.nextNumber }}</p></div>
                        <label class="grid gap-1.5 sm:col-span-2"><span class="admin-label">{{ t('common.status') }}</span><select v-model="entry.form.status" :disabled="!canManage" required class="admin-control w-full px-3 text-sm disabled:bg-admin-canvas"><option v-for="value in statuses" :key="value" :value="value">{{ statusLabel(value) }}</option></select></label>
                        <FieldError class="sm:col-span-2" :message="seriesError(entry.form.errors, 'document_type', 'fiscal.errors.documentType') ?? seriesError(entry.form.errors, 'series', 'fiscal.errors.series') ?? seriesError(entry.form.errors, 'number_padding', 'fiscal.errors.padding') ?? seriesError(entry.form.errors, 'status', 'fiscal.errors.status')" />
                        <button v-if="canManage" :disabled="entry.form.processing || !entry.form.isDirty" class="min-h-10 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-2">{{ entry.form.processing ? t('common.saving') : t('fiscal.saveSeries') }}</button>
                    </form></div>
                </template>
                <EmptyState v-else-if="store" :title="t('fiscal.noSeries')" :description="t('fiscal.noSeriesHelp')" />
                <form v-if="canManage && store" class="admin-card grid gap-4 border-dashed p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_9rem_11rem_auto] lg:items-start" @submit.prevent="createSeries"><div class="sm:col-span-2 lg:col-span-5"><p class="admin-label text-admin-secondary">{{ t('fiscal.newSeriesSection') }}</p><h3 class="mt-1 font-semibold">{{ t('fiscal.addSeries') }}</h3><p class="mt-1 text-xs leading-5 text-admin-neutral">{{ t('fiscal.addSeriesHelp') }}</p></div>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.documentType') }}</span><select v-model="seriesForm.document_type" required class="admin-control w-full px-3 text-sm"><option v-for="value in documentTypes" :key="value" :value="value">{{ typeLabel(value) }}</option></select><FieldError :message="seriesError(seriesForm.errors, 'document_type', 'fiscal.errors.documentType')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.series') }}</span><input v-model="seriesForm.series" required maxlength="40" autocomplete="off" class="admin-control w-full px-3 font-mono text-sm uppercase" /><FieldError :message="seriesError(seriesForm.errors, 'series', 'fiscal.errors.series')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('fiscal.padding') }}</span><input v-model.number="seriesForm.number_padding" required type="number" min="1" max="20" class="admin-control w-full px-3 font-mono text-sm" /><FieldError :message="seriesError(seriesForm.errors, 'number_padding', 'fiscal.errors.padding')" /></label>
                    <label class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select v-model="seriesForm.status" required class="admin-control w-full px-3 text-sm"><option v-for="value in statuses" :key="value" :value="value">{{ statusLabel(value) }}</option></select><FieldError :message="seriesError(seriesForm.errors, 'status', 'fiscal.errors.status')" /></label>
                    <button :disabled="seriesForm.processing" class="min-h-10 w-full rounded-md bg-admin-secondary px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 lg:mt-[1.375rem] lg:w-auto">{{ seriesForm.processing ? t('common.creating') : t('fiscal.addSeries') }}</button>
                </form>
            </section>
        </section>
    </AdminLayout>
</template>
