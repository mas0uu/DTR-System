import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Button,
    Card,
    Col,
    Empty,
    Form,
    Input,
    Modal,
    Progress,
    Row,
    Select,
    Space,
    Statistic,
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
    remarks: string | null;
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
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingRow, setEditingRow] = useState<DtrRow | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [breakChoice, setBreakChoice] = useState<number>(15);
    const [breakCountdownSec, setBreakCountdownSec] = useState<number | null>(null);
    const [selectedPrintDays, setSelectedPrintDays] = useState<number[]>([]);
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
            await axios.patch(route('dtr.rows.leave', { row: row.id }));
            message.success('Row marked as leave.');
            refreshRows();
        } catch (error: any) {
            message.error(error.response?.data?.error || 'Failed to mark leave.');
        }
    };

    const handleEditRow = (row: DtrRow) => {
        if (!row.can_edit || row.date === today_date) {
            message.warning('Only past rows up to yesterday can be edited.');
            return;
        }

        setEditingRow(row);
        form.setFieldsValue({
            time_in: row.time_in ? dayjs(row.time_in, ['HH:mm:ss', 'HH:mm', 'h:mm A']) : null,
            time_out: row.time_out ? dayjs(row.time_out, ['HH:mm:ss', 'HH:mm', 'h:mm A']) : null,
            break_minutes: row.break_minutes ?? 0,
            remarks: row.remarks,
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
                remarks: values.remarks || null,
            });

            message.success('Record updated.');
            setIsModalVisible(false);
            setEditingRow(null);
            form.resetFields();
            refreshRows();
        } catch (error: any) {
            message.error(error.response?.data?.error || 'Update failed.');
        } finally {
            setSubmitting(false);
        }
    };

    const selectFirst15 = () => {
        setSelectedPrintDays(daysWithRecords.filter((day) => day <= 15));
    };

    const selectLast15 = () => {
        const daysInMonth = dayjs(`${month.year}-${month.month}-01`).daysInMonth();
        const start = daysInMonth - 14;
        setSelectedPrintDays(daysWithRecords.filter((day) => day >= start));
    };

    const selectWholeMonth = () => {
        setSelectedPrintDays(daysWithRecords);
    };

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
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            width: 120,
            render: (status: RowStatus) => status.replace('_', ' ').toUpperCase(),
        },
        {
            title: 'Actions',
            key: 'actions',
            width: 170,
            render: (_: any, row: DtrRow) => {
                const canMarkLeave = row.can_edit && !row.time_in && !row.time_out && row.date < today_date;
                return (
                    <Space>
                        <Button
                            type="text"
                            size="small"
                            icon={<EditOutlined />}
                            onClick={() => handleEditRow(row)}
                            disabled={!row.can_edit || row.date === today_date}
                        />
                        <Button
                            size="small"
                            onClick={() => handleLeave(row)}
                            disabled={!canMarkLeave}
                        >
                            Leave
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
                <div className="mb-4 flex items-center justify-between">
                    <Link href={route('dtr.index', { tab: '2' })}>
                        <Button icon={<ArrowLeftOutlined />}>Back</Button>
                    </Link>
                    <Space wrap>
                        <Button onClick={selectFirst15}>Print First 15</Button>
                        <Button onClick={selectLast15}>Print Last 15</Button>
                        <Button onClick={selectWholeMonth}>Print Whole Month</Button>
                        <Button icon={<PrinterOutlined />} onClick={() => window.print()}>
                            Print
                        </Button>
                    </Space>
                </div>

                <Card title={<h1 className="text-2xl font-bold">{month.monthName} DTR</h1>} className="mb-6">
                    <Row gutter={16} className="mb-6">
                        <Col xs={24} sm={8}>
                            <Statistic title="Total Hours Logged" value={total_hours.toFixed(2)} suffix="hours" />
                        </Col>
                        <Col xs={24} sm={8}>
                            <Statistic title="Required Hours" value={required_hours} suffix="hours" />
                        </Col>
                        <Col xs={24} sm={8}>
                            <Statistic title="Remaining Hours" value={remaining_hours.toFixed(2)} suffix="hours" />
                        </Col>
                    </Row>

                    <div className="mb-6">
                        <p className="mb-2">Progress: {progressPercentage.toFixed(1)}%</p>
                        <Progress percent={Math.min(100, Number(progressPercentage.toFixed(10)))} />
                    </div>

                    <Alert
                        type="info"
                        showIcon
                        className="mb-4"
                        message="Rules"
                        description={`Shift start is ${shift_start} with ${grace_minutes} minutes grace period. Past rows are editable only until yesterday. Today uses IN/OUT/BREAK buttons only.`}
                    />

                    {todayRow && (
                        <Card size="small" className="mb-4" title={`Today (${dayjs(todayRow.date).format('YYYY-MM-DD')})`}>
                            <Space wrap>
                                <Button
                                    type="primary"
                                    onClick={handleClockIn}
                                    disabled={!!todayRow.time_in}
                                >
                                    IN
                                </Button>
                                <Button
                                    type="primary"
                                    onClick={handleClockOut}
                                    disabled={!todayRow.time_in || !!todayRow.time_out}
                                >
                                    OUT
                                </Button>
                                <Select
                                    value={breakChoice}
                                    onChange={setBreakChoice}
                                    style={{ width: 120 }}
                                    options={BREAK_OPTIONS}
                                    disabled={todayRow.on_break}
                                />
                                <Button
                                    onClick={handleStartBreak}
                                    disabled={!todayRow.time_in || !!todayRow.time_out || todayRow.on_break}
                                >
                                    Take a Break
                                </Button>
                                <Button
                                    onClick={handleFinishBreak}
                                    disabled={!todayRow.on_break}
                                >
                                    Finish Break
                                </Button>
                                <Button
                                    onClick={() => handleLeave(todayRow)}
                                    disabled={!(todayRow.status === 'missed' && !todayRow.time_in && !todayRow.time_out)}
                                >
                                    LEAVE
                                </Button>
                            </Space>
                            {todayRow.on_break && (
                                <div className="mt-3 text-sm text-amber-600">
                                    Break running: {breakCountdownSec !== null ? `${Math.max(0, breakCountdownSec)}s remaining` : '...'}
                                </div>
                            )}
                        </Card>
                    )}

                    {initialRows.length === 0 ? (
                        <Empty description="No attendance records for this month" />
                    ) : (
                        <Table
                            dataSource={initialRows}
                            columns={columns}
                            rowKey="id"
                            rowClassName={rowClassName}
                            pagination={{ pageSize: 20, total: initialRows.length }}
                            size="small"
                        />
                    )}
                </Card>

                <Modal
                    title="Edit Attendance Record"
                    open={isModalVisible}
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
                        <Form.Item name="remarks" label="Remarks">
                            <Input.TextArea rows={3} />
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
                    <p><strong>Name:</strong> {user.student_name || '-'}</p>
                    <p><strong>Company:</strong> {user.company || '-'}</p>
                    <table className="print-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Day</th>
                                <th>Date</th>
                                <th>IN</th>
                                <th>OUT</th>
                                <th>Break</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            {printableRows.map((row, index) => (
                                <tr key={row.id}>
                                    <td>{index + 1}</td>
                                    <td>{row.day}</td>
                                    <td>{dayjs(row.date).format('MM/DD')}</td>
                                    <td>{formatDisplayTime(row.time_in)}</td>
                                    <td>{formatDisplayTime(row.time_out)}</td>
                                    <td>{row.break_minutes}</td>
                                    <td>{row.total_hours.toFixed(2)}</td>
                                    <td>{row.status}</td>
                                    <td>{row.remarks || ''}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="mt-3">
                        <strong>Total:</strong> {printableTotalHours.toFixed(2)}h | <strong>Remaining:</strong> {printableRemainingHours.toFixed(2)}h
                    </div>
                </div>
            </div>
        </>
    );
}
