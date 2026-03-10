import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { PageProps as AppPageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Space, Table, Tag } from 'antd';
import { useMemo } from 'react';

type AuditLog = {
    id: number;
    actor_id: number | null;
    actor_name: string;
    actor_email: string | null;
    action: string;
    entity_type: string;
    entity_id: number | null;
    before_json: Record<string, unknown> | null;
    after_json: Record<string, unknown> | null;
    reason: string | null;
    ip_address: string | null;
    created_at: string | null;
};

type Props = AppPageProps<{
    audit_logs: AuditLog[];
}>;

export default function AdminAuditIndex() {
    const { audit_logs } = usePage<Props>().props;
    const attendanceChanges = useMemo(
        () => audit_logs.filter((item) => item.entity_type === 'dtr_row').length,
        [audit_logs],
    );
    const payrollChanges = useMemo(
        () => audit_logs.filter((item) => item.entity_type === 'payroll_record').length,
        [audit_logs],
    );
    const leaveChanges = useMemo(
        () => audit_logs.filter((item) => item.entity_type === 'leave_request').length,
        [audit_logs],
    );

    return (
        <>
            <Head title="Audit Trail" />
            <PageHeader
                title="Audit Trail"
                subtitle="Recent administrative changes across attendance, payroll, leave, holidays, and employee lifecycle."
            />

            <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Recent Records" value={audit_logs.length} />
                <MetricCard label="Attendance Changes" value={attendanceChanges} />
                <MetricCard label="Payroll Changes" value={payrollChanges} />
                <MetricCard label="Leave Changes" value={leaveChanges} />
            </div>

            <TableCard title="Change History">
                <Table
                    rowKey="id"
                    dataSource={audit_logs}
                    pagination={{ pageSize: 20 }}
                    columns={[
                        { title: 'When', dataIndex: 'created_at', width: 170 },
                        {
                            title: 'Actor',
                            render: (_, row) => (
                                <Space direction="vertical" size={0}>
                                    <span>{row.actor_name}</span>
                                    <span className="text-xs text-gray-500">{row.actor_email || '-'}</span>
                                </Space>
                            ),
                            width: 190,
                        },
                        {
                            title: 'Action',
                            dataIndex: 'action',
                            render: (v) => <Tag color="blue">{String(v).toUpperCase()}</Tag>,
                            width: 180,
                        },
                        {
                            title: 'Target',
                            render: (_, row) => (
                                <span>{row.entity_type}#{row.entity_id ?? '-'}</span>
                            ),
                            width: 180,
                        },
                        { title: 'Reason', dataIndex: 'reason', render: (v) => v || '-' },
                        { title: 'IP', dataIndex: 'ip_address', width: 140, render: (v) => v || '-' },
                    ]}
                />
            </TableCard>
        </>
    );
}
