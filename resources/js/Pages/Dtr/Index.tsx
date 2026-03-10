import { Head, Link, router } from '@inertiajs/react';
import { attendanceStatusColor, rowStateColor, rowStateLabel } from '@/lib/attendanceStatus';
import TableCard from '@/Components/ui/TableCard';
import { List, Button, Statistic, Row, Col, Empty, Progress, Tag, Typography, Select, Space, message } from 'antd';
import { ArrowRightOutlined, EditOutlined, PrinterOutlined } from '@ant-design/icons';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import dayjs from 'dayjs';

interface User {
    name?: string;
    student_name: string;
    student_no: string | null;
    school: string | null;
    required_hours: number;
    employee_type?: 'intern' | 'regular' | null;
    company: string;
    department: string;
    supervisor_name: string;
    supervisor_position: string;
    working_days?: number[] | null;
    intern_compensation_enabled?: boolean;
    work_time_in?: string | null;
    work_time_out?: string | null;
    default_break_minutes?: number | null;
    salary_type?: 'monthly' | 'daily' | 'hourly' | null;
    salary_amount?: number | null;
}

interface Month {
    id: number;
    month: number;
    year: number;
    monthName: string;
    is_fulfilled: boolean;
    total_hours: number;
    finished_rows: number;
}

interface Props {
    months: Month[];
    user: User;
    today_row: TodayRow | null;
    shift_start: string;
    grace_minutes: number;
    payroll_records: PayrollRecord[];
    payroll_access_enabled: boolean;
    initial_tab?: '1' | '2' | '3' | '4';
}

const { Title, Paragraph } = Typography;
const BREAK_OPTIONS = [5, 10, 15, 30, 45, 60].map((minutes) => ({
    label: `${minutes} mins`,
    value: minutes,
}));
const DAY_LABELS: Record<number, string> = {
    0: 'Sunday',
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
};

interface TodayRow {
    id: number;
    dtr_month_id: number;
    date: string;
    time_in: string | null;
    time_out: string | null;
    on_break: boolean;
    break_minutes: number;
    break_target_minutes: number | null;
    break_started_at: string | null;
    late_minutes: number;
    status: 'draft' | 'missed' | 'in_progress' | 'finished' | 'leave';
    leave_request_status?: 'pending' | 'approved' | 'rejected' | 'cancelled' | null;
    leave_request_type?: 'leave' | 'intern_absence' | null;
    leave_request_is_paid?: boolean | null;
    attendance_statuses: string[];
    warnings: string[];
}

interface PayrollRecord {
    id: number;
    pay_period_start: string;
    pay_period_end: string;
    salary_type: 'monthly' | 'daily' | 'hourly';
    salary_amount: number;
    days_worked: number;
    hours_worked: number;
    absences: number;
    undertime_minutes: number;
    half_days: number;
    total_salary: number;
    status: 'generated' | 'reviewed' | 'finalized';
    payslip_available: boolean;
}

type PayrollCutoffType = 'first_15' | 'last_15' | 'whole_month';
type PayrollOrderType = 'recent' | 'oldest';

const resolvePayrollPeriod = (payPeriodMonth: string, cutoffType: PayrollCutoffType): [string, string] | null => {
    const monthStart = dayjs(`${payPeriodMonth}-01`).startOf('month');
    if (!monthStart.isValid()) {
        return null;
    }

    if (cutoffType === 'first_15') {
        return [monthStart.format('YYYY-MM-DD'), monthStart.date(15).format('YYYY-MM-DD')];
    }

    if (cutoffType === 'last_15') {
        return [monthStart.date(16).format('YYYY-MM-DD'), monthStart.endOf('month').format('YYYY-MM-DD')];
    }

    return [monthStart.format('YYYY-MM-DD'), monthStart.endOf('month').format('YYYY-MM-DD')];
};

export default function DtrIndex({
    months,
    user,
    today_row,
    shift_start = '08:00',
    grace_minutes = 30,
    payroll_records,
    payroll_access_enabled,
    initial_tab = '1',
}: Props) {
    const [todayRow, setTodayRow] = useState<TodayRow | null>(today_row);
    const [breakChoice, setBreakChoice] = useState<number>(user.default_break_minutes ?? 60);
    const [nowTick, setNowTick] = useState<number>(Date.now());
    const [payrollCutoffType, setPayrollCutoffType] = useState<PayrollCutoffType>('whole_month');
    const [payrollMonth, setPayrollMonth] = useState<string>(dayjs().format('YYYY-MM'));
    const [payrollRecordsOrderFilter, setPayrollRecordsOrderFilter] = useState<PayrollOrderType>('recent');
    const [generatingPayroll, setGeneratingPayroll] = useState(false);
    const isIntern = user.employee_type === 'intern';
    const displayName = user.student_name || user.name || '-';
    const totalLoggedHours = months.reduce((sum, month) => sum + month.total_hours, 0);
    const remainingHours = Math.max(0, user.required_hours - totalLoggedHours);
    const percent = user.required_hours
        ? Math.min(100, (totalLoggedHours / user.required_hours) * 100)
        : 0;
    const canClockIn = !!todayRow && !todayRow.time_in;
    const canClockOut = !!todayRow && !!todayRow.time_in && !todayRow.time_out;
    const canStartBreak = !!todayRow && !!todayRow.time_in && !todayRow.time_out && !todayRow.on_break;
    const canFinishBreak = !!todayRow && todayRow.on_break;
    const selectedRange = useMemo(() => {
        if (payrollMonth === 'all') {
            return null;
        }
        return resolvePayrollPeriod(payrollMonth, payrollCutoffType);
    }, [payrollMonth, payrollCutoffType]);
    const canGeneratePayroll = payroll_access_enabled && !!selectedRange && !!user.salary_type && !!user.salary_amount;
    const payrollMonthOptions = useMemo(() => {
        const months = Array.from(new Set([
            dayjs().format('YYYY-MM'),
            ...payroll_records.map((row) => dayjs(row.pay_period_start).format('YYYY-MM')),
        ])).sort((a, b) => b.localeCompare(a));

        return [
            { label: 'All months', value: 'all' as const },
            ...months.map((month) => ({
                label: dayjs(`${month}-01`).format('MMMM YYYY'),
                value: month,
            })),
        ];
    }, [payroll_records]);
    const filteredPayrollRecords = useMemo(() => {
        const monthFiltered = payrollMonth === 'all'
            ? payroll_records
            : payroll_records.filter((row) => dayjs(row.pay_period_start).format('YYYY-MM') === payrollMonth);

        return [...monthFiltered].sort((a, b) => {
            const aEnd = dayjs(a.pay_period_end).valueOf();
            const bEnd = dayjs(b.pay_period_end).valueOf();
            if (aEnd === bEnd) {
                const aStart = dayjs(a.pay_period_start).valueOf();
                const bStart = dayjs(b.pay_period_start).valueOf();
                return payrollRecordsOrderFilter === 'recent' ? bStart - aStart : aStart - bStart;
            }

            return payrollRecordsOrderFilter === 'recent' ? bEnd - aEnd : aEnd - bEnd;
        });
    }, [payrollMonth, payrollRecordsOrderFilter, payroll_records]);

    const formatDisplayTime = (time: string | null | undefined) => {
        if (!time) return '-';
        const parsed = dayjs(time, ['HH:mm:ss', 'HH:mm'], true);
        return parsed.isValid() ? parsed.format('h:mm A') : time;
    };

    const workingDaysLabel = useMemo(() => {
        const days = Array.isArray(user.working_days) ? user.working_days : [];
        if (days.length === 0) return '-';

        const labels = days
            .map((day) => DAY_LABELS[day])
            .filter((label): label is string => Boolean(label));

        return labels.length > 0 ? labels.join(', ') : '-';
    }, [user.working_days]);

    const workScheduleLabel = useMemo(() => {
        const timeIn = formatDisplayTime(user.work_time_in);
        const timeOut = formatDisplayTime(user.work_time_out);

        if (timeIn === '-' && timeOut === '-') {
            return '-';
        }

        return `${timeIn} - ${timeOut}`;
    }, [user.work_time_in, user.work_time_out]);

    useEffect(() => {
        const timer = window.setInterval(() => setNowTick(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);

    const manilaTime = useMemo(() => {
        return new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        }).format(nowTick);
    }, [nowTick]);

    const manilaDate = useMemo(() => {
        return new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            weekday: 'long',
            month: 'long',
            day: '2-digit',
            year: 'numeric',
        }).format(nowTick);
    }, [nowTick]);

    const breakCountdown = useMemo(() => {
        if (!todayRow?.on_break || !todayRow.break_started_at || !todayRow.break_target_minutes) {
            return null;
        }

        const started = new Date(todayRow.break_started_at).getTime();
        const targetEnd = started + todayRow.break_target_minutes * 60 * 1000;
        const remainingSec = Math.ceil((targetEnd - nowTick) / 1000);
        return Math.max(0, remainingSec);
    }, [todayRow, nowTick]);

    const graceCutoffTime = useMemo(() => {
        const [hoursRaw, minutesRaw = '0'] = shift_start.split(':');
        const shiftHours = Number(hoursRaw);
        const shiftMinutes = Number(minutesRaw);

        if (Number.isNaN(shiftHours) || Number.isNaN(shiftMinutes)) {
            return 'N/A';
        }

        const utcDate = new Date(Date.UTC(2000, 0, 1, shiftHours, shiftMinutes, 0));
        utcDate.setUTCMinutes(utcDate.getUTCMinutes() + grace_minutes);

        return utcDate.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
            timeZone: 'UTC',
        });
    }, [shift_start, grace_minutes]);

    const formatCountdown = (totalSeconds: number) => {
        const safeSeconds = Math.max(0, totalSeconds);
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);
        const seconds = safeSeconds % 60;
        return `${hours}h ${minutes}m ${seconds}s`;
    };

    const handleClockIn = async () => {
        if (!todayRow) return;
        try {
            const { data } = await axios.patch(route('dtr.rows.clock_in', { row: todayRow.id }));
            setTodayRow((prev) => (prev ? { ...prev, ...data } : prev));
            router.reload({ only: ['months', 'today_row', 'shift_start', 'grace_minutes'] });
            message.success('Clocked in successfully.');
        } catch (error) {
            console.error(error);
            message.error('Clock in failed.');
        }
    };

    const handleClockOut = async () => {
        if (!todayRow) return;
        try {
            const { data } = await axios.patch(route('dtr.rows.clock_out', { row: todayRow.id }));
            setTodayRow((prev) => (prev ? { ...prev, ...data } : prev));
            router.reload({ only: ['months', 'today_row', 'shift_start', 'grace_minutes'] });
            message.success('Clocked out successfully.');
        } catch (error) {
            console.error(error);
            message.error('Clock out failed.');
        }
    };

    const handleStartBreak = async () => {
        if (!todayRow) return;
        try {
            const { data } = await axios.patch(route('dtr.rows.break_start', { row: todayRow.id }), { minutes: breakChoice });
            setTodayRow((prev) => (prev ? { ...prev, ...data } : prev));
            router.reload({ only: ['months', 'today_row', 'shift_start', 'grace_minutes'] });
            message.success('Break started.');
        } catch (error) {
            console.error(error);
            message.error('Start break failed.');
        }
    };

    const handleFinishBreak = async () => {
        if (!todayRow) return;
        try {
            const { data } = await axios.patch(route('dtr.rows.break_finish', { row: todayRow.id }));
            setTodayRow((prev) => (prev ? { ...prev, ...data } : prev));
            router.reload({ only: ['months', 'today_row', 'shift_start', 'grace_minutes'] });
            message.success('Break finished.');
        } catch (error) {
            console.error(error);
            message.error('Finish break failed.');
        }
    };

    const handleGeneratePayroll = async () => {
        if (!payroll_access_enabled) {
            message.error('Payroll is disabled for this internship program.');
            return;
        }
        if (!selectedRange) {
            message.error('Select a specific month first.');
            return;
        }

        setGeneratingPayroll(true);
        try {
            await axios.post(route('payroll.generate'), {
                pay_period_start: selectedRange[0],
                pay_period_end: selectedRange[1],
            });
            message.success('Payroll generated.');
            router.reload({ only: ['payroll_records'] });
        } catch (error: any) {
            message.error(error?.response?.data?.error || 'Payroll generation failed.');
        } finally {
            setGeneratingPayroll(false);
        }
    };

    const homeTabContent = (
        <TableCard title="Today's Attendance">
            {isIntern && (
                <div className="mx-auto mb-4 w-full max-w-xl">
                    <div className="mb-1 text-xs text-slate-600">
                        {`${totalLoggedHours.toFixed(2)} / ${Number(user.required_hours).toFixed(2)} hrs`}
                    </div>
                    <Progress percent={Number(percent.toFixed(1))} size="small" />
                </div>
            )}

            <div className="mb-4 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-center text-sm text-yellow-900">
                <span className="font-semibold">Late Rule:</span>{' '}
                Shift starts at {shift_start}. Grace period is {grace_minutes} minutes (until {graceCutoffTime}). Clock-in after grace is marked late.
            </div>

            <div className="mx-auto flex w-full max-w-2xl flex-col items-center gap-3 text-center">
                <Paragraph className="!mb-0 !text-slate-500">Current Time (Asia/Manila)</Paragraph>
                <Title
                    level={1}
                    style={{ margin: 0, lineHeight: 1.05, fontSize: '72px', fontWeight: 700, letterSpacing: '-0.02em' }}
                >
                    {manilaTime}
                </Title>
                <Paragraph className="!mt-0 !mb-1 !text-slate-600">{manilaDate}</Paragraph>

                <div className="flex flex-wrap justify-center gap-2">
                    <Button type="primary" size="large" onClick={handleClockIn} disabled={!canClockIn}>
                        Clock In
                    </Button>
                    <Button type="primary" size="large" onClick={handleClockOut} disabled={!canClockOut}>
                        Clock Out
                    </Button>
                </div>

                {todayRow && (
                    <div className="flex flex-wrap justify-center gap-2">
                        <Tag color={rowStateColor(todayRow.status)}>
                            Row State: {rowStateLabel(todayRow.status)}
                        </Tag>
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
                        {todayRow.late_minutes > 0 && <Tag color={attendanceStatusColor('Late')}>Late: {todayRow.late_minutes} mins</Tag>}
                        {todayRow.attendance_statuses.length === 0 ? (
                            <Tag>None</Tag>
                        ) : (
                            todayRow.attendance_statuses.map((status) => (
                                <Tag key={`today-${todayRow.id}-${status}`} color={attendanceStatusColor(status)}>
                                    {status}
                                </Tag>
                            ))
                        )}
                        {todayRow.warnings.map((warning) => (
                            <Tag key={`warning-${todayRow.id}-${warning}`} color="red">
                                {warning}
                            </Tag>
                        ))}
                    </div>
                )}
                {!todayRow && <Paragraph className="!mb-0 !text-slate-700">No row scheduled for today based on your working days.</Paragraph>}

                <div className="flex flex-wrap items-center justify-center gap-2">
                    <span className="text-sm text-slate-600">Break Duration</span>
                    <Select
                        style={{ width: 140 }}
                        value={breakChoice}
                        options={BREAK_OPTIONS}
                        onChange={setBreakChoice}
                        disabled={!canStartBreak}
                    />
                    <Button onClick={handleStartBreak} disabled={!canStartBreak}>Start Break</Button>
                    <Button onClick={handleFinishBreak} disabled={!canFinishBreak}>Finish Break</Button>
                </div>

                {todayRow?.on_break && (
                    <Paragraph className="!mb-0 !text-amber-600">
                        Break running{breakCountdown !== null ? `: ${formatCountdown(breakCountdown)} remaining` : ''}.
                    </Paragraph>
                )}
            </div>
        </TableCard>
    );

    const sections = [
        {
            key: '1',
            children: homeTabContent,
        },
        {
            key: '2',
            children: (
                <TableCard title={`Monthly Records (${months.length})`}>
                    {months.length === 0 ? (
                        <Empty description="No DTR records found" style={{ marginTop: 50, marginBottom: 24 }} />
                    ) : (
                        <List
                            dataSource={months}
                            renderItem={(month) => (
                                <div key={month.id}>
                                    <List.Item
                                        actions={[
                                            <a
                                                href={route('dtr.months.show', { month: month.id, print: 1 })}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Button icon={<PrinterOutlined />}>Print DTR</Button>
                                            </a>,
                                            <Link href={route('dtr.months.show', month.id)}>
                                                <Button type="primary">
                                                    View <ArrowRightOutlined />
                                                </Button>
                                            </Link>,
                                        ]}
                                    >
                                        <List.Item.Meta
                                            title={month.monthName}
                                            description={`${month.total_hours.toFixed(2)} hours (days recorded: ${month.finished_rows})`}
                                        />
                                        {month.is_fulfilled && <Tag color="success">Fulfilled</Tag>}
                                    </List.Item>
                                </div>
                            )}
                        />
                    )}
                </TableCard>
            ),
        },
        {
            key: '3',
            children: (
                <TableCard
                    title="User Information"
                    actions={(
                        <Link href={route('profile.edit')}>
                            <Button icon={<EditOutlined />}>Edit User Information</Button>
                        </Link>
                    )}
                >
                    <Row gutter={16}>
                        <Col xs={24} sm={12}>
                            <Statistic title={isIntern ? 'Student Name' : 'Employee Name'} value={displayName} />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic
                                title="Employee Type"
                                value={isIntern ? 'Intern' : 'Regular Employee'}
                                formatter={(value) => value}
                            />
                        </Col>
                    </Row>
                    {isIntern && (
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <Statistic title="Student Number" value={user.student_no || '-'} formatter={(value) => value} />
                            </Col>
                            <Col xs={24} sm={12}>
                                <Statistic title="School" value={user.school || '-'} />
                            </Col>
                        </Row>
                    )}
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic title="Total Logged Hours" value={totalLoggedHours.toFixed(2)} suffix="hours" />
                        </Col>
                        <Col xs={24} sm={12}>
                            {isIntern && <Statistic title="Remaining Hours" value={remainingHours.toFixed(2)} suffix="hours" />}
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic title="Company" value={user.company} />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic title="Department" value={user.department} />
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic title="Supervisor Name" value={user.supervisor_name} />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic title="Supervisor Position" value={user.supervisor_position} />
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic title="Working Days" value={workingDaysLabel} formatter={(value) => value} />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic title="Work Schedule" value={workScheduleLabel} formatter={(value) => value} />
                        </Col>
                    </Row>
                    {(!isIntern || payroll_access_enabled) ? (
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <Statistic title="Salary Type" value={user.salary_type ? user.salary_type.toUpperCase() : '-'} />
                            </Col>
                            <Col xs={24} sm={12}>
                                <Statistic title="Salary Amount" value={user.salary_amount ?? 0} precision={2} prefix="PHP" />
                            </Col>
                        </Row>
                    ) : (
                        <Row gutter={16} className="mt-4">
                            <Col xs={24}>
                                <Tag color="default">Compensation disabled for this internship.</Tag>
                            </Col>
                        </Row>
                    )}
                </TableCard>
            ),
        },
        {
            key: '4',
            children: (
                <TableCard title={`Payroll (${payroll_records.length})`}>
                    <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <Space wrap>
                            <Select
                                style={{ width: 150 }}
                                value={payrollCutoffType}
                                onChange={(value) => setPayrollCutoffType(value as PayrollCutoffType)}
                                options={[
                                    { label: 'First 15', value: 'first_15' },
                                    { label: 'Last 15', value: 'last_15' },
                                    { label: 'Whole Month', value: 'whole_month' },
                                ]}
                            />
                            <Select
                                style={{ width: 190 }}
                                value={payrollMonth}
                                onChange={(value) => setPayrollMonth(String(value))}
                                options={payrollMonthOptions}
                            />
                            <Select
                                style={{ width: 130 }}
                                value={payrollRecordsOrderFilter}
                                onChange={(value) => setPayrollRecordsOrderFilter(value as PayrollOrderType)}
                                options={[
                                    { label: 'Recent', value: 'recent' },
                                    { label: 'Oldest', value: 'oldest' },
                                ]}
                            />
                        </Space>
                        <Button type="primary" onClick={handleGeneratePayroll} loading={generatingPayroll} disabled={!canGeneratePayroll}>
                            Generate Payroll
                        </Button>
                    </div>
                    <div className="mb-4 text-sm text-slate-600">
                        Selected Period:{' '}
                        <span className="font-medium">
                            {selectedRange ? `${selectedRange[0]} to ${selectedRange[1]}` : 'All months selected'}
                        </span>
                    </div>

                    {!user.salary_type || !user.salary_amount ? (
                        <Empty description="Salary setup is missing for this employee." />
                    ) : filteredPayrollRecords.length === 0 ? (
                        <Empty description="No payroll records generated yet." />
                    ) : (
                        <List
                            dataSource={filteredPayrollRecords}
                            renderItem={(record) => (
                                <List.Item key={record.id}>
                                    <List.Item.Meta
                                        title={`${record.pay_period_start} to ${record.pay_period_end}`}
                                        description={`Type: ${record.salary_type.toUpperCase()} | Base: PHP ${record.salary_amount.toFixed(2)} | Days: ${record.days_worked.toFixed(2)} | Hours: ${record.hours_worked.toFixed(2)} | Absences: ${record.absences} | Undertime: ${record.undertime_minutes} mins | Half-day: ${record.half_days}`}
                                    />
                                    <Space>
                                        <Tag color={record.status === 'finalized' ? 'green' : record.status === 'reviewed' ? 'gold' : 'blue'}>
                                            {record.status.toUpperCase()}
                                        </Tag>
                                        <Tag color="blue">PHP {record.total_salary.toFixed(2)}</Tag>
                                        {record.status === 'finalized' ? (
                                            <Space wrap>
                                                <Link href={route('payroll.payslip.view', record.id)}>
                                                    <Button size="small">View Payslip</Button>
                                                </Link>
                                                <Button
                                                    size="small"
                                                    onClick={() => {
                                                        window.open(
                                                            route('payroll.payslip.view', {
                                                                payrollRecord: record.id,
                                                                print: 1,
                                                            }),
                                                            '_blank',
                                                            'noopener,noreferrer'
                                                        );
                                                    }}
                                                >
                                                    Print Payslip
                                                </Button>
                                                {record.payslip_available && (
                                                    <a href={route('payroll.payslip', record.id)}>
                                                        <Button size="small">Download</Button>
                                                    </a>
                                                )}
                                            </Space>
                                        ) : (
                                            <Tag color="default">Finalize first</Tag>
                                        )}
                                    </Space>
                                </List.Item>
                            )}
                        />
                    )}
                </TableCard>
            ),
        },
    ];
    const visibleSections = payroll_access_enabled
        ? sections
        : sections.filter((section) => section.key !== '4');
    const normalizedTab = initial_tab === '4' && !payroll_access_enabled ? '1' : initial_tab;
    const activeSection = visibleSections.find((section) => section.key === normalizedTab) ?? visibleSections[0];

    return (
        <>
            <Head title="Daily Time Records" />

            {activeSection?.children}
        </>
    );
}
