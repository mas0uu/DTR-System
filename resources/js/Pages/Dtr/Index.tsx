import { Head, Link, router } from '@inertiajs/react';
import { Card, List, Button, Statistic, Row, Col, Empty, Tabs, Progress, Tag, Typography, Select, message } from 'antd';
import { ArrowRightOutlined, EditOutlined, PrinterOutlined } from '@ant-design/icons';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';

interface User {
    student_name: string;
    student_no: string | null;
    school: string | null;
    required_hours: number;
    employee_type?: 'intern' | 'regular' | null;
    company: string;
    department: string;
    supervisor_name: string;
    supervisor_position: string;
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
}

const { Title, Paragraph } = Typography;
const BREAK_OPTIONS = [5, 10, 15, 30, 45, 60].map((minutes) => ({
    label: `${minutes} mins`,
    value: minutes,
}));

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
}

export default function DtrIndex({ months, user, today_row, shift_start = '08:00', grace_minutes = 30 }: Props) {
    const [todayRow, setTodayRow] = useState<TodayRow | null>(today_row);
    const [breakChoice, setBreakChoice] = useState<number>(15);
    const [nowTick, setNowTick] = useState<number>(Date.now());
    const isIntern = user.employee_type === 'intern';
    const displayName = user.student_name || '-';
    const totalLoggedHours = months.reduce((sum, month) => sum + month.total_hours, 0);
    const remainingHours = Math.max(0, user.required_hours - totalLoggedHours);
    const percent = user.required_hours
        ? Math.min(100, (totalLoggedHours / user.required_hours) * 100)
        : 0;
    const canClockIn = !!todayRow && !todayRow.time_in;
    const canClockOut = !!todayRow && !!todayRow.time_in && !todayRow.time_out;
    const canStartBreak = !!todayRow && !!todayRow.time_in && !todayRow.time_out && !todayRow.on_break;
    const canFinishBreak = !!todayRow && todayRow.on_break;

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

    const homeTabContent = (
        <Card size="small" className="mb-2">
            <div className="mb-4 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-center text-sm text-yellow-900">
                <span className="font-semibold">Late Rule:</span>{' '}
                Shift starts at {shift_start}. Grace period is {grace_minutes} minutes (until 08:30 AM). Clock-in after grace is marked late.
            </div>

            <div className="mx-auto flex w-full max-w-2xl flex-col items-center gap-3 text-center">
                <Paragraph className="!mb-0 !text-slate-500">Current Time (Asia/Manila)</Paragraph>
                <Title level={1} style={{ margin: 0, lineHeight: 1.1 }}>{manilaTime}</Title>
                <Paragraph className="!mt-0 !mb-1 !text-slate-600">{manilaDate}</Paragraph>

                <div className="flex flex-wrap justify-center gap-2">
                    <Button type="primary" size="large" onClick={handleClockIn} disabled={!canClockIn}>
                        Clock In
                    </Button>
                    <Button type="primary" size="large" onClick={handleClockOut} disabled={!canClockOut}>
                        Clock Out
                    </Button>
                </div>

                <Paragraph className="!mb-0 !text-slate-700">
                    {todayRow
                        ? `Today Status: ${todayRow.status.toUpperCase()}${todayRow.late_minutes > 0 ? ` | Late: ${todayRow.late_minutes} mins` : ''}`
                        : 'No row scheduled for today based on your working days.'}
                </Paragraph>

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
        </Card>
    );

    const tabItems = [
        {
            key: '1',
            label: 'Home',
            children: homeTabContent,
        },
        {
            key: '2',
            label: `Monthly Records (${months.length})`,
            children: (
                <div>
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
                </div>
            ),
        },
        {
            key: '3',
            label: 'User Information',
            children: (
                <Card>
                    <div className="mb-4 flex justify-end">
                        <Link href={route('profile.edit')}>
                            <Button icon={<EditOutlined />}>Edit User Information</Button>
                        </Link>
                    </div>
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
                </Card>
            ),
        },
    ];

    return (
        <>
            <Head title="DTR Records" />

            <Card
                title={<Title level={3} style={{ margin: 0 }}>Daily Time Record</Title>}
                extra={
                    <div className="text-right">
                        <Paragraph className="!mb-0 !text-slate-600">Welcome, {user.student_name}!</Paragraph>
                    </div>
                }
            >
                <Tabs
                    defaultActiveKey="1"
                    items={tabItems}
                    tabBarExtraContent={isIntern ? (
                        <div style={{ width: 260, paddingLeft: 12 }}>
                            <div style={{ fontSize: 12, marginBottom: -10 }}>
                                {`${totalLoggedHours.toFixed(2)} / ${Number(user.required_hours).toFixed(2)} hrs`}
                            </div>
                            <Progress percent={Number(percent.toFixed(1))} size="small" />
                        </div>
                    ) : undefined}
                />
            </Card>
        </>
    );
}
