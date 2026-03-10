import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { PageProps as AppPageProps } from '@/types';
import { attendanceStatusColor, rowStateColor, rowStateLabel } from '@/lib/attendanceStatus';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Alert, Button, Input, Modal, Select, Space, Table, Tag, TimePicker } from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';

type Employee = {
    id: number;
    name: string;
    email: string;
    employee_type: 'intern' | 'regular' | null;
    department: string | null;
    company: string | null;
    salary_type: 'monthly' | 'daily' | 'hourly' | null;
    salary_amount: number | null;
    work_time_in: string | null;
    work_time_out: string | null;
};

type Month = {
    id: number;
    month: number;
    year: number;
    month_name: string;
    total_hours: number;
    finished_rows: number;
};

type Row = {
    id: number;
    date: string;
    day: string;
    time_in: string | null;
    time_out: string | null;
    break_minutes: number;
    late_minutes: number;
    total_minutes: number;
    total_hours: number;
    status: 'draft' | 'finished' | 'leave' | 'missed' | 'in_progress';
    is_locked_by_payroll: boolean;
    attendance_statuses: string[];
    flags: string[];
};

type Props = AppPageProps<{
    employee: Employee;
    months: Month[];
    selected_month: { id: number; month_name: string } | null;
    rows: Row[];
    flash?: {
        success?: string;
    };
}>;

export default function AdminAttendanceShow() {
    const { employee, months, selected_month, rows, flash } = usePage<Props>().props;
    const [editingRow, setEditingRow] = useState<Row | null>(null);
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const { data, setData, patch, processing, errors, reset } = useForm({
        status: 'finished' as 'draft' | 'finished' | 'leave' | 'missed',
        time_in: '',
        time_out: '',
        break_minutes: 0,
        correction_reason: '',
    });

    const totalHours = useMemo(() => rows.reduce((sum, row) => sum + row.total_hours, 0), [rows]);
    const flaggedRows = useMemo(() => rows.filter((row) => row.flags.length > 0).length, [rows]);

    const statusFilterOptions = useMemo(() => {
        const statuses = Array.from(new Set(rows.flatMap((row) => row.attendance_statuses))).sort((a, b) =>
            a.localeCompare(b),
        );
        return [
            { label: 'All attendance statuses', value: 'all' as const },
            ...statuses.map((status) => ({
                label: status,
                value: status,
            })),
        ];
    }, [rows]);

    const filteredRows = useMemo(
        () =>
            statusFilter === 'all' ? rows : rows.filter((row) => row.attendance_statuses.includes(statusFilter)),
        [rows, statusFilter],
    );

    const openEdit = (row: Row) => {
        setEditingRow(row);
        setData({
            status: (row.status === 'in_progress' ? 'finished' : row.status) as 'draft' | 'finished' | 'leave' | 'missed',
            time_in: row.time_in ? row.time_in.slice(0, 5) : '',
            time_out: row.time_out ? row.time_out.slice(0, 5) : '',
            break_minutes: row.break_minutes || 0,
            correction_reason: '',
        });
    };

    return (
        <>
            <Head title={`Attendance Review - ${employee.name}`} />
            <style>{`
                @media print {
                    @page { size: A4 portrait; margin: 8mm; }
                    .screen-only, .liquid-header { display: none !important; }
                    .print-only { display: block !important; }
                }
            `}</style>
            <div className="screen-only">
                <PageHeader
                    title="Attendance Review"
                    subtitle={`${employee.name} (${employee.email})`}
                    actions={(
                        <Space>
                            <Link href={route('admin.employees.index')}>
                                <Button>Back to Employees</Button>
                            </Link>
                            <Button onClick={() => window.print()}>Print Attendance</Button>
                        </Space>
                    )}
                />

                {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}

                <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard label="Rows" value={`${filteredRows.length} / ${rows.length}`} />
                    <MetricCard label="Total Hours" value={totalHours.toFixed(2)} />
                    <MetricCard label="Flagged Rows" value={flaggedRows} valueClassName={flaggedRows > 0 ? 'text-red-600' : ''} />
                    <MetricCard label="Selected Month" value={selected_month?.month_name || 'No month'} />
                </div>

                <TableCard
                    title="Attendance Rows"
                    actions={(
                        <div className="flex flex-wrap items-center gap-3">
                            <Select
                                placeholder="Select month"
                                style={{ width: 260 }}
                                value={selected_month?.id}
                                options={months.map((month) => ({
                                    label: `${month.month_name} | ${month.total_hours.toFixed(2)}h`,
                                    value: month.id,
                                }))}
                                onChange={(monthId) => {
                                    router.get(route('admin.attendance.show', employee.id), { month_id: monthId }, { preserveState: true });
                                }}
                            />
                            <Select
                                placeholder="Filter attendance status"
                                style={{ width: 230 }}
                                value={statusFilter}
                                onChange={(value) => setStatusFilter(value)}
                                options={statusFilterOptions}
                            />
                        </div>
                    )}
                >
                    <Table
                        rowKey="id"
                        dataSource={filteredRows}
                        rowClassName={(row) => (row.flags.length > 0 ? 'bg-red-50' : '')}
                        columns={[
                            { title: 'Date', dataIndex: 'date' },
                            { title: 'Day', dataIndex: 'day' },
                            { title: 'Time In', dataIndex: 'time_in', render: (v) => v || '-' },
                            { title: 'Time Out', dataIndex: 'time_out', render: (v) => v || '-' },
                            { title: 'Late', dataIndex: 'late_minutes', render: (v) => `${v}m` },
                            { title: 'Break', dataIndex: 'break_minutes', render: (v) => `${v}m` },
                            { title: 'Hours', dataIndex: 'total_hours', render: (v) => Number(v).toFixed(2) },
                            {
                                title: 'Row State',
                                dataIndex: 'status',
                                render: (v, row) => (
                                    <Space wrap>
                                        <Tag color={rowStateColor(String(v))}>{rowStateLabel(String(v))}</Tag>
                                        {row.is_locked_by_payroll && <Tag color="red">Payroll Locked</Tag>}
                                    </Space>
                                ),
                            },
                            {
                                title: 'Attendance Statuses',
                                render: (_, row) =>
                                    row.attendance_statuses.length === 0 ? (
                                        <Tag>None</Tag>
                                    ) : (
                                        <Space wrap>
                                            {row.attendance_statuses.map((status) => (
                                                <Tag key={`${row.id}-${status}`} color={attendanceStatusColor(status)}>
                                                    {status}
                                                </Tag>
                                            ))}
                                        </Space>
                                    ),
                            },
                            {
                                title: 'Flags',
                                render: (_, row) =>
                                    row.flags.length === 0 ? (
                                        <Tag color="green">None</Tag>
                                    ) : (
                                        <Space wrap>
                                            {row.flags.map((flag) => (
                                                <Tag key={`${row.id}-${flag}`} color="red">{flag}</Tag>
                                            ))}
                                        </Space>
                                    ),
                            },
                            {
                                title: 'Actions',
                                render: (_, row) => (
                                    <Button size="small" onClick={() => openEdit(row)}>
                                        {row.is_locked_by_payroll ? 'Correct' : 'Edit'}
                                    </Button>
                                ),
                            },
                        ]}
                    />
                </TableCard>
            </div>

            <div className="print-only" style={{ display: 'none' }}>
                <h2 style={{ textAlign: 'center' }}>
                    ATTENDANCE REVIEW - {selected_month?.month_name?.toUpperCase() || 'NO MONTH'}
                </h2>
                <p><strong>Employee:</strong> {employee.name} ({employee.email})</p>
                <p><strong>Department:</strong> {employee.department || '-'} | <strong>Company:</strong> {employee.company || '-'}</p>
                <p><strong>Total Hours:</strong> {totalHours.toFixed(2)} | <strong>Flagged Rows:</strong> {flaggedRows}</p>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                    <thead>
                        <tr>
                            {['Date', 'Day', 'In', 'Out', 'Late', 'Break', 'Hours', 'Row State', 'Attendance Statuses', 'Flags'].map((header) => (
                                <th key={header} style={{ border: '1px solid #000', padding: 4 }}>{header}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.date}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.day}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.time_in || '-'}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.time_out || '-'}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.late_minutes}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.break_minutes}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.total_hours.toFixed(2)}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{rowStateLabel(row.status)}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.attendance_statuses.join(', ') || 'None'}</td>
                                <td style={{ border: '1px solid #000', padding: 4 }}>{row.flags.join(', ') || 'None'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Modal
                title={`Edit Attendance - ${editingRow?.date || ''}`}
                open={!!editingRow}
                onCancel={() => {
                    setEditingRow(null);
                    reset();
                }}
                onOk={() => {
                    if (!editingRow) return;
                    const routeName = editingRow.is_locked_by_payroll
                        ? route('admin.attendance.rows.correct', editingRow.id)
                        : route('admin.attendance.rows.update', editingRow.id);

                    patch(routeName, {
                        onSuccess: () => {
                            setEditingRow(null);
                            reset();
                        },
                    });
                }}
                okButtonProps={{ loading: processing }}
            >
                <div className="space-y-3">
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Status</label>
                        <Select
                            className="w-full"
                            value={data.status}
                            onChange={(value) => setData('status', value)}
                            options={[
                                { label: 'Finished', value: 'finished' },
                                { label: 'Draft', value: 'draft' },
                                { label: 'Leave', value: 'leave' },
                                { label: 'Missed', value: 'missed' },
                            ]}
                        />
                        {errors.status && <p className="text-sm text-red-600 mt-1">{errors.status}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1 block text-sm text-gray-700">Time In</label>
                            <TimePicker
                                className="w-full"
                                format="HH:mm"
                                value={data.time_in ? dayjs(data.time_in, 'HH:mm') : null}
                                onChange={(value) => setData('time_in', value ? value.format('HH:mm') : '')}
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm text-gray-700">Time Out</label>
                            <TimePicker
                                className="w-full"
                                format="HH:mm"
                                value={data.time_out ? dayjs(data.time_out, 'HH:mm') : null}
                                onChange={(value) => setData('time_out', value ? value.format('HH:mm') : '')}
                            />
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Break Minutes</label>
                        <Select
                            className="w-full"
                            value={data.break_minutes}
                            onChange={(value) => setData('break_minutes', value)}
                            options={[0, 5, 10, 15, 30, 45, 60, 90, 120].map((m) => ({ value: m, label: `${m} minutes` }))}
                        />
                    </div>
                    {editingRow?.is_locked_by_payroll && (
                        <div>
                            <label className="mb-1 block text-sm text-gray-700">Correction Reason (Required)</label>
                            <Input.TextArea
                                rows={3}
                                value={data.correction_reason}
                                onChange={(event) => setData('correction_reason', event.target.value)}
                                placeholder="Explain why this locked/finalized attendance row needs correction."
                            />
                            {errors.correction_reason && <p className="text-sm text-red-600 mt-1">{errors.correction_reason}</p>}
                        </div>
                    )}
                </div>
            </Modal>
        </>
    );
}
