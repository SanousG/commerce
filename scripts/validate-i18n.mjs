import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { messages } from '../resources/js/i18n/messages.ts';
import { adminNavigation } from '../resources/js/components/admin/navigation.ts';

const flatten = (value, prefix = '', output = {}) => {
    for (const [key, entry] of Object.entries(value)) {
        const path = prefix ? `${prefix}.${key}` : key;

        if (typeof entry === 'string') {
            output[path] = entry;
        } else {
            flatten(entry, path, output);
        }
    }

    return output;
};

const placeholders = (value) => [...value.matchAll(/\{([A-Za-z_][A-Za-z0-9_]*)\}/g)]
    .map((match) => match[1])
    .sort();

const english = flatten(messages.en);
const spanish = flatten(messages['es-PE']);

assert.deepEqual(Object.keys(spanish).sort(), Object.keys(english).sort(), 'Vue locale keys differ');

for (const key of Object.keys(english)) {
    assert.deepEqual(placeholders(spanish[key]), placeholders(english[key]), `Placeholder mismatch: ${key}`);
}

for (const [key, value] of Object.entries(spanish)) {
    assert.doesNotMatch(value, /[�ÃÂ]/u, `Mojibake detected: ${key}`);
    assert.doesNotMatch(value, /\p{L}\?\p{L}/u, `Damaged character detected: ${key}`);
    assert.doesNotMatch(value, / \? /u, `Damaged separator detected: ${key}`);

    if (value.endsWith('?')) {
        assert.match(value, /¿/u, `Spanish question lacks opening mark: ${key}`);
    }
}

assert.equal(spanish['categories.title'], 'Categorías');
assert.equal(spanish['products.description'], 'Descripción');
assert.equal(spanish['auth.common.password'], 'Contraseña');
assert.match(spanish['auth.login.forgot'], /^¿/u);
assert.match(spanish['auth.login.title'], /^¡/u);
assert.equal(spanish['media.title'], 'Biblioteca multimedia');
assert.equal(spanish['media.upload'], 'Subir imagen');
assert.equal(spanish['media.select'], 'Seleccionar imagen');
assert.equal(spanish['media.deleteConfirm'], '¿Deseas eliminar esta imagen?');
assert.equal(english['media.deleteConfirm'], 'Do you want to delete this image?');
assert.match(spanish['media.fileHelp'], /\{max\}/u);
assert.match(spanish['media.usageCount'], /\{count\}/u);

const listingKeys = [
    'title', 'currentStore', 'description', 'create', 'back', 'search', 'searchPlaceholder',
    'product', 'enabledVariants', 'effectivePriceList', 'publishedAt', 'never',
    'defaultPriceList', 'defaultPriceHelp', 'useCompanyDefault', 'companyDefault',
    'saveDefault', 'savingDefault', 'publish', 'publishing', 'unpublish',
    'unpublishing', 'archiveConfirm', 'noMatch', 'noListings', 'noMatchHelp',
    'noListingsHelp', 'clearSearch', 'notices.created', 'notices.updated',
    'notices.published', 'notices.unpublished',
    'notices.archived', 'notices.settingsUpdated', 'notices.completed', 'errors.archivedPublish',
    'errors.archivedUnpublish', 'errors.storeInactive', 'errors.productInactive',
    'errors.activeVariant', 'errors.activePriceList', 'errors.activeCompanyPriceList',
    'errors.invalidDefaultPriceList', 'errors.missingPrices',
    'errors.actionFailed',
];
for (const key of listingKeys) {
    assert.equal(typeof english[`listings.${key}`], 'string', `Missing English listing translation: ${key}`);
    assert.equal(typeof spanish[`listings.${key}`], 'string', `Missing Spanish listing translation: ${key}`);
}
const listingPageSources = await Promise.all([
    '../resources/js/pages/admin/listings/Index.vue',
    '../resources/js/pages/admin/listings/Create.vue',
    '../resources/js/pages/admin/listings/Edit.vue',
].map((path) => readFile(new URL(path, import.meta.url), 'utf8')));
const listingPageKeys = [...new Set(listingPageSources.flatMap((source) => (
    [...source.matchAll(/\bt\('([^']+)'/gu)].map((match) => match[1])
)))];
for (const key of listingPageKeys) {
    assert.equal(typeof english[key], 'string', `Missing English Listings page translation: ${key}`);
    assert.equal(typeof spanish[key], 'string', `Missing Spanish Listings page translation: ${key}`);
    assert.notEqual(english[key], key, `Unresolved English Listings page translation: ${key}`);
    assert.notEqual(spanish[key], key, `Unresolved Spanish Listings page translation: ${key}`);
}
for (const status of ['PUBLISHED', 'UNPUBLISHED']) {
    assert.equal(typeof english[`status.${status}`], 'string', `Missing English listing status: ${status}`);
    assert.equal(typeof spanish[`status.${status}`], 'string', `Missing Spanish listing status: ${status}`);
}
assert.equal(spanish['listings.title'], 'Publicaciones');
assert.equal(spanish['listings.create'], 'Crear publicación');
assert.equal(spanish['listings.createPage.title'], 'Crear publicación');
assert.equal(spanish['listings.editPage.title'], 'Editar publicación');
assert.equal(spanish['listings.unpublish'], 'Retirar publicación');
assert.match(spanish['listings.archiveConfirm'], /^¿/u);

const storePageSources = await Promise.all([
    '../resources/js/pages/admin/stores/Index.vue',
    '../resources/js/pages/admin/stores/Create.vue',
    '../resources/js/pages/admin/stores/Edit.vue',
    '../resources/js/pages/admin/stores/Settings.vue',
].map((path) => readFile(new URL(path, import.meta.url), 'utf8')));
const storePageKeys = [...new Set(storePageSources.flatMap((source) => (
    [...source.matchAll(/\bt\('([^']+)'/gu)].map((match) => match[1])
)))];
for (const key of storePageKeys) {
    assert.equal(typeof english[key], 'string', `Missing English Stores page translation: ${key}`);
    assert.equal(typeof spanish[key], 'string', `Missing Spanish Stores page translation: ${key}`);
    assert.notEqual(english[key], key, `Unresolved English Stores page translation: ${key}`);
    assert.notEqual(spanish[key], key, `Unresolved Spanish Stores page translation: ${key}`);
}
assert.equal(spanish['stores.title'], 'Tiendas');
assert.match(spanish['stores.editPage.deactivateConfirm'], /^¿/u);
assert.match(spanish['stores.editPage.deactivateCurrentConfirm'], /^¿/u);
assert.equal(spanish['stores.flash.updated'], 'Configuración de tienda actualizada.');
assert.equal(spanish['stores.flash.saved'], 'Configuración de tienda guardada.');
for (const currency of ['PEN', 'USD', 'EUR']) {
    assert.equal(typeof english[`stores.currencies.${currency}`], 'string', `Missing English Stores currency: ${currency}`);
    assert.equal(typeof spanish[`stores.currencies.${currency}`], 'string', `Missing Spanish Stores currency: ${currency}`);
}
for (const channel of ['facebook', 'instagram', 'tiktok', 'youtube', 'whatsapp']) {
    assert.equal(typeof english[`stores.settingsPage.channels.${channel}`], 'string', `Missing English Stores channel: ${channel}`);
    assert.equal(typeof spanish[`stores.settingsPage.channels.${channel}`], 'string', `Missing Spanish Stores channel: ${channel}`);
}

const dashboardStatuses = [
    'PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED',
    'UNPAID', 'PARTIALLY_PAID', 'PAID', 'REFUNDED', 'PARTIALLY_REFUNDED',
    'UNFULFILLED', 'PARTIALLY_FULFILLED', 'FULFILLED',
    'REQUESTED', 'APPROVED', 'REJECTED', 'RECEIVED', 'INSPECTING',
    'DRAFT', 'ORDERED', 'PARTIALLY_RECEIVED',
    'GENERATED', 'SUBMISSION_PENDING', 'SUBMITTED', 'ACCEPTED',
];
for (const status of dashboardStatuses) {
    assert.equal(typeof english[`status.${status}`], 'string', `Missing English dashboard status: ${status}`);
    assert.equal(typeof spanish[`status.${status}`], 'string', `Missing Spanish dashboard status: ${status}`);
}
const reverseLogisticsSources = await Promise.all([
    '../resources/js/pages/admin/returns/Index.vue',
    '../resources/js/pages/admin/returns/Show.vue',
    '../resources/js/pages/admin/exchanges/Index.vue',
    '../resources/js/pages/admin/exchanges/Show.vue',
    '../resources/js/pages/admin/refunds/Index.vue',
    '../resources/js/pages/admin/refunds/Show.vue',
].map((path) => readFile(new URL(path, import.meta.url), 'utf8')));
const reverseLogisticsKeys = [...new Set(reverseLogisticsSources.flatMap((source) => (
    [...source.matchAll(/\bt\('([^']+)'/gu)].map((match) => match[1])
)))];
for (const key of reverseLogisticsKeys) {
    assert.equal(typeof english[key], 'string', `Missing English reverse-logistics translation: ${key}`);
    assert.equal(typeof spanish[key], 'string', `Missing Spanish reverse-logistics translation: ${key}`);
    assert.notEqual(english[key], key, `Unresolved English reverse-logistics translation: ${key}`);
    assert.notEqual(spanish[key], key, `Unresolved Spanish reverse-logistics translation: ${key}`);
}
const procurementSources = await Promise.all([
    '../resources/js/pages/admin/suppliers/Index.vue',
    '../resources/js/pages/admin/suppliers/Create.vue',
    '../resources/js/pages/admin/suppliers/Edit.vue',
    '../resources/js/pages/admin/purchase-orders/Index.vue',
    '../resources/js/pages/admin/purchase-orders/Create.vue',
    '../resources/js/pages/admin/purchase-orders/Show.vue',
].map((path) => readFile(new URL(path, import.meta.url), 'utf8')));
const procurementKeys = [...new Set(procurementSources.flatMap((source) => (
    [...source.matchAll(/\bt\('([^']+)'/gu)].map((match) => match[1])
)))];
for (const key of procurementKeys) {
    assert.equal(typeof english[key], 'string', `Missing English Procurement page translation: ${key}`);
    assert.equal(typeof spanish[key], 'string', `Missing Spanish Procurement page translation: ${key}`);
    assert.notEqual(english[key], key, `Unresolved English Procurement page translation: ${key}`);
    assert.notEqual(spanish[key], key, `Unresolved Spanish Procurement page translation: ${key}`);
}
for (const status of ['ACTIVE', 'INACTIVE', 'ARCHIVED', 'DRAFT', 'APPROVED', 'ORDERED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CANCELLED']) {
    assert.equal(typeof english[`status.${status}`], 'string', `Missing English procurement status: ${status}`);
    assert.equal(typeof spanish[`status.${status}`], 'string', `Missing Spanish procurement status: ${status}`);
}
assert.match(spanish['procurement.suppliers.archiveConfirm'], /^¿/u);
assert.match(spanish['procurement.orders.postReceiptConfirm'], /^¿/u);
const taxSource = await readFile(new URL('../resources/js/pages/admin/taxes/Index.vue', import.meta.url), 'utf8');
const taxKeys = [...new Set([...taxSource.matchAll(/\bt\('([^']+)'/gu)].map((match) => match[1]))];
for (const key of taxKeys) {
    assert.equal(typeof english[key], 'string', `Missing English Tax page translation: ${key}`);
    assert.equal(typeof spanish[key], 'string', `Missing Spanish Tax page translation: ${key}`);
    assert.notEqual(english[key], key, `Unresolved English Tax page translation: ${key}`);
    assert.notEqual(spanish[key], key, `Unresolved Spanish Tax page translation: ${key}`);
}
for (const code of ['STANDARD', 'EXEMPT', 'UNAFFECTED']) {
    assert.equal(typeof english[`taxes.codes.${code}`], 'string', `Missing English tax category code: ${code}`);
    assert.equal(typeof spanish[`taxes.codes.${code}`], 'string', `Missing Spanish tax category code: ${code}`);
}
const fiscalSources = await Promise.all([
    '../resources/js/pages/admin/fiscal/Settings.vue',
    '../resources/js/pages/admin/fiscal-documents/Index.vue',
    '../resources/js/pages/admin/fiscal-documents/Show.vue',
].map((path) => readFile(new URL(path, import.meta.url), 'utf8')));
const fiscalKeys = [...new Set(fiscalSources.flatMap((source) => (
    [...source.matchAll(/\bt\('([^']+)'/gu)].map((match) => match[1])
)))];
for (const key of fiscalKeys) {
    assert.equal(typeof english[key], 'string', `Missing English Fiscal page translation: ${key}`);
    assert.equal(typeof spanish[key], 'string', `Missing Spanish Fiscal page translation: ${key}`);
    assert.notEqual(english[key], key, `Unresolved English Fiscal page translation: ${key}`);
    assert.notEqual(spanish[key], key, `Unresolved Spanish Fiscal page translation: ${key}`);
}
for (const status of ['DRAFT', 'GENERATED', 'SUBMISSION_PENDING', 'SUBMITTED', 'ACCEPTED', 'REJECTED', 'CANCELLED', 'PENDING', 'PROCESSING', 'SENT', 'ERROR', 'UNKNOWN']) {
    assert.equal(typeof english[`status.${status}`], 'string', `Missing English fiscal status: ${status}`);
    assert.equal(typeof spanish[`status.${status}`], 'string', `Missing Spanish fiscal status: ${status}`);
}
for (const type of ['INVOICE', 'RECEIPT', 'CREDIT_NOTE']) {
    assert.equal(typeof english[`fiscal.types.${type}`], 'string', `Missing English fiscal document type: ${type}`);
    assert.equal(typeof spanish[`fiscal.types.${type}`], 'string', `Missing Spanish fiscal document type: ${type}`);
}
assert.match(spanish['fiscal.submitConfirm'], /^¿/u);
for (const condition of ['SELLABLE', 'DAMAGED', 'DEFECTIVE', 'OPEN_BOX', 'UNKNOWN']) {
    assert.equal(typeof english[`returns.conditions.${condition}`], 'string', `Missing English return condition: ${condition}`);
    assert.equal(typeof spanish[`returns.conditions.${condition}`], 'string', `Missing Spanish return condition: ${condition}`);
}
for (const disposition of ['RESTOCK', 'SCRAP', 'QUARANTINE', 'RETURN_TO_VENDOR', 'NO_ACTION']) {
    assert.equal(typeof english[`returns.dispositions.${disposition}`], 'string', `Missing English return disposition: ${disposition}`);
    assert.equal(typeof spanish[`returns.dispositions.${disposition}`], 'string', `Missing Spanish return disposition: ${disposition}`);
}
assert.equal(english['common.unknownStatus'], 'Unknown status');
assert.equal(spanish['common.unknownStatus'], 'Estado desconocido');

const navigationKeys = adminNavigation.flatMap((section) => [
    section.translationKey,
    ...section.items.map((item) => item.translationKey),
]);
const sidebarSource = await readFile(new URL('../resources/js/components/admin/AdminSidebar.vue', import.meta.url), 'utf8');
const sidebarStaticKeys = [...sidebarSource.matchAll(/\bt\('([^']+)'\)/gu)].map((match) => match[1]);
const sidebarTranslationKeys = [...new Set([...navigationKeys, ...sidebarStaticKeys])];
for (const key of sidebarTranslationKeys) {
    assert.equal(typeof english[key], 'string', `Missing English navigation translation: ${key}`);
    assert.equal(typeof spanish[key], 'string', `Missing Spanish navigation translation: ${key}`);
    assert.notEqual(english[key], key, `Unresolved English navigation translation: ${key}`);
    assert.notEqual(spanish[key], key, `Unresolved Spanish navigation translation: ${key}`);
}
assert.equal(english['shell.notifications'], 'Notifications');
assert.equal(spanish['shell.notifications'], 'Notificaciones');
assert.equal(english['common.products'], 'Products');
assert.equal(spanish['common.products'], 'Productos');
assert.equal(english['common.categories'], 'Categories');
assert.equal(spanish['common.categories'], 'Categorías');

const i18nBootstrap = await readFile(new URL('../resources/js/i18n/index.ts', import.meta.url), 'utf8');
assert.match(i18nBootstrap, /fallbackLocale:\s*'es-PE'/u, 'Vue fallback must be es-PE');

const laravelEnglish = JSON.parse(await readFile(new URL('../lang/en.json', import.meta.url), 'utf8'));
const laravelSpanish = JSON.parse(await readFile(new URL('../lang/es-PE.json', import.meta.url), 'utf8'));
assert.deepEqual(Object.keys(laravelSpanish).sort(), Object.keys(laravelEnglish).sort(), 'Laravel JSON locale keys differ');

for (const key of Object.keys(laravelEnglish)) {
    assert.deepEqual(placeholders(laravelSpanish[key]), placeholders(laravelEnglish[key]), `Laravel placeholder mismatch: ${key}`);
}

console.log(`Validated ${Object.keys(english).length} Vue messages, ${sidebarTranslationKeys.length} sidebar labels, and ${Object.keys(laravelEnglish).length} Laravel JSON messages per locale.`);
