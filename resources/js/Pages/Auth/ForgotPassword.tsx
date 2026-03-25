import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Alert, Typography } from 'antd';

const { Title, Paragraph } = Typography;

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        credential: '',
        request_note: '',
    });
    const fallbackErrors = errors as Record<string, string | undefined>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout
            topLeft={(
                <Link href={route('login')} className="theme-toggle-btn inline-flex items-center">
                    Back to login
                </Link>
            )}
        >
            <Head title="Forgot Password" />

            <div className="mx-auto w-full max-w-md">
                <Title level={4}>Reset your password</Title>
                <Paragraph className="!text-slate-500">
                    Enter your email or student number. Your request will be reviewed by an administrator.
                </Paragraph>

                {status && <Alert className="mb-4" type="success" message={status} showIcon />}

                <form onSubmit={submit}>
                    <TextInput
                        id="credential"
                        type="text"
                        name="credential"
                        value={data.credential}
                        placeholder="email@example.com or INTERN100"
                        className="mt-1 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('credential', e.target.value)}
                    />

                    <InputError message={errors.credential || fallbackErrors.email} className="mt-2" />

                    <div className="mt-4">
                        <label htmlFor="request_note" className="text-sm font-medium text-slate-700">
                            Note (optional)
                        </label>
                        <textarea
                            id="request_note"
                            name="request_note"
                            value={data.request_note}
                            onChange={(e) => setData('request_note', e.target.value)}
                            rows={3}
                            placeholder="Add context (example: lost my temporary password)."
                            className="theme-text-input mt-1 block w-full rounded-md px-3 py-2 text-sm shadow-sm"
                        />
                        <InputError message={errors.request_note} className="mt-2" />
                    </div>

                    <div className="mt-4 flex items-center justify-end">
                        <PrimaryButton className="ms-4" disabled={processing}>
                            Submit Reset Request
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </GuestLayout>
    );
}
