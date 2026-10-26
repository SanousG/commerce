import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { messages } from '../resources/js/i18n/messages.ts';

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
assert.equal(english['common.unknownStatus'], 'Unknown status');
assert.equal(spanish['common.unknownStatus'], 'Estado desconocido');

const i18nBootstrap = await readFile(new URL('../resources/js/i18n/index.ts', import.meta.url), 'utf8');
assert.match(i18nBootstrap, /fallbackLocale:\s*'es-PE'/u, 'Vue fallback must be es-PE');

const laravelEnglish = JSON.parse(await readFile(new URL('../lang/en.json', import.meta.url), 'utf8'));
const laravelSpanish = JSON.parse(await readFile(new URL('../lang/es-PE.json', import.meta.url), 'utf8'));
assert.deepEqual(Object.keys(laravelSpanish).sort(), Object.keys(laravelEnglish).sort(), 'Laravel JSON locale keys differ');

for (const key of Object.keys(laravelEnglish)) {
    assert.deepEqual(placeholders(laravelSpanish[key]), placeholders(laravelEnglish[key]), `Laravel placeholder mismatch: ${key}`);
}

console.log(`Validated ${Object.keys(english).length} Vue messages and ${Object.keys(laravelEnglish).length} Laravel JSON messages per locale.`);
