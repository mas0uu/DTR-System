import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Card,
    Table,
    Button,
    Modal,
    Form,
    Input,
    Select,
    DatePicker,
    TimePicker,
    Statistic,
    Row,
    Col,
    Space,
    Empty,
    message,
    Progress,
    Alert,
} from 'antd';
import {
    PlusOutlined,
    DeleteOutlined,
    EditOutlined,
    PrinterOutlined,
    ArrowLeftOutlined,
    CheckCircleOutlined,
} from '@ant-design/icons';
import axios from 'axios';
import dayjs from 'dayjs';

interface DtrRow {
    id: number;
    date: string;
    day: string;
    time_in: string | null;
    time_out: string | null;
    total_hours: number;
    total_minutes: number;
    break_minutes: number;
    status: 'draft' | 'finished';
    remarks: string | null;
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
    user: any;
}

export default function DtrShow({ month, rows: initialRows, total_hours, required_hours, remaining_hours, user }: Props) {
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingRow, setEditingRow] = useState<DtrRow | null>(null);
    const [form] = Form.useForm();
    const [submitting, setSubmitting] = useState(false);
    const breakOptions = Array.from({ length: 25 }, (_, i) => i * 5).map((value) => ({
        label: value === 0 ? 'No Break (0 mins)' : `${value} mins`,
        value,
    }));
    const monthStart = dayjs(`${month.year}-${String(month.month).padStart(2, '0')}-01`);

    const formatDisplayTime = (time: string | null) => {
        if (!time) return '-';
        const parsed = dayjs(time, ['HH:mm:ss', 'HH:mm', 'h:mm A'], true);
        return parsed.isValid() ? parsed.format('h:mm A') : time;
    };

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('print') === '1') {
            setTimeout(() => window.print(), 300);
        }
    }, []);

    const handleAddRow = () => {
        setEditingRow(null);
        form.resetFields();
        setIsModalVisible(true);
    };

    const handleEditRow = (row: DtrRow) => {
        setEditingRow(row);
        form.setFieldsValue({
            date: dayjs(row.date),
            time_in: row.time_in ? dayjs(row.time_in, ['HH:mm:ss', 'HH:mm', 'h:mm A']) : null,
            time_out: row.time_out ? dayjs(row.time_out, ['HH:mm:ss', 'HH:mm', 'h:mm A']) : null,
            break_minutes: row.break_minutes ?? 0,
            remarks: row.remarks,
        });
        setIsModalVisible(true);
    };

    const handleDeleteRow = (rowId: number) => {
        Modal.confirm({
            title: 'Delete Record',
            content: 'Are you sure you want to delete this attendance record?',
            okText: 'Yes',
            cancelText: 'No',
            onOk: async () => {
                try {
                    await axios.delete(route('dtr.rows.destroy', { row: rowId }));
                    message.success('Record deleted successfully');
                    router.reload();
                } catch (error) {
                    console.error('Error deleting row:', error);
                    message.error('Failed to delete record');
                }
            },
        });
    };

    const handleSubmit = async (values: any) => {
        setSubmitting(true);
        try {
            const date = values.date ? values.date.format('YYYY-MM-DD') : null;
            const timeIn = values.time_in ? values.time_in.format('HH:mm') : null;
            const timeOut = values.time_out ? values.time_out.format('HH:mm') : null;
            const breakMinutes =
                values.break_minutes !== null && values.break_minutes !== undefined
                    ? Number(values.break_minutes)
                    : null;

            if (editingRow) {
                const payload = {
                    time_in: timeIn,
                    time_out: timeOut,
                    break_minutes: breakMinutes,
                    remarks: values.remarks || null,
                };
                await axios.patch(route('dtr.rows.update', { row: editingRow.id }), payload);
                message.success('Record updated successfully');
            } else {
                const payload = {
                    dtr_month_id: month.id,
                    date: date,
                    time_in: timeIn,
                    time_out: timeOut,
                    break_minutes: breakMinutes,
                    remarks: values.remarks || null,
                };
                await axios.post(route('dtr.rows.store'), payload);
                message.success('Attendance record added');
            }

            setIsModalVisible(false);
            form.resetFields();
            router.reload();
        } catch (error: any) {
            const errorMsg = error.response?.data?.error || error.response?.data?.message || 'Failed to save record';
            message.error(errorMsg);
        } finally {
            setSubmitting(false);
        }
    };

    const handlePrint = () => {
        window.print();
    };

    const handleFinishMonth = async () => {
        Modal.confirm({
            title: 'Finish Month',
            content: 'Are you sure you want to finish this month? You will no longer be able to edit attendance records.',
            okText: 'Finish Month',
            cancelText: 'Cancel',
            onOk: async () => {
                try {
                    await axios.patch(route('dtr.months.finish', { month: month.id }));
                    message.success('Month marked as finished');
                    router.reload();
                } catch (error: any) {
                    const errorMsg = error.response?.data?.message || 'Failed to finish month';
                    message.error(errorMsg);
                }
            },
        });
    };

    const columns = [
        {
            title: 'No.',
            key: 'index',
            render: (_: any, __: any, index: number) => index + 1,
            width: 60,
        },
        {
            title: 'Day',
            dataIndex: 'day',
            key: 'day',
            width: 100,
        },
        {
            title: 'Date',
            dataIndex: 'date',
            key: 'date',
            render: (date: string) => dayjs(date).format('YYYY-MM-DD'),
            width: 120,
        },
        {
            title: 'Time In',
            dataIndex: 'time_in',
            key: 'time_in',
            render: (time: string | null) => formatDisplayTime(time),
            width: 100,
        },
        {
            title: 'Time Out',
            dataIndex: 'time_out',
            key: 'time_out',
            render: (time: string | null) => formatDisplayTime(time),
            width: 100,
        },
        {
            title: 'Total Hours',
            dataIndex: 'total_hours',
            key: 'total_hours',
            render: (hours: number) => hours.toFixed(2),
            width: 120,
        },
        {
            title: 'Break (mins)',
            dataIndex: 'break_minutes',
            key: 'break_minutes',
            render: (minutes: number) => minutes > 0 ? minutes : (minutes === 0 ? '0' : '-'),
            width: 100,
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (status: string) => (
                <span className={status === 'finished' ? 'text-green-600' : 'text-yellow-600'}>
                    {status === 'finished' ? 'Finished' : 'Draft'}
                </span>
            ),
            width: 100,
        },
        {
            title: 'Actions',
            key: 'actions',
            render: (_: any, record: DtrRow) => (
                <Space>
                    <Button
                        type="text"
                        size="small"
                        icon={<EditOutlined />}
                        onClick={() => handleEditRow(record)}
                        disabled={month.is_fulfilled}
                    />
                    <Button
                        type="text"
                        danger
                        size="small"
                        icon={<DeleteOutlined />}
                        onClick={() => handleDeleteRow(record.id)}
                        disabled={month.is_fulfilled}
                    />
                </Space>
            ),
            width: 100,
        },
    ];

    const progressPercentage = (total_hours / required_hours) * 100;
    const isMonthComplete = total_hours >= required_hours;

    return (
        <>
            <Head title={`DTR - ${month.monthName}`} />
            <style>{`
                @media print {
                    @page {
                        size: A4 portrait;
                        margin: 8mm;
                    }

                    body {
                        background: #fff !important;
                    }

                    .screen-only {
                        display: none !important;
                    }

                    .ant-layout-header {
                        display: none !important;
                    }

                    .ant-layout,
                    .ant-layout-content {
                        min-height: auto !important;
                        height: auto !important;
                    }

                    .ant-layout-content > div {
                        max-width: none !important;
                        width: 100% !important;
                        padding: 0 !important;
                        border-radius: 0 !important;
                        box-shadow: none !important;
                        background: #fff !important;
                    }

                    .print-only {
                        display: block !important;
                    }

                    .print-root {
                        width: 100%;
                        color: #000;
                        font-size: 10px;
                        line-height: 1.2;
                        position: relative;
                        padding-bottom: 10px;
                        page-break-inside: avoid;
                        break-inside: avoid-page;
                    }

                    .print-title {
                        text-align: center;
                        font-size: 16px;
                        font-weight: 700;
                        margin-bottom: 8px;
                    }

                    .print-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 6px 16px;
                        margin-bottom: 8px;
                    }

                    .print-field {
                        border-bottom: 1px solid #000;
                        padding-bottom: 2px;
                        min-height: 14px;
                    }

                    .print-table {
                        width: 100%;
                        border-collapse: collapse;
                        table-layout: fixed;
                    }

                    .print-table th,
                    .print-table td {
                        border: 1px solid #000;
                        padding: 2px 3px;
                        font-size: 9px;
                        text-align: center;
                        vertical-align: middle;
                        word-break: break-word;
                    }

                    .print-summary {
                        margin-top: 6px;
                        display: grid;
                        grid-template-columns: 1fr 1fr 1fr;
                        gap: 8px;
                        font-size: 10px;
                    }

                    .print-sign {
                        margin-top: 14px;
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 16px;
                    }

                    .print-sign-line {
                        border-bottom: 1px solid #000;
                        height: 24px;
                    }

                    .print-footer {
                        position: absolute;
                        right: 0;
                        bottom: 0;
                        text-align: right;
                        font-size: 8px;
                        color: #333;
                    }
                }
            `}</style>

            <div className="screen-only">
                    {/* Header */}
                    <div className="mb-4 flex items-center justify-between">
                        <Link href={route('dtr.index', { tab: '2' })}>
                            <Button icon={<ArrowLeftOutlined />}>
                                Back
                            </Button>
                        </Link>
                        <Button
                            icon={<PrinterOutlined />}
                            onClick={handlePrint}
                        >
                            Print DTR
                        </Button>
                    </div>

                    {/* Month Card */}
                    <Card
                        title={<h1 className="text-2xl font-bold">{month.monthName} DTR</h1>}
                        className="mb-6"
                    >
                        {isMonthComplete && (
                            <Alert
                                message="Month Fulfilled"
                                description="You have fulfilled the required hours for this month."
                                type="success"
                                showIcon
                                className="mb-4"
                            />
                        )}

                        <Row gutter={16} className="mb-6">
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Total Hours Logged"
                                    value={total_hours.toFixed(2)}
                                    suffix="hours"
                                    valueStyle={{ color: '#1890ff' }}
                                />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Required Hours"
                                    value={required_hours}
                                    suffix="hours"
                                />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Remaining Hours"
                                    value={Math.max(0, remaining_hours).toFixed(2)}
                                    suffix="hours"
                                    valueStyle={{ color: remaining_hours > 0 ? '#cf1322' : '#52c41a' }}
                                />
                            </Col>
                        </Row>

                        <div className="mb-6">
                            <p className="mb-2">Progress: {progressPercentage.toFixed(1)}%</p>
                            <Progress
                                percent={Math.min(100, progressPercentage)}
                                strokeColor={isMonthComplete ? '#52c41a' : '#1890ff'}
                            />
                        </div>

                        <div className="mb-4 flex items-center gap-2">
                            <Button
                                type="primary"
                                icon={<PlusOutlined />}
                                onClick={handleAddRow}
                                disabled={month.is_fulfilled}
                            >
                                Add Attendance Record
                            </Button>
                            <Button
                                icon={<CheckCircleOutlined />}
                                onClick={handleFinishMonth}
                                disabled={month.is_fulfilled}
                            >
                                {month.is_fulfilled ? 'Month Finished' : 'Finish Month'}
                            </Button>
                        </div>

                        {initialRows.length === 0 ? (
                            <Empty
                                description="No attendance records for this month"
                                style={{ marginTop: 50, marginBottom: 50 }}
                            />
                        ) : (
                            <Table
                                dataSource={initialRows}
                                columns={columns}
                                rowKey="id"
                                pagination={{
                                    pageSize: 20,
                                    total: initialRows.length,
                                }}
                                size="small"
                            />
                        )}
                    </Card>

                    {/* Modal for adding/editing records */}
                    <Modal
                        title={editingRow ? 'Edit Attendance Record' : 'Add Attendance Record'}
                        open={isModalVisible}
                        onCancel={() => {
                            setIsModalVisible(false);
                            setEditingRow(null);
                            form.resetFields();
                        }}
                        footer={null}
                    >
                        <Form
                            form={form}
                            layout="vertical"
                            onFinish={handleSubmit}
                        >
                            {!editingRow && (
                                <Form.Item
                                    name="date"
                                    label="Date"
                                    rules={[{ required: true, message: 'Please select a date' }]}
                                >
                                    <DatePicker
                                        style={{ width: '100%' }}
                                        defaultPickerValue={monthStart}
                                        disabledDate={(current) => {
                                            if (!current) return false;
                                            const outsideMonth =
                                                current.year() !== month.year ||
                                                current.month() + 1 !== month.month;
                                            const recordDate = dayjs(current).format('YYYY-MM-DD');
                                            return outsideMonth || initialRows.some((row) => row.date === recordDate);
                                        }}
                                    />
                                </Form.Item>
                            )}

                            <Row gutter={12}>
                                <Col xs={24} md={12}>
                                    <Form.Item
                                        name="time_in"
                                        label="Time In"
                                        rules={[{ required: true, message: 'Please select time in' }]}
                                    >
                                        <TimePicker style={{ width: '100%' }} format="h:mm A" use12Hours />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} md={12}>
                                    <Form.Item
                                        name="time_out"
                                        label="Time Out"
                                    >
                                        <TimePicker style={{ width: '100%' }} format="h:mm A" use12Hours />
                                    </Form.Item>
                                </Col>
                            </Row>

                            <Form.Item
                                name="break_minutes"
                                label="Break Time (Optional)"
                            >
                                <Select allowClear placeholder="Select break time" options={breakOptions} />
                            </Form.Item>

                            <Form.Item
                                name="remarks"
                                label="Remarks (Optional)"
                            >
                                <Input.TextArea rows={3} placeholder="Any additional notes" />
                            </Form.Item>

                            <Form.Item>
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    loading={submitting}
                                    block
                                >
                                    {editingRow ? 'Update Record' : 'Add Record'}
                                </Button>
                            </Form.Item>
                        </Form>
                    </Modal>
            </div>

            <div className="print-only" style={{ display: 'none' }}>
                <div className="print-root">
                    <div className="print-title">DAILY TIME RECORD - {month.monthName.toUpperCase()}</div>

                    <div className="print-grid">
                        <div>
                            <div><strong>Student Name:</strong></div>
                            <div className="print-field">{user.student_name || '-'}</div>
                        </div>
                        <div>
                            <div><strong>Student Number:</strong></div>
                            <div className="print-field">{user.student_no || '-'}</div>
                        </div>
                        <div>
                            <div><strong>School:</strong></div>
                            <div className="print-field">{user.school || '-'}</div>
                        </div>
                        <div>
                            <div><strong>Required Hours:</strong></div>
                            <div className="print-field">{required_hours}</div>
                        </div>
                        <div>
                            <div><strong>Company:</strong></div>
                            <div className="print-field">{user.company || '-'}</div>
                        </div>
                        <div>
                            <div><strong>Department:</strong></div>
                            <div className="print-field">{user.department || '-'}</div>
                        </div>
                        <div>
                            <div><strong>Supervisor Name:</strong></div>
                            <div className="print-field">{user.supervisor_name || '-'}</div>
                        </div>
                        <div>
                            <div><strong>Supervisor Position:</strong></div>
                            <div className="print-field">{user.supervisor_position || '-'}</div>
                        </div>
                    </div>

                    <table className="print-table">
                        <thead>
                            <tr>
                                <th style={{ width: '4%' }}>No.</th>
                                <th style={{ width: '10%' }}>Day</th>
                                <th style={{ width: '10%' }}>Date</th>
                                <th style={{ width: '12%' }}>Time In</th>
                                <th style={{ width: '12%' }}>Time Out</th>
                                <th style={{ width: '10%' }}>Break</th>
                                <th style={{ width: '10%' }}>Total Hrs</th>
                                <th style={{ width: '10%' }}>Status</th>
                                <th style={{ width: '22%' }}>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            {initialRows.map((row, index) => (
                                <tr key={row.id}>
                                    <td>{index + 1}</td>
                                    <td>{row.day}</td>
                                    <td>{dayjs(row.date).format('MM/DD')}</td>
                                    <td>{formatDisplayTime(row.time_in)}</td>
                                    <td>{formatDisplayTime(row.time_out)}</td>
                                    <td>{row.break_minutes ?? 0}</td>
                                    <td>{row.total_hours.toFixed(2)}</td>
                                    <td>{row.status}</td>
                                    <td>{row.remarks || ''}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <div className="print-summary">
                        <div><strong>Total Hours Logged:</strong> {total_hours.toFixed(2)}</div>
                        <div><strong>Required Hours:</strong> {required_hours}</div>
                        <div><strong>Remaining Hours:</strong> {Math.max(0, remaining_hours).toFixed(2)}</div>
                    </div>

                    <div className="print-sign">
                        <div>
                            <div className="print-sign-line" />
                            <div style={{ textAlign: 'center', marginTop: 4 }}>Student Signature</div>
                        </div>
                        <div>
                            <div className="print-sign-line" />
                            <div style={{ textAlign: 'center', marginTop: 4 }}>
                                {user.supervisor_name || 'Supervisor Name'}
                            </div>
                            <div style={{ textAlign: 'center', fontSize: 9 }}>
                                {user.supervisor_position || 'Supervisor'}
                            </div>
                        </div>
                    </div>

                    <div className="print-footer">DTR System by Johnson Roque Jr.</div>
                </div>
            </div>
        </>
    );
}
