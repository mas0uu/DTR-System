import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Alert, Card, Checkbox, Typography } from 'antd';

const { Title, Paragraph } = Typography;

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        credential: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <Card
                className="mx-auto w-full max-w-md"
                style={{
                    borderRadius: 16,
                    borderColor: '#dbe4f5',
                    boxShadow: '0 22px 56px rgba(37, 99, 235, 0.1)',
                    background: 'rgba(255,255,255,0.94)',
                }}
            >
                <div className="mb-6">
                    <Title level={3} className="brand-title !mb-1 text-center !text-slate-900">
                        Welcome Back
                    </Title>
                    <Paragraph className="!mb-0 text-center !text-slate-600">
                        Sign in to continue managing your internship time records.
                    </Paragraph>
                </div>

                {status && (
                    <Alert className="mb-4" type="success" message={status} showIcon />
                )}

                <form onSubmit={submit}>
                    <div className="mb-4">
                        <InputLabel htmlFor="credential" value="Student Number or Email" />

                        <TextInput
                            id="credential"
                            type="text"
                            name="credential"
                            value={data.credential}
                            onChange={(e) => setData('credential', e.target.value)}
                            placeholder="e.g., 2022123456"
                            className="mt-1 block w-full"
                            autoFocus
                        />

                        <InputError message={errors.credential} className="mt-2" />
                    </div>

                    <div className="mb-4">
                        <InputLabel htmlFor="password" value="Password" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Enter your password"
                            className="mt-1 block w-full"
                        />

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="mb-4 flex items-center">
                        <label className="flex items-center">
                            <Checkbox
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                            <span className="ms-2 text-sm text-slate-600">
                                Remember me
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-between mt-6">
                        {canResetPassword && (
                            <Link href={route('password.request')} className="text-sm font-semibold text-blue-700 hover:text-blue-900">
                                Forgot password?
                            </Link>
                        )}

                        <PrimaryButton disabled={processing}>
                            {processing ? 'Logging in...' : 'Login'}
                        </PrimaryButton>
                    </div>
                </form>

                <div className="mt-4 text-center">
                    <p className="text-sm text-slate-600">
                        Don't have an account?{' '}
                        <Link
                            href={route('register')}
                            className="font-semibold text-blue-700 hover:text-blue-900"
                        >
                            Register here
                        </Link>
                    </p>
                </div>
            </Card>
        </GuestLayout>
    );
}
