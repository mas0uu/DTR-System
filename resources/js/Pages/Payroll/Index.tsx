import { PageProps as AppPageProps } from '@/types';
import PageHeader from '@/Components/ui/PageHeader';
import MetricCard from '@/Components/ui/MetricCard';
import TableCard from '@/Components/ui/TableCard';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Empty, Select, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';

type SalarySummary = {
    salary_type: 'monthly' | 'daily' | 'hourly' | null;
    salary_amount: number | null;
};

type PayrollRecord = {
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
    base_pay: number;
    paid_leave_pay: number;
    paid_holiday_base_pay: number;
    holiday_attendance_bonus: number;
    leave_deductions: number;
    other_deductions: number;
    total_deductions: number;
    net_pay: number;
    total_salary: number;
    status: 'generated' | 'reviewed' | 'finalized';
    payslip_available: boolean;
};

type Props = AppPageProps<{
    salary_summary: SalarySummary;
    payroll_records: PayrollRecord[];
}>;
type PayrollOrderType = 'recent' | 'oldest';

const statusColor = (status: PayrollRecord['status']) => {
    if (status === 'generated') return 'blue';
    if (status === 'reviewed') return 'gold';
    return 'green';
};

export default function PayrollIndex() {
    const { salary_summary, payroll_records } = usePage<Props>().props;
    const [monthFilter, setMonthFilter] = useState<string>('all');
    const [orderFilter, setOrderFilter] = useState<PayrollOrderType>('recent');
    const monthOptions = useMemo(() => {
        const months = Array.from(new Set(
            payroll_records.map((row) => dayjs(row.pay_period_start).format('YYYY-MM'))
        )).sort((a, b) => b.localeCompare(a));

        return [
            { label: 'All months', value: 'all' as const },
            ...months.map((month) => ({
                label: dayjs(`${month}-01`).format('MMMM YYYY'),
                value: month,
            })),
        ];
    }, [payroll_records]);
    const filteredPayrollRecords = useMemo(() => {
        const monthFiltered = monthFilter === 'all'
            ? payroll_records
            : payroll_records.filter((row) => dayjs(row.pay_period_start).format('YYYY-MM') === monthFilter);

        return [...monthFiltered].sort((a, b) => {
            const aEnd = dayjs(a.pay_period_end).valueOf();
            const bEnd = dayjs(b.pay_period_end).valueOf();
            if (aEnd === bEnd) {
                const aStart = dayjs(a.pay_period_start).valueOf();
                const bStart = dayjs(b.pay_period_start).valueOf();
                return orderFilter === 'recent' ? bStart - aStart : aStart - bStart;
            }

            return orderFilter === 'recent' ? bEnd - aEnd : aEnd - bEnd;
        });
    }, [monthFilter, orderFilter, payroll_records]);

    return (
        <>
            <Head title="My Payroll" />
            <PageHeader
                title="My Payroll"
                actions={(
                    <Space>
                        <Link href={route('dtr.index', { tab: '4' })}>
                            <Button>Open Payroll Generator</Button>
                        </Link>
                    </Space>
                )}
            />
            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <MetricCard
                    label="Salary Type"
                    value={salary_summary.salary_type ? salary_summary.salary_type.toUpperCase() : '-'}
                />
                <MetricCard
                    label="Salary Amount"
                    value={`PHP ${(salary_summary.salary_amount ?? 0).toFixed(2)}`}
                />
                <MetricCard
                    label="Payroll Records"
                    value={payroll_records.length}
                />
            </div>

            <TableCard
                actions={(
                    <Space wrap>
                        <Select
                            style={{ width: 180 }}
                            value={monthFilter}
                            onChange={(value) => setMonthFilter(String(value))}
                            options={monthOptions}
                        />
                        <Select
                            style={{ width: 130 }}
                            value={orderFilter}
                            onChange={(value) => setOrderFilter(value as PayrollOrderType)}
                            options={[
                                { label: 'Recent', value: 'recent' },
                                { label: 'Oldest', value: 'oldest' },
                            ]}
                        />
                    </Space>
                )}
            >
                {filteredPayrollRecords.length === 0 ? (
                    <Empty description="No payroll records yet." />
                ) : (
                    <Table
                        rowKey="id"
                        dataSource={filteredPayrollRecords}
                        pagination={{ pageSize: 15 }}
                        columns={[
                            {
                                title: 'Period',
                                render: (_, row) => `${row.pay_period_start} to ${row.pay_period_end}`,
                            },
                            {
                                title: 'Breakdown',
                                render: (_, row) => (
                                    <span>
                                        Rate PHP {row.salary_amount.toFixed(2)} | Days {row.days_worked.toFixed(2)} | Hours {row.hours_worked.toFixed(2)} | Absences {row.absences} | Undertime {row.undertime_minutes}m | Half-day {row.half_days}
                                    </span>
                                ),
                            },
                            {
                                title: 'Earnings',
                                render: (_, row) => (
                                    <span>
                                        Base PHP {row.base_pay.toFixed(2)} | Paid Leave PHP {row.paid_leave_pay.toFixed(2)} | Holiday Base PHP {row.paid_holiday_base_pay.toFixed(2)} | Holiday Bonus PHP {row.holiday_attendance_bonus.toFixed(2)}
                                    </span>
                                ),
                            },
                            {
                                title: 'Status',
                                render: (_, row) => <Tag color={statusColor(row.status)}>{row.status.toUpperCase()}</Tag>,
                            },
                            {
                                title: 'Net',
                                render: (_, row) => <Tag color="blue">PHP {row.net_pay.toFixed(2)}</Tag>,
                            },
                            {
                                title: 'Payslip',
                                render: (_, row) =>
                                    row.status === 'finalized' ? (
                                        <Space wrap>
                                            <Link href={route('payroll.payslip.view', row.id)}>
                                                <Button size="small">View</Button>
                                            </Link>
                                            <Button
                                                size="small"
                                                onClick={() => {
                                                    window.open(
                                                        route('payroll.payslip.view', {
                                                            payrollRecord: row.id,
                                                            print: 1,
                                                        }),
                                                        '_blank',
                                                        'noopener,noreferrer'
                                                    );
                                                }}
                                            >
                                                Print
                                            </Button>
                                            {row.payslip_available ? (
                                                <a href={route('payroll.payslip', row.id)}>
                                                    <Button size="small">Download</Button>
                                                </a>
                                            ) : (
                                                <Tag>No file</Tag>
                                            )}
                                        </Space>
                                    ) : (
                                        <Tag color="default">Finalize first</Tag>
                                    ),
                            },
                        ]}
                    />
                )}
            </TableCard>
        </>
    );
}
