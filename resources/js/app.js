import './bootstrap';
import './pwa'; // Import PWA registration
import '../css/app.css';
import 'v-calendar/dist/style.css';
import VCalendar from 'v-calendar';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

import { Ziggy } from './ziggy';
window.Ziggy = Ziggy;

const appName = import.meta.env.VITE_APP_NAME || 'Reminddo';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, Ziggy)
            .use(VCalendar, {})
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
