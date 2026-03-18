import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { useThemeMode } from '@/lib/theme';

export default function Guest({ children }: PropsWithChildren) {
    const { themeMode, toggleTheme } = useThemeMode();

    return (
        <div className="app-shell relative min-h-screen overflow-hidden">

            <div className="relative mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-8 sm:px-6">
                <div className="glass-panel w-full p-4 sm:p-6">
                    <div className="mb-3 flex justify-end">
                        <button type="button" className="theme-toggle-btn" onClick={toggleTheme}>
                            {themeMode === 'dark' ? 'Light Mode' : 'Dark Mode'}
                        </button>
                    </div>

                    <div className="mb-6 flex items-center justify-center gap-3">
                        <Link href="/" className="inline-flex items-center gap-3">
                            <ApplicationLogo />
                        </Link>
                    </div>

                    <div className="mx-auto w-full max-w-4xl">{children}</div>
                </div>
            </div>
        </div>
    );
}
