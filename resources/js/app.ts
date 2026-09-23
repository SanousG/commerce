import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';

type PageModule = { default: DefineComponent };

createInertiaApp({
    title: (title) => (title ? `${title} | Commerce` : 'Commerce'),
    resolve: (name) =>
        resolvePageComponent<PageModule>(
            `./pages/${name}.vue`,
            import.meta.glob<PageModule>('./pages/**/*.vue'),
        ).then((module) => module.default),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: {
        color: '#17211b',
    },
});
