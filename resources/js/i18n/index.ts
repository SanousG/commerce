import { createI18n, type Composer } from 'vue-i18n';

export type AdminLocale = 'es-PE' | 'en';

type MessageSetter = Pick<Composer, 'setLocaleMessage'>;

const localeLoaders: Record<AdminLocale, () => Promise<{ default: Record<string, unknown> }>> = {
    'es-PE': () => import('./locales/es-PE'),
    en: () => import('./locales/en'),
};
const catalogPromises = new Map<AdminLocale, Promise<Record<string, unknown>>>();

const loadCatalog = (locale: AdminLocale): Promise<Record<string, unknown>> => {
    const existing = catalogPromises.get(locale);

    if (existing) {
        return existing;
    }

    const pending = localeLoaders[locale]().then((module) => module.default).catch((error: unknown) => {
        catalogPromises.delete(locale);
        throw error;
    });
    catalogPromises.set(locale, pending);

    return pending;
};

export const loadAdminLocale = async (i18n: MessageSetter, locale: AdminLocale): Promise<void> => {
    i18n.setLocaleMessage(locale, await loadCatalog(locale));
};

export const createAdminI18n = async (locale: AdminLocale) => {
    const i18n = createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'es-PE',
        messages: {},
        missingWarn: false,
        fallbackWarn: false,
    });

    await Promise.all([
        loadAdminLocale(i18n.global, locale),
        ...(locale === 'en' ? [loadAdminLocale(i18n.global, 'es-PE')] : []),
    ]);

    return i18n;
};
