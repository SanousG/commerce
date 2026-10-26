import { createI18n } from 'vue-i18n';
import { messages } from './messages';

export type AdminLocale = 'es-PE' | 'en';

export const createAdminI18n = (locale: AdminLocale) => createI18n({
    legacy: false,
    locale,
    fallbackLocale: 'es-PE',
    messages,
    missingWarn: false,
    fallbackWarn: false,
});
