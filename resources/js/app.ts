import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { createAdminI18n, type AdminLocale } from './i18n';

type PageModule = { default: DefineComponent };

createInertiaApp({
    title: (title) => (title ? `${title} | Commerce` : 'Commerce'),
    resolve: (name) =>
        resolvePageComponent<PageModule>(
            `./pages/${name}.vue`,
            import.meta.glob<PageModule>('./pages/**/*.vue'),
        ).then((module) => module.default),
    setup({ el, App, props, plugin }) {
        const locale = (props.initialPage.props.localization as { locale?: AdminLocale } | undefined)?.locale ?? 'es-PE';
        createApp({ render: () => h(App, props) }).use(plugin).use(createAdminI18n(locale)).mount(el);
    },
    progress: {
        color: '#2563EB',
    },
});
