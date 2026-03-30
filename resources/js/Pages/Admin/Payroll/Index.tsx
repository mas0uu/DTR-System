import { PageProps as AppPageProps } from '@/types';
import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import UserSearchControl from '@/Components/ui/UserSearchControl';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Alert, Button, Select, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';

type Employee = {
    id: number;
    name: string;
    email: string;
    employee_type: 'intern' | 'regular' | null;
    intern_compensation_enabled: boolean;
    salary_type: 'monthly' | 'daily' | 'hourly' | null;
    salary_amount: number | null;
};

type PayrollRecord = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_email: string;
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
    reviewed_by: string | null;
    reviewed_at: string | null;
    finalized_by: string | null;
    finalized_at: string | null;
    lock_reason: string | null;
    correction_count: number;
    payslip_available: boolean;
};

type Props = AppPageProps<{
    employees: Employee[];
    payroll_records: PayrollRecord[];
    flash?: {
        success?: string;
    };
}>;

type PayrollCutoffType = 'first_15' | 'last_15' | 'whole_month';
type PayrollOrderType = 'recent' | 'oldest';

const resolvePayrollPeriod = (payPeriodMonth: string, cutoffType: PayrollCutoffType): { start: string; end: string } => {
    const monthStart = dayjs(`${payPeriodMonth}-01`).startOf('month');
    if (!monthStart.isValid()) {
        return { start: '', end: '' };
    }

    if (cutoffType === 'first_15') {
        return {
            start: monthStart.format('YYYY-MM-DD'),
            end: monthStart.date(15).format('YYYY-MM-DD'),
        };
    }

    if (cutoffType === 'last_15') {
        return {
            start: monthStart.date(16).format('YYYY-MM-DD'),
            end: monthStart.endOf('month').format('YYYY-MM-DD'),
        };
    }

    return {
        start: monthStart.format('YYYY-MM-DD'),
        end: monthStart.endOf('month').format('YYYY-MM-DD'),
    };
};

export default function AdminPayrollIndex() {
    const page = usePage<Props>();
    const { employees, payroll_records, flash } = page.props;
    const pageErrors = (page.props as unknown as { errors?: Record<string, string> }).errors;
    const defaultMonth = dayjs().format('YYYY-MM');
    const defaultCutoffType: PayrollCutoffType = 'whole_month';
    const defaultRange = resolvePayrollPeriod(defaultMonth, defaultCutoffType);
    const { data, setData, post, processing, errors } = useForm<{
        employee_id: string;
        pay_period_type: PayrollCutoffType;
        pay_period_month: string;
        pay_period_start: string;
        pay_period_end: string;
    }>({
        employee_id: '',
        pay_period_type: defaultCutoffType,
        pay_period_month: defaultMonth,
        pay_period_start: defaultRange.start,
        pay_period_end: defaultRange.end,
    });
    const [orderFilter, setOrderFilter] = useState<PayrollOrderType>('recent');
    const [userSearch, setUserSearch] = useState('');

    useEffect(() => {
        if (data.pay_period_month === 'all') {
            if (data.pay_period_start !== '') {
                setData('pay_period_start', '');
            }
            if (data.pay_period_end !== '') {
                setData('pay_period_end', '');
            }
            return;
        }

        const range = resolvePayrollPeriod(data.pay_period_month, data.pay_period_type);
        if (range.start !== data.pay_period_start) {
            setData('pay_period_start', range.start);
        }
        if (range.end !== data.pay_period_end) {
            setData('pay_period_end', range.end);
        }
    }, [data.pay_period_month, data.pay_period_type]);

    const finalizedCount = payroll_records.filter((row) => row.status === 'finalized').length;
    const reviewedCount = payroll_records.filter((row) => row.status === 'reviewed').length;
    const generatedCount = payroll_records.filter((row) => row.status === 'generated').length;
    const monthOptions = useMemo(() => {
        const months = Array.from(new Set(
            [dayjs().format('YYYY-MM'), ...payroll_records.map((row) => dayjs(row.pay_period_start).format('YYYY-MM'))]
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
        const query = userSearch.trim().toLowerCase();
        const monthFiltered = data.pay_period_month === 'all'
            ? payroll_records
            : payroll_records.filter((row) => dayjs(row.pay_period_start).format('YYYY-MM') === data.pay_period_month);
        const userFiltered = monthFiltered.filter((row) => (
            query === ''
            || row.employee_name.toLowerCase().includes(query)
            || row.employee_email.toLowerCase().includes(query)
        ));

        return [...userFiltered].sort((a, b) => {
            const aEnd = dayjs(a.pay_period_end).valueOf();
            const bEnd = dayjs(b.pay_period_end).valueOf();
            if (aEnd === bEnd) {
                const aStart = dayjs(a.pay_period_start).valueOf();
                const bStart = dayjs(b.pay_period_start).valueOf();
                return orderFilter === 'recent' ? bStart - aStart : aStart - bStart;
            }

            return orderFilter === 'recent' ? bEnd - aEnd : aEnd - bEnd;
        });
    }, [data.pay_period_month, orderFilter, payroll_records, userSearch]);
    const canGeneratePayroll = Boolean(data.employee_id) && data.pay_period_month !== 'all';
    const canGenerateAllPayroll = data.pay_period_month !== 'all';

    const statusColor = (status: PayrollRecord['status']) => {
        if (status === 'generated') return 'blue';
        if (status === 'reviewed') return 'orange';
        return 'green';
    };

    return (
        <>
            <Head title="Payroll Center" />
            <PageHeader
                title="Payroll Center"
                subtitle="Generate, review, and finalize payroll records with lock-safe workflow."
                actions={(
                    <Space>
                        <Link href={route('admin.anomalies.index')}>
                            <Button>Anomalies</Button>
                        </Link>
                        <Link href={route('admin.employees.index')}>
                            <Button>Employees</Button>
                        </Link>
                    </Space>
                )}
            />

            {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}
            {pageErrors?.payroll && <Alert type="error" message={pageErrors.payroll} showIcon className="mb-4" />}

            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <MetricCard label="Total Records" value={payroll_records.length} />
                <MetricCard label="Generated" value={generatedCount} />
                <MetricCard label="Reviewed" value={reviewedCount} />
                <MetricCard label="Finalized" value={finalizedCount} />
            </div>

            <TableCard title="Generate Payroll" className="mb-5">
                <div className="grid gap-3 md:grid-cols-5">
                    <div className="md:col-span-2">
                        <label className="mb-1 block text-sm text-gray-700">Employee</label>
                        <Select
                            className="w-full"
                            placeholder="Select employee"
                            value={data.employee_id || undefined}
                            onChange={(value) => setData('employee_id', String(value))}
                            options={employees.map((employee) => ({
                                label:
                                    employee.employee_type === 'intern' && !employee.intern_compensation_enabled
                                        ? `${employee.name} (${employee.employee_type}) - payroll disabled`
                                        : `${employee.name} (${employee.employee_type})`,
                                value: String(employee.id),
                                disabled: employee.employee_type === 'intern' && !employee.intern_compensation_enabled,
                            }))}
                        />
                        {errors.employee_id && <p className="text-sm text-red-600 mt-1">{errors.employee_id}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Cutoff</label>
                        <Select
                            className="w-full"
                            value={data.pay_period_type}
                            onChange={(value) => setData('pay_period_type', value)}
                            options={[
                                { label: 'First 15', value: 'first_15' },
                                { label: 'Last 15', value: 'last_15' },
                                { label: 'Whole Month', value: 'whole_month' },
                            ]}
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Payroll Month</label>
                        <Select
                            className="w-full"
                            value={data.pay_period_month}
                            onChange={(value) => setData('pay_period_month', String(value))}
                            options={monthOptions}
                        />
                    </div>

                    <div className="flex items-end">
                        <Space direction="vertical" className="w-full">
                            <Button
                                type="primary"
                                loading={processing}
                                disabled={!canGeneratePayroll}
                                onClick={() => post(route('admin.payroll.generate'))}
                                block
                            >
                                Generate Payroll
                            </Button>
                            <Button
                                loading={processing}
                                disabled={!canGenerateAllPayroll}
                                onClick={() => {
                                    const confirmed = window.confirm(
                                        `Generate payroll for all eligible employees from ${data.pay_period_start} to ${data.pay_period_end}?`
                                    );
                                    if (!confirmed) return;
                                    post(route('admin.payroll.generate_all'));
                                }}
                                block
                            >
                                Generate All Employees
                            </Button>
                        </Space>
                    </div>
                </div>
                <div className="mt-3 text-sm text-slate-600">
                    Selected Period:{' '}
                    <span className="font-medium">
                        {data.pay_period_month === 'all'
                            ? 'All months selected'
                            : `${data.pay_period_start} to ${data.pay_period_end}`}
                    </span>
                </div>
                {errors.pay_period_start && <p className="text-sm text-red-600 mt-1">{errors.pay_period_start}</p>}
                {errors.pay_period_end && <p className="text-sm text-red-600 mt-1">{errors.pay_period_end}</p>}
            </TableCard>

            <TableCard
                title="Payroll Records"
                actions={(
                    <Space wrap>
                        <UserSearchControl value={userSearch} onChange={setUserSearch} />
                        <Select
                            style={{ width: 140 }}
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
                <Table
                    className="admin-payroll-records-table"
                    size="small"
                    tableLayout="fixed"
                    rowKey="id"
                    dataSource={filteredPayrollRecords}
                    pagination={{ pageSize: 10 }}
                    columns={[
                        {
                            title: 'Employee',
                            key: 'employee',
                            render: (_, row) => (
                                <div className="payroll-cell-stack">
                                    <span>{row.employee_name}</span>
                                    <span className="payroll-cell-muted">{row.employee_email}</span>
                                </div>
                            ),
                        },
                        {
                            title: 'Period',
                            key: 'period',
                            render: (_, row) => (
                                <div className="payroll-cell-stack">
                                    <span>{row.pay_period_start}</span>
                                    <span className="payroll-cell-muted">to {row.pay_period_end}</span>
                                </div>
                            ),
                        },
                        {
                            title: 'Rate',
                            key: 'rate',
                            responsive: ['lg'],
                            render: (_, row) => (
                                <div className="payroll-cell-stack">
                                    <span>{row.salary_type.toUpperCase()}</span>
                                    <span>PHP {row.salary_amount.toFixed(2)}</span>
                                </div>
                            ),
                        },
                        {
                            title: 'Work Summary',
                            key: 'work_summary',
                            render: (_, row) => (
                                <div className="payroll-cell-stack">
                                    <span>Days: {row.days_worked.toFixed(2)}</span>
                                    <span>Hours: {row.hours_worked.toFixed(2)}</span>
                                    <span>Abs: {row.absences}</span>
                                </div>
                            ),
                        },
                        {
                            title: 'Earnings Breakdown',
                            key: 'earnings_breakdown',
                            responsive: ['xl'],
                            render: (_, row) => (
                                <div className="payroll-cell-stack">
                                    <span>Base: PHP {row.base_pay.toFixed(2)}</span>
                                    <span>Paid Leave: PHP {row.paid_leave_pay.toFixed(2)}</span>
                                    <span>Holiday Base: PHP {row.paid_holiday_base_pay.toFixed(2)}</span>
                                    <span>Holiday Bonus: PHP {row.holiday_attendance_bonus.toFixed(2)}</span>
                                </div>
                            ),
                        },
                        {
                            title: 'Adjustments',
                            key: 'adjustments',
                            responsive: ['xl'],
                            render: (_, row) => (
                                <div className="payroll-tags-stack">
                                    <Tag>Undertime: {row.undertime_minutes}m</Tag>
                                    <Tag>Half-day: {row.half_days}</Tag>
                                    <Tag color="red">Deductions: PHP {row.total_deductions.toFixed(2)}</Tag>
                                </div>
                            ),
                        },
                        {
                            title: 'Status',
                            key: 'status',
                            render: (_, row) => (
                                <Space direction="vertical" size={2} className="payroll-cell-stack">
                                    <Tag color={statusColor(row.status)}>{row.status.toUpperCase()}</Tag>
                                    {row.status === 'reviewed' && (
                                        <span className="payroll-cell-muted">
                                            {row.reviewed_by || 'Unknown'} {row.reviewed_at ? `(${row.reviewed_at})` : ''}
                                        </span>
                                    )}
                                    {row.status === 'finalized' && (
                                        <span className="payroll-cell-muted">
                                            {row.finalized_by || 'Unknown'} {row.finalized_at ? `(${row.finalized_at})` : ''}
                                        </span>
                                    )}
                                </Space>
                            ),
                        },
                        {
                            title: 'Net Pay',
                            key: 'net_pay',
                            render: (_, row) => <Tag color="blue">PHP {row.net_pay.toFixed(2)}</Tag>,
                        },
                        {
                            title: 'Actions',
                            key: 'actions',
                            render: (_, row) => (
                                <Space direction="vertical" size={6} className="payroll-actions">
                                    {row.status === 'generated' && (
                                        <Button
                                            size="small"
                                            className="payroll-review-btn"
                                            onClick={() => router.patch(route('admin.payroll.review', row.id))}
                                        >
                                            Mark Reviewed
                                        </Button>
                                    )}
                                    <Link href={route('admin.payroll.payslip.view', row.id)}>
                                        <Button size="small">View Payroll</Button>
                                    </Link>
                                    <Button
                                        size="small"
                                        onClick={() => {
                                            window.open(
                                                route('admin.payroll.payslip.view', {
                                                    payrollRecord: row.id,
                                                    print: 1,
                                                }),
                                                '_blank',
                                                'noopener,noreferrer'
                                            );
                                        }}
                                    >
                                        Print Payroll
                                    </Button>
                                    {row.status === 'finalized' && row.payslip_available && (
                                        <a href={route('admin.payroll.payslip.download', row.id)}>
                                            <Button size="small">Download</Button>
                                        </a>
                                    )}
                                    {row.status !== 'finalized' && (
                                        <Button
                                            size="small"
                                            danger
                                            onClick={() => {
                                                const confirmed = window.confirm(
                                                    `Delete payroll for ${row.employee_name} (${row.pay_period_start} to ${row.pay_period_end})? This cannot be undone.`
                                                );
                                                if (!confirmed) return;
                                                router.delete(route('admin.payroll.destroy', row.id), { preserveScroll: true });
                                            }}
                                        >
                                            Delete
                                        </Button>
                                    )}
                                </Space>
                            ),
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
