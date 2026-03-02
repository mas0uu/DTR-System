import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Button, Typography } from 'antd';

const { Title, Paragraph } = Typography;

export default function Welcome({ auth }: PageProps) {
    return (
        <>
            <Head title="DTR System" />

            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
                <div className="w-full max-w-2xl text-center">
                    <p className="mb-3 text-sm font-medium uppercase tracking-[0.2em] text-blue-600">
                        Internship Tracking
                    </p>
                    <Title level={1} style={{ marginBottom: 8 }}>
                        Daily Time Record System
                    </Title>
                    <Paragraph className="mx-auto mb-8 max-w-xl text-base !text-slate-600">
                        Record attendance, monitor required hours, and manage internship details in one place.
                    </Paragraph>

                    <div className="flex justify-center gap-3">
                        {auth.user ? (
                            <Link href={route('dtr.index')}>
                                <Button type="primary" size="large">
                                    Open Dashboard
                                </Button>
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')}>
                                    <Button size="large">Log In</Button>
                                </Link>
                                <Link href={route('register')}>
                                    <Button type="primary" size="large">
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
