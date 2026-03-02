import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="relative min-h-screen overflow-hidden bg-slate-50">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,#bfdbfe,transparent_40%),radial-gradient(circle_at_bottom_right,#fef3c7,transparent_45%)]" />

            <div className="relative mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-8 sm:px-6">
                <div className="w-full rounded-2xl border border-slate-200/70 bg-white/90 p-4 shadow-xl shadow-slate-200/60 backdrop-blur sm:p-6">
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
