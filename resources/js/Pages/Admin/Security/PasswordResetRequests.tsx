import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { PageProps as AppPageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Alert, Button, Modal, Popconfirm, Space, Table, Tag } from 'antd';
import { useMemo } from 'react';
import { useState } from 'react';

type ResetRequest = {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    credential_snapshot: string | null;
    request_note: string | null;
    decision_note: string | null;
    created_at: string | null;
    reviewed_at: string | null;
    user: {
        id: number | null;
        name: string | null;
        email: string | null;
        student_no: string | null;
        role: 'admin' | 'employee' | 'intern' | null;
        employee_type: 'regular' | 'intern' | null;
    };
    reviewer: {
        id: number | null;
        name: string | null;
        email: string | null;
    };
};

type Props = AppPageProps<{
    requests: ResetRequest[];
    flash?: {
        success?: string;
    };
    errors?: Record<string, string>;
}>;

export default function PasswordResetRequests() {
    const { requests, flash, errors } = usePage<Props>().props;
    const [openedNote, setOpenedNote] = useState<{ title: string; content: string } | null>(null);

    const pendingCount = useMemo(
        () => requests.filter((item) => item.status === 'pending').length,
        [requests],
    );
    const approvedCount = useMemo(
        () => requests.filter((item) => item.status === 'approved').length,
        [requests],
    );
    const rejectedCount = useMemo(
        () => requests.filter((item) => item.status === 'rejected').length,
        [requests],
    );
    const firstError = useMemo(() => {
        if (!errors) {
            return null;
        }

        const values = Object.values(errors).filter((value) => typeof value === 'string' && value.trim() !== '');
        return values.length > 0 ? values[0] : null;
    }, [errors]);

    const handleApprove = (id: number) => {
        router.patch(route('admin.password_reset_requests.approve', id), {}, { preserveScroll: true });
    };

    const handleReject = (id: number) => {
        const decisionNote = window.prompt('Rejection reason (required):');
        if (decisionNote === null) {
            return;
        }

        if (decisionNote.trim() === '') {
            return;
        }

        router.patch(
            route('admin.password_reset_requests.reject', id),
            { decision_note: decisionNote.trim() },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Password Resets" />
            <PageHeader
                title="Password Reset Requests"
                subtitle="Review user reset requests and issue temporary credentials securely."
            />

            {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}
            {firstError && <Alert type="error" message={firstError} showIcon className="mb-4" />}

            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <MetricCard label="Total Requests" value={requests.length} />
                <MetricCard label="Pending" value={pendingCount} />
                <MetricCard label="Approved" value={approvedCount} />
                <MetricCard label="Rejected" value={rejectedCount} />
            </div>

            <TableCard
                title="Reset Queue"
                subtitle="Approving a request generates a temporary password and forces password change on next login."
            >
                <Table
                    rowKey="id"
                    dataSource={requests}
                    pagination={{ pageSize: 12 }}
                    tableLayout="fixed"
                    columns={[
                        {
                            title: 'Account',
                            key: 'account',
                            width: 230,
                            render: (_, row) => (
                                <Space direction="vertical" size={0}>
                                    <span>{row.user.name || 'Unknown user'}</span>
                                    <span className="text-xs text-slate-500">{row.user.email || '-'}</span>
                                    <span className="text-xs text-slate-500">{row.user.student_no || '-'}</span>
                                </Space>
                            ),
                        },
                        {
                            title: 'Employee Type',
                            key: 'employee_type',
                            width: 150,
                            render: (_, row) => {
                                const value = row.user.role === 'admin'
                                    ? 'Admin'
                                    : row.user.role === 'intern' || row.user.employee_type === 'intern'
                                        ? 'Intern'
                                        : 'Employee';

                                return <Tag>{value.toUpperCase()}</Tag>;
                            },
                        },
                        {
                            title: 'Request Note',
                            key: 'request_note',
                            width: 130,
                            render: (_, row) => (
                                row.request_note ? (
                                    <Button
                                        size="small"
                                        onClick={() =>
                                            setOpenedNote({
                                                title: `Request Note • ${row.user.name || row.user.email || `Request #${row.id}`}`,
                                                content: row.request_note || '',
                                            })
                                        }
                                    >
                                        View
                                    </Button>
                                ) : (
                                    <span className="text-xs text-slate-500">No note</span>
                                )
                            ),
                        },
                        {
                            title: 'Status',
                            key: 'status',
                            width: 130,
                            render: (_, row) => (
                                <Tag color={row.status === 'pending' ? 'gold' : row.status === 'approved' ? 'green' : 'red'}>
                                    {row.status.toUpperCase()}
                                </Tag>
                            ),
                        },
                        { title: 'Requested At', dataIndex: 'created_at', width: 180 },
                        {
                            title: 'Reviewed By',
                            key: 'reviewed_by',
                            width: 200,
                            render: (_, row) => row.reviewer.name || '-',
                        },
                        {
                            title: 'Actions',
                            key: 'actions',
                            width: 190,
                            render: (_, row) =>
                                row.status !== 'pending' ? (
                                    <span className="text-xs text-slate-500">Already reviewed</span>
                                ) : (
                                    <Space>
                                        <Popconfirm
                                            title="Approve reset request?"
                                            description="A temporary password will be generated and shown once."
                                            okText="Approve"
                                            onConfirm={() => handleApprove(row.id)}
                                        >
                                            <Button size="small" type="primary">
                                                Approve
                                            </Button>
                                        </Popconfirm>
                                        <Button size="small" danger onClick={() => handleReject(row.id)}>
                                            Reject
                                        </Button>
                                    </Space>
                                ),
                        },
                    ]}
                />
            </TableCard>

            <Modal
                title={openedNote?.title || 'Request Note'}
                open={openedNote !== null}
                onCancel={() => setOpenedNote(null)}
                footer={null}
                centered
            >
                <p className="whitespace-pre-wrap break-words text-sm text-slate-700">{openedNote?.content}</p>
            </Modal>
        </>
    );
}
