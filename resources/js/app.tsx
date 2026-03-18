import '../css/app.css';
import './bootstrap';
import 'antd/dist/reset.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import type { ReactNode } from 'react';
import ModernLayout from './Layouts/ModernLayout';
import { initializeTheme } from './lib/theme';

type InertiaPageModule = {
    default: {
        layout?: (page: ReactNode) => ReactNode;
    };
};

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
initializeTheme();

createInertiaApp({
    title: (title) => `${title} DTR System`,
    resolve: async (name) => {
        const page = (await resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        )) as InertiaPageModule;
        
        // Apply layout to all authenticated pages except auth pages
        if (!name.startsWith('Auth/') && !name.startsWith('Welcome')) {
            page.default.layout = page.default.layout || ((page: any) => <ModernLayout>{page}</ModernLayout>);
        }
        
        return page;
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#00415f',
    },
});
