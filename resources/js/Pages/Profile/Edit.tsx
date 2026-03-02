import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, Typography } from 'antd';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

const { Title, Paragraph } = Typography;

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <>
            <Head title="Profile" />

            <div>
                <div className="mb-6">
                    <Title level={3} style={{ marginBottom: 0 }}>
                        Profile Settings
                    </Title>
                    <Paragraph className="!mb-0 !text-slate-500">
                        Manage your personal details, password, and account access.
                    </Paragraph>
                </div>

                <div className="space-y-6">
                    <Card>
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-5xl"
                        />
                    </Card>

                    <Card>
                        <UpdatePasswordForm className="max-w-xl" />
                    </Card>

                    <Card>
                        <DeleteUserForm className="max-w-xl" />
                    </Card>
                </div>
            </div>
        </>
    );
}
