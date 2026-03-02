import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Alert, Card, Typography } from 'antd';

const { Title, Paragraph } = Typography;

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <Card className="mx-auto w-full max-w-md">
                <Title level={4}>Verify your email</Title>
                <Paragraph className="!text-slate-500">
                    Please confirm your email address by clicking the link we sent.
                </Paragraph>

                {status === 'verification-link-sent' && (
                    <Alert
                        className="mb-4"
                        type="success"
                        showIcon
                        message="A new verification link has been sent to your email."
                    />
                )}

                <form onSubmit={submit}>
                    <div className="mt-4 flex items-center justify-between">
                        <PrimaryButton disabled={processing}>
                            Resend Verification Email
                        </PrimaryButton>

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="rounded-md text-sm text-slate-600 underline hover:text-slate-900"
                        >
                            Log Out
                        </Link>
                    </div>
                </form>
            </Card>
        </GuestLayout>
    );
}
