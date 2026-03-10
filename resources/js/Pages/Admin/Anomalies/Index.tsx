import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { PageProps as AppPageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Space, Table, Tag } from 'antd';
import { useMemo } from 'react';

type Anomaly = {
    row_id: number;
    employee_id: number;
    employee_name: string;
    employee_email: string;
    date: string;
    day: string;
    type: 'late' | 'undertime' | 'overtime' | 'missing_logs';
    details: string;
    time_in: string | null;
    time_out: string | null;
    status: string;
};

type Props = AppPageProps<{
    anomalies: Anomaly[];
}>;

export default function AdminAnomalyIndex() {
    const { anomalies } = usePage<Props>().props;
    const lateCount = useMemo(() => anomalies.filter((item) => item.type === 'late').length, [anomalies]);
    const undertimeCount = useMemo(() => anomalies.filter((item) => item.type === 'undertime').length, [anomalies]);
    const overtimeCount = useMemo(() => anomalies.filter((item) => item.type === 'overtime').length, [anomalies]);
    const missingLogCount = useMemo(() => anomalies.filter((item) => item.type === 'missing_logs').length, [anomalies]);

    const typeColor = (type: string) => {
        if (type === 'late') return 'orange';
        if (type === 'undertime') return 'volcano';
        if (type === 'overtime') return 'cyan';
        if (type === 'missing_logs') return 'red';
        return 'default';
    };

    return (
        <>
            <Head title="Attendance Anomalies" />
            <PageHeader
                title="Attendance Anomalies"
                subtitle="Monitor late, undertime, overtime, and missing-log records for review."
                actions={(
                    <Link href={route('admin.attendance.logs')}>
                        <Button>Attendance Logs</Button>
                    </Link>
                )}
            />

            <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Total Anomalies" value={anomalies.length} />
                <MetricCard label="Late" value={lateCount} />
                <MetricCard label="Undertime" value={undertimeCount} />
                <MetricCard label="Overtime / Missing" value={`${overtimeCount} / ${missingLogCount}`} />
            </div>

            <TableCard title="Anomaly Queue">
                <Table
                    rowKey={(row) => `${row.row_id}-${row.type}`}
                    dataSource={anomalies}
                    pagination={{ pageSize: 20 }}
                    columns={[
                        {
                            title: 'Employee',
                            render: (_, row) => (
                                <Space direction="vertical" size={0}>
                                    <span>{row.employee_name}</span>
                                    <span className="text-xs text-gray-500">{row.employee_email}</span>
                                </Space>
                            ),
                        },
                        { title: 'Date', dataIndex: 'date' },
                        { title: 'Day', dataIndex: 'day' },
                        { title: 'Type', dataIndex: 'type', render: (v) => <Tag color={typeColor(String(v))}>{String(v).toUpperCase()}</Tag> },
                        { title: 'Details', dataIndex: 'details' },
                        {
                            title: 'Action',
                            render: (_, row) => (
                                <Link href={route('admin.attendance.show', row.employee_id)}>
                                    <Button size="small" type="primary">Review</Button>
                                </Link>
                            ),
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
