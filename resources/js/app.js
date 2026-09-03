import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Nama toko datang dari Pengaturan (props bersama), bukan nama build.
let storeName = import.meta.env.VITE_APP_NAME || 'Kios BERKAH';

createInertiaApp({
    title: (title) => (title ? `${title} - ${storeName}` : storeName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        storeName = props.initialPage.props.store?.store_name || storeName;

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#1E6B4F',
    },
});
