import { Head, Link, router } from '@inertiajs/react';
import { attendanceStatusColor, rowStateColor, rowStateLabel } from '@/lib/attendanceStatus';
import PageHeader from '@/Components/ui/PageHeader';
import MetricCard from '@/Components/ui/MetricCard';
import TableCard from '@/Components/ui/TableCard';
import { useEffect, useMemo, useState } from 'react';
import {
    Button,
    Card,
    Col,
    Empty,
    Form,
    Modal,
    Progress,
    Row,
    Select,
    Space,
    Tag,
    Table,
    TimePicker,
    message,
    notification,
} from 'antd';
import { ArrowLeftOutlined, EditOutlined, PrinterOutlined } from '@ant-design/icons';
import axios from 'axios';
import dayjs from 'dayjs';

type RowStatus = 'draft' | 'in_progress' | 'finished' | 'leave' | 'missed';

interface DtrRow {
    id: number;
    date: string;
    day: string;
    time_in: string | null;
    time_out: string | null;
    total_hours: number;
    total_minutes: number;
    break_minutes: number;
    late_minutes: number;
    on_break: boolean;
    break_target_minutes: number | null;
    break_started_at: string | null;
    status: RowStatus;
    leave_request_status?: 'pending' | 'approved' | 'rejected' | 'cancelled' | null;
    leave_request_type?: 'leave' | 'intern_absence' | null;
    leave_request_is_paid?: boolean | null;
    is_locked_by_payroll?: boolean;
    attendance_statuses: string[];
    warnings: string[];
    can_edit: boolean;
}

interface DtrMonth {
    id: number;
    month: number;
    year: number;
    monthName: string;
    is_fulfilled: boolean;
}

interface Props {
    month: DtrMonth;
    rows: DtrRow[];
    total_hours: number;
    required_hours: number;
    remaining_hours: number;
    today_date: string;
    shift_start: string;
    grace_minutes: number;
    user: any;
}

const BREAK_OPTIONS = [5, 10, 15, 30, 45, 60].map((value) => ({ label: `${value} mins`, value }));

export default function DtrShow({
    month,
    rows: initialRows,
    total_hours,
    required_hours,
    remaining_hours,
    today_date,
    shift_start,
    grace_minutes,
    user,
}: Props) {
    const isIntern = user?.employee_type === 'intern';
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingRow, setEditingRow] = useState<DtrRow | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [breakChoice, setBreakChoice] = useState<number>(user?.default_break_minutes ?? 60);
    const [breakCountdownSec, setBreakCountdownSec] = useState<number | null>(null);
    const [selectedPrintDays, setSelectedPrintDays] = useState<number[]>([]);
    const [printRange, setPrintRange] = useState<'first_15' | 'last_15' | 'whole_month'>('whole_month');
    const [form] = Form.useForm();

    const todayRow = useMemo(
        () => initialRows.find((row) => row.date === today_date) ?? null,
        [initialRows, today_date]
    );

    const daysWithRecords = useMemo(
        () => Array.from(new Set(initialRows.map((row) => dayjs(row.date).date()))).sort((a, b) => a - b),
        [initialRows]
    );

    const printableRows = initialRows.filter((row) => selectedPrintDays.includes(dayjs(row.date).date()));
    const printableTotalHours = printableRows.reduce((sum, row) => sum + row.total_hours, 0);
    const printableRemainingHours = Math.max(0, required_hours - printableTotalHours);

    const progressPercentage = required_hours > 0 ? (total_hours / required_hours) * 100 : 0;

    useEffect(() => {
        setSelectedPrintDays(daysWithRecords);
        setPrintRange('whole_month');
    }, [daysWithRecords]);

    useEffect(() => {
        if (!todayRow?.on_break || !todayRow.break_started_at || !todayRow.break_target_minutes) {
            setBreakCountdownSec(null);
            return;
        }

        const startedAt = dayjs(todayRow.break_started_at);
        const targetEnd = startedAt.add(todayRow.break_target_minutes, 'minute');

        const update = () => {
            const sec = targetEnd.diff(dayjs(), 'second');
            setBreakCountdownSec(sec);
            if (sec <= 0) {
                notification.warning({
                    message: 'Break timer ended',
                    description: 'Please press "Finish Break" to stop the timer.',
                    placement: 'topRight',
                });
            }
        };

        update();
        const timer = window.setInterval(update, 1000);
        return () => window.clearInterval(timer);
    }, [todayRow?.on_break, todayRow?.break_started_at, todayRow?.break_target_minutes]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('print') === '1') {
            setTimeout(() => window.print(), 300);
        }
    }, []);

    const formatDisplayTime = (time: string | null) => {
        if (!time) return '-';
        const parsed = dayjs(time, ['HH:mm:ss', 'HH:mm', 'h:mm A'], true);
        return parsed.isValid() ? parsed.format('h:mm A') : time;
    };

    const formatCountdown = (totalSeconds: number) => {
        const safeSeconds = Math.max(0, totalSeconds);
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);
        const seconds = safeSeconds % 60;
        return `${hours}h ${minutes}m ${seconds}s`;
    };

    const extractApiError = (error: any, fallback: string) => {
        const responseData = error?.response?.data;
        if (!responseData) return fallback;

        if (typeof responseData.error === 'string' && responseData.error.trim() !== '') {
            return responseData.error;
        }

        if (typeof responseData.message === 'string' && responseData.message.trim() !== '') {
            return responseData.message;
        }

        if (responseData.errors && typeof responseData.errors === 'object') {
            const firstField = Object.keys(responseData.errors)[0];
            const firstMessage = firstField ? responseData.errors[firstField]?.[0] : null;
            if (typeof firstMessage === 'string' && firstMessage.trim() !== '') {
                return firstMessage;
            }
        }

        return fallback;
    };

    const refreshRows = () => router.reload();

    const handleClockIn = async () => {
        if (!todayRow) return;
        try {
            await axios.patch(route('dtr.rows.clock_in', { row: todayRow.id }));
            message.success('Clocked in.');
            refreshRows();
        } catch (error: any) {
            message.error(error.response?.data?.error || 'Clock in failed.');
        }
    };

    const handleClockOut = async () => {
        if (!todayRow) return;
        try {
            await axios.patch(route('dtr.rows.clock_out', { row: todayRow.id }));
            message.success('Clocked out.');
            refreshRows();
        } catch (error: any) {
            message.error(error.response?.data?.error || 'Clock out failed.');
        }
    };

    const handleStartBreak = async () => {
        if (!todayRow) return;
        try {
            await axios.patch(route('dtr.rows.break_start', { row: todayRow.id }), { minutes: breakChoice });
            message.success('Break started.');
            refreshRows();
        } catch (error: any) {
            message.error(error.response?.data?.error || 'Failed to start break.');
        }
    };

    const handleFinishBreak = async () => {
        if (!todayRow) return;
        try {
            await axios.patch(route('dtr.rows.break_finish', { row: todayRow.id }));
            message.success('Break finished.');
            refreshRows();
        } catch (error: any) {
            message.error(error.response?.data?.error || 'Failed to finish break.');
        }
    };

    const handleLeave = async (row: DtrRow) => {
        try {
            const reasonPrompt = isIntern ? 'Absence reason (optional):' : 'Leave reason (optional):';
            const reasonInput = window.prompt(reasonPrompt);
            if (reasonInput === null) {
                return;
            }

            const reason = reasonInput.trim();
            let isPaid = false;
            if (!isIntern) {
                const leaveTypeInput = window.prompt(
                    `Leave type for ${dayjs(row.date).format('YYYY-MM-DD')} (paid or unpaid):`,
                    'paid'
                );
                if (leaveTypeInput === null) {
                    return;
                }
                const normalized = leaveTypeInput.trim().toLowerCase();
                if (!['paid', 'unpaid'].includes(normalized)) {
                    message.error('Invalid leave type. Use "paid" or "unpaid".');
                    return;
                }
                isPaid = normalized === 'paid';
            }
            await axios.patch(route('dtr.rows.leave', { row: row.id }), {
                reason,
                is_paid: isPaid,
            });
            message.success(isIntern ? 'Absence request submitted for admin approval.' : 'Leave request submitted for admin approval.');
            refreshRows();
        } catch (error: any) {
            message.error(error.response?.data?.error || (isIntern ? 'Failed to submit absence request.' : 'Failed to mark leave.'));
        }
    };

    const handleEditRow = (row: DtrRow) => {
        if (row.is_locked_by_payroll) {
            message.warning('This row is locked by a finalized payroll period. Please ask admin for correction.');
            return;
        }

        if (row.status !== 'missed') {
            message.warning('Only missed rows can be edited.');
            return;
        }

        if (!row.can_edit || row.date === today_date) {
            message.warning('Only missed rows outside finalized payroll periods can be edited.');
            return;
        }

        setEditingRow(row);
        form.setFieldsValue({
            time_in: row.time_in ? dayjs(row.time_in, ['HH:mm:ss', 'HH:mm', 'h:mm A']) : null,
            time_out: row.time_out ? dayjs(row.time_out, ['HH:mm:ss', 'HH:mm', 'h:mm A']) : null,
            break_minutes: row.break_minutes ?? 0,
        });
        setIsModalVisible(true);
    };

    const handleSubmitEdit = async (values: any) => {
        if (!editingRow) return;
        setSubmitting(true);

        try {
            await axios.patch(route('dtr.rows.update', { row: editingRow.id }), {
                time_in: values.time_in ? values.time_in.format('HH:mm') : null,
                time_out: values.time_out ? values.time_out.format('HH:mm') : null,
                break_minutes: values.break_minutes ?? 0,
            });

            message.success('Record updated.');
            setIsModalVisible(false);
            setEditingRow(null);
            form.resetFields();
            refreshRows();
        } catch (error: any) {
            message.open({
                type: 'error',
                key: 'dtr-update-error',
                content: extractApiError(error, 'Update failed.'),
            });
        } finally {
            setSubmitting(false);
        }
    };

    useEffect(() => {
        if (printRange === 'first_15') {
            setSelectedPrintDays(daysWithRecords.filter((day) => day <= 15));
            return;
        }

        if (printRange === 'last_15') {
            const daysInMonth = dayjs(`${month.year}-${month.month}-01`).daysInMonth();
            const start = daysInMonth - 14;
            setSelectedPrintDays(daysWithRecords.filter((day) => day >= start));
            return;
        }

        setSelectedPrintDays(daysWithRecords);
    }, [printRange, daysWithRecords, month.year, month.month]);

    const rowClassName = (row: DtrRow) => {
        if (row.status === 'finished') return 'dtr-row-finished';
        if (row.status === 'missed') return 'dtr-row-missed';
        if (row.status === 'leave') return 'dtr-row-leave';
        return '';
    };

    const columns = [
        {
            title: 'No.',
            key: 'index',
            width: 60,
            render: (_: any, __: any, index: number) => index + 1,
        },
        {
            title: 'Day',
            dataIndex: 'day',
            key: 'day',
            width: 120,
        },
        {
            title: 'Date',
            dataIndex: 'date',
            key: 'date',
            width: 120,
            render: (date: string) => dayjs(date).format('YYYY-MM-DD'),
        },
        {
            title: 'IN',
            dataIndex: 'time_in',
            key: 'time_in',
            width: 100,
            render: (time: string | null) => formatDisplayTime(time),
        },
        {
            title: 'OUT',
            dataIndex: 'time_out',
            key: 'time_out',
            width: 100,
            render: (time: string | null) => formatDisplayTime(time),
        },
        {
            title: 'Late',
            dataIndex: 'late_minutes',
            key: 'late_minutes',
            width: 90,
            render: (minutes: number) => (minutes > 0 ? `${minutes}m` : '-'),
        },
        {
            title: 'Break',
            dataIndex: 'break_minutes',
            key: 'break_minutes',
            width: 90,
            render: (minutes: number) => `${minutes || 0}m`,
        },
        {
            title: 'Total',
            dataIndex: 'total_hours',
            key: 'total_hours',
            width: 90,
            render: (hours: number) => hours.toFixed(2),
        },
        {
            title: 'Row State',
            dataIndex: 'status',
            key: 'status',
            width: 120,
            render: (status: RowStatus, row: DtrRow) => (
                <Space size={4} wrap>
                    <Tag color={rowStateColor(status)}>{rowStateLabel(status)}</Tag>
                    {row.is_locked_by_payroll && <Tag color="red">LOCKED</Tag>}
                </Space>
            ),
        },
        {
            title: 'Attendance Statuses',
            key: 'attendance_statuses',
            width: 260,
            render: (_: any, row: DtrRow) => (
                <Space size={4} wrap>
                    {row.attendance_statuses.length === 0 ? (
                        <Tag>-</Tag>
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
            title: 'Warnings',
            key: 'warnings',
            width: 220,
            render: (_: any, row: DtrRow) => (
                <Space size={4} wrap>
                    {row.warnings.length === 0 ? (
                        <Tag color="green">None</Tag>
                    ) : (
                        row.warnings.map((warning) => (
                            <Tag key={`${row.id}-warning-${warning}`} color="red">
                                {warning}
                            </Tag>
                        ))
                    )}
                </Space>
            ),
        },
        {
            title: 'Actions',
            key: 'actions',
            width: 170,
            render: (_: any, row: DtrRow) => {
                const canMarkLeave = row.can_edit
                    && !row.is_locked_by_payroll
                    && !row.time_in
                    && !row.time_out
                    && row.date < today_date
                    && !['pending', 'approved', 'rejected'].includes(String(row.leave_request_status || ''));
                const requestLabel = isIntern ? 'Absence Request' : 'Leave';
                return (
                    <Space wrap size={[6, 6]}>
                        {row.is_locked_by_payroll && <Tag color="red">LOCKED</Tag>}
                        <Button
                            type="text"
                            size="small"
                            icon={<EditOutlined />}
                            onClick={() => handleEditRow(row)}
                        />
                        <Button
                            size="small"
                            onClick={() => handleLeave(row)}
                            disabled={!canMarkLeave}
                        >
                            {row.leave_request_status === 'pending'
                                ? 'Request Pending'
                                : row.leave_request_status === 'rejected'
                                    ? 'Request Rejected'
                                    : requestLabel}
                        </Button>
                    </Space>
                );
            },
        },
    ];

    return (
        <>
            <Head title={`DTR - ${month.monthName}`} />
            <style>{`
                .dtr-row-finished td { background: #f6ffed !important; }
                .dtr-row-missed td { background: #fff1f0 !important; }
                .dtr-row-leave td { background: #fffbe6 !important; }
                @media print {
                    @page { size: A4 portrait; margin: 8mm; }
                    .screen-only, .ant-layout-header { display: none !important; }
                    .print-only { display: block !important; }
                }
            `}</style>

            <div className="screen-only">
                <PageHeader
                    title={`${month.monthName} DTR`}
                    actions={(
                        <Space wrap>
                            <Link href={route('dtr.index', { tab: '2' })}>
                                <Button icon={<ArrowLeftOutlined />}>Back</Button>
                            </Link>
                            <Select
                                value={printRange}
                                onChange={(value) => setPrintRange(value)}
                                style={{ width: 180 }}
                                options={[
                                    { label: 'Print First 15', value: 'first_15' },
                                    { label: 'Print Last 15', value: 'last_15' },
                                    { label: 'Print Whole Month', value: 'whole_month' },
                                ]}
                            />
                            <Button icon={<PrinterOutlined />} onClick={() => window.print()}>
                                Print
                            </Button>
                        </Space>
                    )}
                />

                <div className={`mb-6 grid grid-cols-1 gap-3 ${isIntern ? 'md:grid-cols-4' : 'md:grid-cols-3'}`}>
                    <MetricCard label="Total Hours Logged" value={`${total_hours.toFixed(2)} hrs`} />
                    {isIntern && <MetricCard label="Required Hours" value={`${required_hours} hrs`} />}
                    {isIntern && <MetricCard label="Remaining Hours" value={`${remaining_hours.toFixed(2)} hrs`} />}
                    {!isIntern && (
                        <MetricCard
                            label="Paid Leave Balance"
                            value={`${Number(user?.current_paid_leave_balance ?? 0).toFixed(2)} day(s)`}
                            helper={<span className="text-xs text-slate-500">Initial: {Number(user?.initial_paid_leave_days ?? 0).toFixed(2)} day(s)</span>}
                        />
                    )}
                    <MetricCard
                        label="Progress"
                        value={`${Math.min(100, Number(progressPercentage.toFixed(1)))}%`}
                        helper={(
                            <Progress
                                percent={Math.min(100, Number(progressPercentage.toFixed(10)))}
                                showInfo={false}
                                size="small"
                            />
                        )}
                    />
                </div>

                {todayRow && (
                    <Card size="small" className="mb-4" title={`Today (${dayjs(todayRow.date).format('YYYY-MM-DD')})`}>
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <Space wrap size={[12, 12]}>
                                <Button
                                    type="primary"
                                    onClick={handleClockIn}
                                    disabled={!!todayRow.time_in}
                                >
                                    Clock In
                                </Button>
                                <Button
                                    type="primary"
                                    onClick={handleClockOut}
                                    disabled={!todayRow.time_in || !!todayRow.time_out}
                                >
                                    Clock Out
                                </Button>
                            </Space>
                            <Space wrap size={[12, 12]} className="lg:justify-end">
                                <span className="text-sm text-slate-600">Break Duration</span>
                                <Select
                                    value={breakChoice}
                                    onChange={setBreakChoice}
                                    style={{ width: 140 }}
                                    options={BREAK_OPTIONS}
                                    disabled={todayRow.on_break}
                                />
                                <Button
                                    onClick={handleStartBreak}
                                    disabled={!todayRow.time_in || !!todayRow.time_out || todayRow.on_break}
                                >
                                    Start Break
                                </Button>
                                <Button
                                    onClick={handleFinishBreak}
                                    disabled={!todayRow.on_break}
                                >
                                    Finish Break
                                </Button>
                            </Space>
                        </div>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Tag color={rowStateColor(todayRow.status)}>Row State: {rowStateLabel(todayRow.status)}</Tag>
                            {todayRow.leave_request_status === 'pending' && (
                                <Tag color="gold">
                                    {todayRow.leave_request_type === 'intern_absence' ? 'Absence Request' : 'Leave Request'}: PENDING
                                </Tag>
                            )}
                            {todayRow.leave_request_status === 'approved' && (
                                <Tag color="green">
                                    {todayRow.leave_request_type === 'intern_absence' ? 'Absence Request' : 'Leave Request'}: APPROVED
                                </Tag>
                            )}
                            {todayRow.leave_request_status === 'rejected' && (
                                <Tag color="red">
                                    {todayRow.leave_request_type === 'intern_absence' ? 'Absence Request' : 'Leave Request'}: REJECTED
                                </Tag>
                            )}
                            {todayRow.leave_request_type === 'leave' && todayRow.leave_request_status && (
                                <Tag color={todayRow.leave_request_is_paid ? 'green' : 'default'}>
                                    {todayRow.leave_request_is_paid ? 'PAID LEAVE' : 'UNPAID LEAVE'}
                                </Tag>
                            )}
                            {todayRow.attendance_statuses.length === 0 ? (
                                <Tag>-</Tag>
                            ) : (
                                todayRow.attendance_statuses.map((status) => (
                                    <Tag key={`today-show-${todayRow.id}-${status}`} color={attendanceStatusColor(status)}>
                                        {status}
                                    </Tag>
                                ))
                            )}
                            {todayRow.warnings.map((warning) => (
                                <Tag key={`today-warning-${todayRow.id}-${warning}`} color="red">
                                    {warning}
                                </Tag>
                            ))}
                        </div>
                        {todayRow.on_break && (
                            <div className="mt-3 text-sm text-amber-600">
                                Break running: {breakCountdownSec !== null ? `${formatCountdown(breakCountdownSec)} remaining` : '...'}
                            </div>
                        )}
                    </Card>
                )}

                <TableCard>
                    {initialRows.length === 0 ? (
                        <Empty description="No attendance records for this month" />
                    ) : (
                        <Table
                            dataSource={initialRows}
                            columns={columns}
                            rowKey="id"
                            rowClassName={rowClassName}
                            pagination={false}
                            size="small"
                        />
                    )}
                </TableCard>

                <Modal
                    title="Edit Attendance Record"
                    open={isModalVisible}
                    centered
                    footer={null}
                    onCancel={() => {
                        setIsModalVisible(false);
                        setEditingRow(null);
                        form.resetFields();
                    }}
                >
                    <Form form={form} layout="vertical" onFinish={handleSubmitEdit}>
                        <Row gutter={12}>
                            <Col xs={24} md={12}>
                                <Form.Item name="time_in" label="Time In">
                                    <TimePicker style={{ width: '100%' }} format="h:mm A" use12Hours />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="time_out" label="Time Out">
                                    <TimePicker style={{ width: '100%' }} format="h:mm A" use12Hours />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="break_minutes" label="Break Minutes">
                            <Select allowClear options={BREAK_OPTIONS} />
                        </Form.Item>
                        <Form.Item>
                            <Button htmlType="submit" type="primary" block loading={submitting}>
                                Update
                            </Button>
                        </Form.Item>
                    </Form>
                </Modal>
            </div>

            <div className="print-only" style={{ display: 'none' }}>
                <div className="print-root">
                    <h2 style={{ textAlign: 'center' }}>DAILY TIME RECORD - {month.monthName.toUpperCase()}</h2>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px 16px', marginBottom: 10 }}>
                        <div><strong>Name:</strong> {user.student_name || '-'}</div>
                        <div><strong>Employee Type:</strong> {user.employee_type === 'intern' ? 'Intern' : 'Regular Employee'}</div>
                        <div><strong>Department:</strong> {user.department || '-'}</div>
                        <div><strong>Company:</strong> {user.company || '-'}</div>
                        <div><strong>Supervisor Name:</strong> {user.supervisor_name || '-'}</div>
                        <div><strong>Supervisor Position:</strong> {user.supervisor_position || '-'}</div>
                    </div>
                    <table
                        className="print-table"
                        style={{ width: '100%', borderCollapse: 'collapse', tableLayout: 'fixed' }}
                    >
                        <colgroup>
                            <col style={{ width: '6%' }} />
                            <col style={{ width: '14%' }} />
                            <col style={{ width: '12%' }} />
                            <col style={{ width: '10%' }} />
                            <col style={{ width: '12%' }} />
                            <col style={{ width: '12%' }} />
                            <col style={{ width: '9%' }} />
                            <col style={{ width: '10%' }} />
                            <col style={{ width: '11%' }} />
                            <col style={{ width: '15%' }} />
                        </colgroup>
                        <thead>
                            <tr>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>No.</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>Day</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>Date</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>IN</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>OUT</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>Break</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>Total</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>Row State</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>Attendance Statuses</th>
                                <th style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>Warnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            {printableRows.map((row, index) => (
                                <tr key={row.id}>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{index + 1}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{row.day}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{dayjs(row.date).format('MM/DD')}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{formatDisplayTime(row.time_in)}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{formatDisplayTime(row.time_out)}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{row.break_minutes}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{row.total_hours.toFixed(2)}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{rowStateLabel(row.status)}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{row.attendance_statuses.join(', ') || '-'}</td>
                                    <td style={{ border: '1px solid #000', padding: '4px 6px', textAlign: 'center' }}>{row.warnings.join(', ') || '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="mt-3">
                        {isIntern ? (
                            <>
                                <strong>Total:</strong> {printableTotalHours.toFixed(2)}h | <strong>Remaining:</strong> {printableRemainingHours.toFixed(2)}h
                            </>
                        ) : (
                            <>
                                <strong>Total:</strong> {printableTotalHours.toFixed(2)}h
                            </>
                        )}
                    </div>
                    <div style={{ marginTop: 28, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
                        <div>
                            <div style={{ borderBottom: '1px solid #000', height: 26 }} />
                            <div style={{ textAlign: 'center', marginTop: 4 }}>Employee Signature</div>
                        </div>
                        <div>
                            <div style={{ borderBottom: '1px solid #000', height: 26 }} />
                            <div style={{ textAlign: 'center', marginTop: 4 }}>
                                Validated by Supervisor
                            </div>
                            <div style={{ textAlign: 'center', fontSize: 11 }}>
                                {user.supervisor_name || 'Supervisor Name'}{user.supervisor_position ? ` (${user.supervisor_position})` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
