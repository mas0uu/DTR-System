import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Alert, Card, Typography } from 'antd';

const { Title, Paragraph } = Typography;

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <Card className="mx-auto w-full max-w-md">
                <Title level={4}>Reset your password</Title>
                <Paragraph className="!text-slate-500">
                    Enter your email and we will send a reset link.
                </Paragraph>

                {status && <Alert className="mb-4" type="success" message={status} showIcon />}

                <form onSubmit={submit}>
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />

                    <div className="mt-4 flex items-center justify-end">
                        <PrimaryButton className="ms-4" disabled={processing}>
                            Send Reset Link
                        </PrimaryButton>
                    </div>
                </form>
            </Card>
        </GuestLayout>
    );
}
