import { PageProps as AppPageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Divider, Space, Tag } from 'antd';
import { ReactNode, useEffect } from 'react';

type Props = AppPageProps<{
    role: 'employee' | 'admin';
    auto_print: boolean;
    payslip: {
        id: number;
        status: 'generated' | 'reviewed' | 'finalized';
        pay_period_start: string;
        pay_period_end: string;
        pay_date: string | null;
        employee: {
            name: string;
            employee_id: string;
            designation: string;
            company: string;
        };
        payroll: {
            salary_type: string;
            salary_amount: number;
            days_worked: number;
            hours_worked: number;
            absences: number;
            undertime_minutes: number;
            half_days: number;
        };
        earnings: {
            basic_pay: number;
            paid_leave_pay: number | null;
            paid_holiday_base_pay: number | null;
            holiday_attendance_bonus: number | null;
            overtime_pay: number | null;
            total_earnings: number;
        };
        deductions: {
            leave_deductions: number | null;
            other_deductions: number | null;
            total_deductions: number;
        };
        summary: {
            net_pay: number;
        };
        download_url: string | null;
        is_read_only: boolean;
        finalized_at: string | null;
    };
}>;

const formatCurrency = (amount: number | null | undefined) =>
    `PHP ${(amount ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatMoneyOrNA = (amount: number | null | undefined) => (amount === null || amount === undefined ? 'N/A' : formatCurrency(amount));

export default function PayslipShow() {
    const { role, auto_print, payslip } = usePage<Props>().props;

    useEffect(() => {
        if (!auto_print) {
            return;
        }

        const timer = window.setTimeout(() => window.print(), 180);
        return () => window.clearTimeout(timer);
    }, [auto_print]);

    return (
        <>
            <Head title={`Payslip #${payslip.id}`} />

            <style>{`
                @page {
                    size: A4;
                    margin: 14mm;
                }
                html.theme-dark .payslip-page {
                    color: #0f172a !important;
                }
                html.theme-dark .payslip-page .bg-white {
                    background: #ffffff !important;
                }
                html.theme-dark .payslip-page .bg-slate-100 {
                    background: #f1f5f9 !important;
                }
                html.theme-dark .payslip-page .bg-slate-50 {
                    background: #f8fafc !important;
                }
                html.theme-dark .payslip-page .border-slate-200 {
                    border-color: #e2e8f0 !important;
                }
                html.theme-dark .payslip-page .border-slate-900 {
                    border-color: #0f172a !important;
                }
                html.theme-dark .payslip-page .text-slate-900,
                html.theme-dark .payslip-page .text-slate-800,
                html.theme-dark .payslip-page .text-slate-700,
                html.theme-dark .payslip-page .text-slate-600,
                html.theme-dark .payslip-page .text-slate-500 {
                    color: #0f172a !important;
                }
                html.theme-dark .payslip-page .text-xs,
                html.theme-dark .payslip-page .text-sm,
                html.theme-dark .payslip-page .text-base {
                    color: #0f172a !important;
                }
                html.theme-dark .payslip-page .ant-btn:not(.ant-btn-primary) {
                    background: #ffffff !important;
                    border-color: #cbd5e1 !important;
                    color: #0f172a !important;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    body {
                        background: #fff !important;
                    }
                    .payslip-sheet {
                        border: 0 !important;
                        box-shadow: none !important;
                        margin: 0 !important;
                        max-width: none !important;
                    }
                }
            `}</style>

            <div className="payslip-page min-h-screen bg-slate-100 p-4 sm:p-6">
                <div className="no-print mx-auto mb-4 flex w-full max-w-4xl items-center justify-between rounded-md bg-white px-4 py-3 shadow-sm">
                    <Space wrap>
                        <Link href={role === 'admin' ? route('admin.payroll.index') : route('payroll.index')}>
                            <Button>Back to Payroll</Button>
                        </Link>
                        <Button type="primary" onClick={() => window.print()}>Print Payslip</Button>
                        {payslip.download_url && (
                            <a href={payslip.download_url}>
                                <Button>Download File</Button>
                            </a>
                        )}
                    </Space>
                    <Tag color="green">READ ONLY</Tag>
                </div>

                <div className="payslip-sheet mx-auto w-full max-w-4xl rounded-md border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div className="mb-5 flex items-start justify-between gap-4">
                        <div className="flex items-start gap-4">
                            <img
                                src="/images/doxsys-logo-full.png"
                                alt="Doxsys"
                                className="h-12 w-auto object-contain print:h-10"
                            />
                            <div>
                                <h1 className="m-0 text-2xl font-bold text-slate-900">{payslip.employee.company}</h1>
                                <p className="m-0 text-sm text-slate-500">Official Payroll Payslip</p>
                            </div>
                        </div>
                        <div className="text-right text-sm text-slate-600">
                            <div>Payslip No: #{payslip.id}</div>
                            <div>Status: {payslip.status.toUpperCase()}</div>
                            <div>Pay Date: {payslip.pay_date || '-'}</div>
                        </div>
                    </div>

                    <Divider className="!my-4" />

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="rounded border border-slate-200 p-3">
                            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Employee Information</div>
                            <div className="text-sm"><span className="text-slate-500">Name:</span> {payslip.employee.name}</div>
                            <div className="text-sm"><span className="text-slate-500">Employee ID:</span> {payslip.employee.employee_id}</div>
                            <div className="text-sm"><span className="text-slate-500">Department / Type:</span> {payslip.employee.designation}</div>
                        </div>
                        <div className="rounded border border-slate-200 p-3">
                            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Payroll Information</div>
                            <div className="text-sm"><span className="text-slate-500">Payroll Period:</span> {payslip.pay_period_start} to {payslip.pay_period_end}</div>
                            <div className="text-sm"><span className="text-slate-500">Salary Type:</span> {payslip.payroll.salary_type.toUpperCase()}</div>
                            <div className="text-sm"><span className="text-slate-500">Base Rate:</span> {formatCurrency(payslip.payroll.salary_amount)}</div>
                            <div className="text-sm"><span className="text-slate-500">Work Summary:</span> {payslip.payroll.days_worked.toFixed(2)} days | {payslip.payroll.hours_worked.toFixed(2)} hrs</div>
                        </div>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="rounded border border-slate-200 p-3">
                            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Earnings</div>
                            <div className="flex justify-between text-sm"><span>Basic Pay</span><span>{formatCurrency(payslip.earnings.basic_pay)}</span></div>
                            <div className="flex justify-between text-sm"><span>Paid Leave Pay</span><span>{formatMoneyOrNA(payslip.earnings.paid_leave_pay)}</span></div>
                            <div className="flex justify-between text-sm"><span>Paid Holiday Base Pay</span><span>{formatMoneyOrNA(payslip.earnings.paid_holiday_base_pay)}</span></div>
                            <div className="flex justify-between text-sm"><span>Holiday Attendance Bonus</span><span>{formatMoneyOrNA(payslip.earnings.holiday_attendance_bonus)}</span></div>
                            <div className="flex justify-between text-sm"><span>Overtime Pay</span><span>{formatMoneyOrNA(payslip.earnings.overtime_pay)}</span></div>
                            <Divider className="!my-2" />
                            <div className="flex justify-between text-sm font-semibold"><span>Total Earnings</span><span>{formatCurrency(payslip.earnings.total_earnings)}</span></div>
                        </div>
                        <div className="rounded border border-slate-200 p-3">
                            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Deductions</div>
                            <div className="flex justify-between text-sm"><span>Leave Deductions</span><span>{formatMoneyOrNA(payslip.deductions.leave_deductions)}</span></div>
                            <div className="flex justify-between text-sm"><span>Other Deductions</span><span>{formatMoneyOrNA(payslip.deductions.other_deductions)}</span></div>
                            <Divider className="!my-2" />
                            <div className="flex justify-between text-sm font-semibold"><span>Total Deductions</span><span>{formatCurrency(payslip.deductions.total_deductions)}</span></div>
                        </div>
                    </div>

                    <div className="mt-4 rounded border border-slate-900 bg-slate-50 p-4">
                        <div className="flex items-center justify-between text-base font-bold">
                            <span>Net Pay</span>
                            <span>{formatCurrency(payslip.summary.net_pay)}</span>
                        </div>
                    </div>

                    <div className="mt-4 text-xs text-slate-500">
                        <div>Attendance Adjustments: absences {payslip.payroll.absences}, undertime {payslip.payroll.undertime_minutes} mins, half-day {payslip.payroll.half_days}.</div>
                        <div>Paid leave and paid holiday components are computed from approved leave and holiday settings.</div>
                        {payslip.finalized_at && <div>Finalized At: {payslip.finalized_at}</div>}
                    </div>
                </div>
            </div>
        </>
    );
}

(PayslipShow as unknown as { layout?: (page: ReactNode) => ReactNode }).layout = (page: ReactNode) => page;
