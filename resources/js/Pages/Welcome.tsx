import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Button, Typography } from 'antd';

const { Title, Paragraph } = Typography;

export default function Welcome({ auth }: PageProps) {
    return (
        <>
            <Head title="DTR System" />

            <div className="app-shell flex min-h-screen items-center justify-center px-4 py-10">
                <div className="glass-panel w-full max-w-3xl p-8 text-center sm:p-12">
                    <p className="mb-3 text-sm font-semibold uppercase tracking-[0.24em] text-blue-700">
                        Internship Tracking
                    </p>
                    <Title level={1} className="brand-title !mb-2 !text-slate-900">
                        Daily Time Record System
                    </Title>
                    <Paragraph className="mx-auto mb-8 max-w-2xl text-base !text-slate-600">
                        Record attendance, monitor required hours, and manage internship details in one place.
                    </Paragraph>

                    <div className="flex flex-wrap justify-center gap-3">
                        {auth.user ? (
                            <Link href={route('dtr.index')}>
                                <Button
                                    type="primary"
                                    size="large"
                                    style={{ minWidth: 180, fontWeight: 700 }}
                                >
                                    Open Dashboard
                                </Button>
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')}>
                                    <Button size="large" style={{ minWidth: 130, fontWeight: 700 }}>
                                        Log In
                                    </Button>
                                </Link>
                                <Link href={route('register')}>
                                    <Button type="primary" size="large" style={{ minWidth: 130, fontWeight: 700 }}>
                                        Register
                                    </Button>
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
