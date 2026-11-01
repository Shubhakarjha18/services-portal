import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';

/**
 * Inertia boots every page from resources/js/Pages. A missing page throws on
 * purpose: a typo in Inertia::render() should fail loudly during development
 * instead of showing a blank shell.
 */
createInertiaApp({
    title: (title) => (title ? `${title} · Public Services Portal` : 'Public Services Portal'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Inertia page not found: Pages/${name}.vue`);
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) }).use(plugin);

        app.component('Head', Head);
        app.component('Link', Link);
        app.mount(el);
    },
    progress: {
        color: '#4f46e5',
        showSpinner: false,
    },
});
