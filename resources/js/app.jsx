import '../css/app.css';
import './bootstrap';

import { LocaleProvider } from '@/i18n/LocaleProvider';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Gambia SchoolOS';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <LocaleProvider>
                <App {...props} />
            </LocaleProvider>,
        );
    },
    progress: {
        color: '#14B8A6',
    },
});
