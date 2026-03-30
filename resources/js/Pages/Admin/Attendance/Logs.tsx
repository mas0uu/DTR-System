import { PageProps as AppPageProps } from '@/types';
import { attendanceStatusColor } from '@/lib/attendanceStatus';
import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import UserSearchControl from '@/Components/ui/UserSearchControl';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Select, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';

type AttendanceLog = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_email: string;
    employee_type: 'intern' | 'regular' | null;
    date: string;
    day: string;
    time_in: string | null;
    time_out: string | null;
    late_minutes: number;
    break_minutes: number;
    total_hours: number;
    status: string;
    leave_request_id: number | null;
    leave_request_status: 'pending' | 'approved' | 'rejected' | 'cancelled' | null;
    leave_request_type: 'leave' | 'intern_absence' | null;
    attendance_statuses: string[];
};

type Props = AppPageProps<{
    attendance_logs: AttendanceLog[];
}>;

export default function AdminAttendanceLogs() {
    const { attendance_logs } = usePage<Props>().props;
    const [roleFilter, setRoleFilter] = useState<'all' | 'regular' | 'intern'>('all');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [monthFilter, setMonthFilter] = useState<string>('all');
    const [userSearch, setUserSearch] = useState('');

    const formatTime = (value: string | null) => {
        if (!value) return '-';
        const parsed = dayjs(value, ['HH:mm:ss', 'HH:mm'], true);
        return parsed.isValid() ? parsed.format('h:mm A') : value;
    };

    const statusOptions = useMemo(() => {
        const statuses = Array.from(new Set(attendance_logs.flatMap((row) => row.attendance_statuses))).sort((a, b) =>
            a.localeCompare(b),
        );

        return [
            { label: 'All attendance statuses', value: 'all' as const },
            ...statuses.map((status) => ({
                label: status,
                value: status,
            })),
        ];
    }, [attendance_logs]);

    const monthOptions = useMemo(() => {
        const months = Array.from(new Set(attendance_logs.map((row) => dayjs(row.date).format('YYYY-MM')))).sort((a, b) =>
            b.localeCompare(a),
        );

        return [
            { label: 'All months', value: 'all' as const },
            ...months.map((month) => ({
                label: dayjs(`${month}-01`).format('MMMM YYYY'),
                value: month,
            })),
        ];
    }, [attendance_logs]);

    const filteredLogs = useMemo(() => {
        const query = userSearch.trim().toLowerCase();

        return attendance_logs.filter((row) => {
            const passesRole = roleFilter === 'all' || row.employee_type === roleFilter;
            const passesStatus = statusFilter === 'all' || row.attendance_statuses.includes(statusFilter);
            const passesMonth = monthFilter === 'all' || dayjs(row.date).format('YYYY-MM') === monthFilter;
            const passesSearch = query === ''
                || row.employee_name.toLowerCase().includes(query)
                || row.employee_email.toLowerCase().includes(query);

            return passesRole && passesStatus && passesMonth && passesSearch;
        });
    }, [attendance_logs, monthFilter, roleFilter, statusFilter, userSearch]);
    const internLogs = useMemo(
        () => filteredLogs.filter((row) => row.employee_type === 'intern').length,
        [filteredLogs],
    );
    const regularLogs = useMemo(
        () => filteredLogs.filter((row) => row.employee_type === 'regular').length,
        [filteredLogs],
    );

    return (
        <>
            <Head title="Employee Attendance Logs" />
            <PageHeader
                title="Employee Attendance Logs"
                subtitle="Filter and review recorded daily logs across the organization."
                actions={(
                    <Link href={route('admin.attendance.index')}>
                        <Button>Attendance Center</Button>
                    </Link>
                )}
            />

            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <MetricCard label="Filtered Logs" value={filteredLogs.length} helper={`${attendance_logs.length} total`} />
                <MetricCard label="Regular Logs" value={regularLogs} />
                <MetricCard label="Intern Logs" value={internLogs} />
                <MetricCard label="Selected Month" value={monthFilter === 'all' ? 'All' : dayjs(`${monthFilter}-01`).format('MMM YYYY')} />
            </div>

            <TableCard
                title="Attendance Log Rows"
                actions={(
                    <div className="flex w-full flex-wrap items-center gap-2 md:w-auto">
                    <UserSearchControl value={userSearch} onChange={setUserSearch} />
                    <Select
                        value={roleFilter}
                        style={{ width: 190 }}
                        onChange={(value) => setRoleFilter(value)}
                        options={[
                            { label: 'All roles', value: 'all' },
                            { label: 'Regular', value: 'regular' },
                            { label: 'Intern', value: 'intern' },
                        ]}
                    />
                    <Select
                        value={statusFilter}
                        style={{ width: 230 }}
                        onChange={(value) => setStatusFilter(value)}
                        options={statusOptions}
                    />
                    <Select
                        value={monthFilter}
                        style={{ width: 170 }}
                        onChange={(value) => setMonthFilter(value)}
                        options={monthOptions}
                    />
                    </div>
                )}
            >
                <Table
                    rowKey="id"
                    dataSource={filteredLogs}
                    pagination={{ pageSize: 20, showSizeChanger: true }}
                    size="small"
                    columns={[
                        {
                            title: 'Employee',
                            width: 200,
                            render: (_, row) => (
                                <div className="min-w-0">
                                    <div className="font-medium text-gray-800">{row.employee_name}</div>
                                    <div className="text-xs text-gray-500 break-all">{row.employee_email}</div>
                                </div>
                            ),
                        },
                        {
                            title: 'Type',
                            width: 90,
                            render: (_, row) => (
                                <Tag color={row.employee_type === 'intern' ? 'gold' : 'blue'}>
                                    {(row.employee_type || '-').toUpperCase()}
                                </Tag>
                            ),
                        },
                        { title: 'Date', dataIndex: 'date', width: 100, ellipsis: true },
                        { title: 'Day', dataIndex: 'day', width: 90, ellipsis: true },
                        { title: 'Time In', dataIndex: 'time_in', width: 90, render: (v) => formatTime(v) },
                        { title: 'Time Out', dataIndex: 'time_out', width: 90, render: (v) => formatTime(v) },
                        {
                            title: 'Log Status',
                            width: 230,
                            render: (_, row) => (
                                <Space size={4} wrap>
                                    {row.attendance_statuses.length === 0 ? (
                                        <Tag>None</Tag>
                                    ) : (
                                        row.attendance_statuses.map((status) => (
                                            <Tag key={`${row.id}-${status}`} color={attendanceStatusColor(status)}>
                                                {status}
                                            </Tag>
                                        ))
                                    )}
                                </Space>
                            ),
                        },
                        {
                            title: 'Action',
                            width: 130,
                            render: (_, row) => {
                                const isLeaveRequestRow = !!row.leave_request_id
                                    && !!row.leave_request_type
                                    && ['pending', 'approved', 'rejected', 'cancelled'].includes(String(row.leave_request_status));
                                const href = isLeaveRequestRow
                                    ? route('admin.leaves.index', { leave_request_id: row.leave_request_id })
                                    : route('admin.attendance.show', row.employee_id);

                                return (
                                    <Link href={href}>
                                        <Button type="primary" size="small">
                                            {isLeaveRequestRow ? 'Review Request' : 'Review Attendance'}
                                        </Button>
                                    </Link>
                                );
                            },
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
