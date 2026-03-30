import { PageProps as AppPageProps } from '@/types';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import UserSearchControl from '@/Components/ui/UserSearchControl';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Alert, Button, Select, Space, Table, Tag } from 'antd';
import { useMemo, useState } from 'react';

type LeaveRequest = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_email: string;
    employee_type: 'intern' | 'regular' | null;
    leave_date: string;
    request_type: 'leave' | 'intern_absence';
    requested_days: number;
    is_paid: boolean;
    approved_paid_days: number;
    approved_unpaid_days: number;
    deducted_days: number;
    balance_before: number | null;
    balance_after: number | null;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    dtr_row_id: number | null;
    dtr_row_status: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    decision_note: string | null;
    created_at: string | null;
    employee_paid_leave_balance: number | null;
    employee_initial_paid_leave_days: number | null;
};

type Props = AppPageProps<{
    leave_requests: LeaveRequest[];
    flash?: {
        success?: string;
    };
}>;

export default function AdminLeaveIndex() {
    const page = usePage<Props>();
    const { leave_requests, flash } = page.props;
    const pageErrors = (page.props as unknown as { errors?: Record<string, string> }).errors;
    const [statusFilter, setStatusFilter] = useState<'all' | LeaveRequest['status']>('all');
    const [userSearch, setUserSearch] = useState('');

    const filtered = useMemo(() => {
        const query = userSearch.trim().toLowerCase();

        return leave_requests.filter((row) => {
            const passesStatus = statusFilter === 'all' || row.status === statusFilter;
            const passesSearch = query === ''
                || row.employee_name.toLowerCase().includes(query)
                || row.employee_email.toLowerCase().includes(query);

            return passesStatus && passesSearch;
        });
    }, [leave_requests, statusFilter, userSearch]);

    const statusColor = (status: LeaveRequest['status']) => {
        if (status === 'pending') return 'gold';
        if (status === 'approved') return 'green';
        if (status === 'rejected') return 'red';
        return 'default';
    };

    return (
        <>
            <Head title="Leave Requests" />
            <PageHeader
                title="Leave Requests"
                subtitle="Review and decide employee leave and internship absence submissions."
                actions={(
                    <Space>
                        <Select
                            value={statusFilter}
                            style={{ width: 180 }}
                            onChange={(value) => setStatusFilter(value)}
                            options={[
                                { label: 'All statuses', value: 'all' },
                                { label: 'Pending', value: 'pending' },
                                { label: 'Approved', value: 'approved' },
                                { label: 'Rejected', value: 'rejected' },
                                { label: 'Cancelled', value: 'cancelled' },
                            ]}
                        />
                        <Link href={route('admin.employees.index')}>
                            <Button>Employees</Button>
                        </Link>
                    </Space>
                )}
            />

            {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}
            {pageErrors?.leave_balance && <Alert type="error" message={pageErrors.leave_balance} showIcon className="mb-4" />}
            {pageErrors?.status && <Alert type="error" message={pageErrors.status} showIcon className="mb-4" />}

            <TableCard
                title="Request Queue"
                actions={(
                    <UserSearchControl value={userSearch} onChange={setUserSearch} />
                )}
            >
                <Table
                    rowKey="id"
                    dataSource={filtered}
                    pagination={{ pageSize: 15 }}
                    columns={[
                        {
                            title: 'Employee',
                            render: (_, row) => (
                                <Space direction="vertical" size={0}>
                                    <span>{row.employee_name}</span>
                                    <span className="text-xs text-gray-500">{row.employee_email}</span>
                                    {row.employee_type === 'regular' && (
                                        <Space size={4} wrap>
                                            <Tag color="blue">
                                                Balance: {Number(row.employee_paid_leave_balance || 0).toFixed(2)}
                                            </Tag>
                                            <Button
                                                size="small"
                                                onClick={() => {
                                                    const balanceInput = window.prompt('Set new paid leave balance (days):', String(row.employee_paid_leave_balance ?? 0));
                                                    if (balanceInput === null) return;
                                                    const parsed = Number(balanceInput);
                                                    if (Number.isNaN(parsed) || parsed < 0) {
                                                        window.alert('Please provide a valid non-negative number.');
                                                        return;
                                                    }
                                                    const reasonInput = window.prompt('Adjustment reason (required):');
                                                    if (!reasonInput || !reasonInput.trim()) return;
                                                    router.patch(route('admin.leaves.balance.adjust', row.employee_id), {
                                                        new_balance: parsed,
                                                        reason: reasonInput.trim(),
                                                    });
                                                }}
                                            >
                                                Adjust Balance
                                            </Button>
                                        </Space>
                                    )}
                                </Space>
                            ),
                        },
                        { title: 'Date', dataIndex: 'leave_date' },
                        {
                            title: 'Type',
                            dataIndex: 'request_type',
                            render: (value, row) => (
                                <Space>
                                    <Tag color={value === 'intern_absence' ? 'purple' : 'blue'}>
                                        {value === 'intern_absence' ? 'INTERNSHIP ABSENCE' : 'LEAVE'}
                                    </Tag>
                                    {value === 'leave' && (
                                        <Tag color={row.is_paid ? 'green' : 'default'}>
                                            {row.is_paid ? 'PAID' : 'UNPAID'}
                                        </Tag>
                                    )}
                                </Space>
                            ),
                        },
                        { title: 'Requested', dataIndex: 'requested_days', render: (value) => Number(value).toFixed(2) },
                        { title: 'Reason', dataIndex: 'reason', render: (v) => v || '-' },
                        {
                            title: 'Status',
                            render: (_, row) => <Tag color={statusColor(row.status)}>{row.status.toUpperCase()}</Tag>,
                        },
                        {
                            title: 'Approved Effect',
                            render: (_, row) => (
                                <span>
                                    Paid {Number(row.approved_paid_days || 0).toFixed(2)} | Unpaid {Number(row.approved_unpaid_days || 0).toFixed(2)}
                                </span>
                            ),
                        },
                        { title: 'Deducted', dataIndex: 'deducted_days', render: (value) => Number(value || 0).toFixed(2) },
                        {
                            title: 'Balance Trail',
                            render: (_, row) => (
                                row.balance_before !== null && row.balance_after !== null
                                    ? `${Number(row.balance_before).toFixed(2)} -> ${Number(row.balance_after).toFixed(2)}`
                                    : '-'
                            ),
                        },
                        { title: 'Decision Note', dataIndex: 'decision_note', render: (v) => v || '-' },
                        {
                            title: 'Actions',
                            render: (_, row) =>
                                row.status === 'pending' ? (
                                    <Space>
                                        <Button
                                            size="small"
                                            type="primary"
                                            onClick={() =>
                                                router.patch(route('admin.leaves.decision', row.id), {
                                                    status: 'approved',
                                                })
                                            }
                                        >
                                            {row.request_type === 'intern_absence' ? 'Approve Absence' : 'Approve Leave'}
                                        </Button>
                                        <Button
                                            size="small"
                                            danger
                                            onClick={() => {
                                                const noteInput = window.prompt('Rejection note (optional):');
                                                if (noteInput === null) {
                                                    return;
                                                }

                                                const note = noteInput.trim() || null;
                                                router.patch(route('admin.leaves.decision', row.id), {
                                                    status: 'rejected',
                                                    decision_note: note,
                                                });
                                            }}
                                        >
                                            Reject
                                        </Button>
                                    </Space>
                                ) : (
                                    <Tag>Final</Tag>
                                ),
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
