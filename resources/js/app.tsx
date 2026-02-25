import '../css/app.css';
import './bootstrap';
import 'antd/dist/reset.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import ModernLayout from './Layouts/ModernLayout';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        );
        
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
        color: '#000000',
    },
});
