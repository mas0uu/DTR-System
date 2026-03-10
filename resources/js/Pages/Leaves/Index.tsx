import { PageProps as AppPageProps } from '@/types';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Space, Table, Tag } from 'antd';

type LeaveRequest = {
    id: number;
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
    decision_note: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    row_status: string | null;
    row_time_in: string | null;
    row_time_out: string | null;
};

type Props = AppPageProps<{
    leave_requests: LeaveRequest[];
    is_intern: boolean;
    leave_balance: {
        initial_paid_leave_days: number;
        current_paid_leave_balance: number;
        leave_reset_month: number;
        leave_reset_day: number;
        last_leave_refresh_year: number | null;
        is_paid_leave_eligible: boolean;
    };
}>;

const statusColor = (status: LeaveRequest['status']) => {
    if (status === 'pending') return 'gold';
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    return 'default';
};

export default function EmployeeLeaveHistory() {
    const { leave_requests, is_intern, leave_balance } = usePage<Props>().props;

    return (
        <>
            <Head title={is_intern ? 'My Absence Requests' : 'My Leave Requests'} />
            <PageHeader
                title={is_intern ? 'My Absence Requests' : 'My Leave Requests'}
                subtitle={
                    is_intern
                        ? 'Absence requests are for internship attendance compliance and require admin review.'
                        : 'Leave becomes official only after admin approval. Submit requests from eligible DTR rows.'
                }
                actions={(
                    <Link href={route('dtr.index', { tab: '2' })}>
                        <Button>Open Monthly DTR</Button>
                    </Link>
                )}
            />
            {!is_intern && leave_balance.is_paid_leave_eligible && (
                <div className="mb-4 rounded-md border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800">
                    <div>
                        Paid Leave Balance: <strong>{leave_balance.current_paid_leave_balance.toFixed(2)}</strong> day(s)
                    </div>
                    <div>
                        Annual Allocation: <strong>{leave_balance.initial_paid_leave_days.toFixed(2)}</strong> day(s)
                    </div>
                    <div>
                        Reset Policy: Every {leave_balance.leave_reset_month}/{leave_balance.leave_reset_day}
                        {leave_balance.last_leave_refresh_year ? ` (last refresh: ${leave_balance.last_leave_refresh_year})` : ''}
                    </div>
                </div>
            )}
            <TableCard subtitle="History and decision status for your submitted requests.">
                <Table
                    rowKey="id"
                    dataSource={leave_requests}
                    pagination={{ pageSize: 15 }}
                    columns={[
                        { title: 'Date', dataIndex: 'leave_date' },
                        {
                            title: 'Request Type',
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
                        { title: 'Requested Days', dataIndex: 'requested_days', render: (value) => Number(value).toFixed(2) },
                        { title: 'Reason', dataIndex: 'reason', render: (value) => value || '-' },
                        {
                            title: 'Status',
                            dataIndex: 'status',
                            render: (value) => <Tag color={statusColor(value)}>{String(value).toUpperCase()}</Tag>,
                        },
                        {
                            title: 'Approved Effect',
                            render: (_, row) => (
                                <span>
                                    Paid {Number(row.approved_paid_days || 0).toFixed(2)} | Unpaid {Number(row.approved_unpaid_days || 0).toFixed(2)}
                                </span>
                            ),
                        },
                        { title: 'Deducted Balance', dataIndex: 'deducted_days', render: (value) => Number(value || 0).toFixed(2) },
                        {
                            title: 'Balance Trail',
                            render: (_, row) => (
                                row.balance_before !== null && row.balance_after !== null
                                    ? `${Number(row.balance_before).toFixed(2)} -> ${Number(row.balance_after).toFixed(2)}`
                                    : '-'
                            ),
                        },
                        { title: 'Decision Note', dataIndex: 'decision_note', render: (value) => value || '-' },
                        {
                            title: 'Attendance Row',
                            render: (_, row) => (
                                <Space>
                                    <Tag>{(row.row_status || '-').toUpperCase()}</Tag>
                                    <span className="text-xs text-slate-500">
                                        {row.row_time_in || '-'} / {row.row_time_out || '-'}
                                    </span>
                                </Space>
                            ),
                        },
                        { title: 'Submitted At', dataIndex: 'created_at', render: (value) => value || '-' },
                        { title: 'Reviewed At', dataIndex: 'reviewed_at', render: (value) => value || '-' },
                    ]}
                />
            </TableCard>
        </>
    );
}
