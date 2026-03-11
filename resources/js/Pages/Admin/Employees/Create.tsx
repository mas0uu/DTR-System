import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { PageProps as AppPageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Alert, Button } from 'antd';
import EmployeeForm from './EmployeeForm';

type Props = AppPageProps<{
    flash?: {
        success?: string;
    };
}>;

export default function CreateEmployee() {
    const flash = usePage<Props>().props.flash;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role: '' as '' | 'admin' | 'employee' | 'intern',
        password: '',
        password_confirmation: '',
        student_no: '',
        school: '',
        required_hours: '',
        supervisor_name: '',
        company: 'Boilerplate Test',
        department: '',
        starting_date: '',
        working_days: [1, 2, 3, 4, 5] as number[],
        work_time_in: '09:00',
        work_time_out: '18:00',
        default_break_minutes: 60,
        salary_type: '' as '' | 'monthly' | 'daily' | 'hourly',
        salary_amount: '',
        initial_paid_leave_days: '10',
        current_paid_leave_balance: '10',
        leave_reset_month: 1,
        leave_reset_day: 1,
    });

    return (
        <>
            <Head title="Create User" />
            <PageHeader
                title="Create User Account"
                subtitle="Admin-only account creation form."
                actions={(
                    <Link href={route('admin.employees.index')}>
                        <Button>View Users</Button>
                    </Link>
                )}
            />

            {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}

            <TableCard className="mx-auto w-full max-w-5xl">
                <EmployeeForm
                    data={data}
                    setData={setData as any}
                    errors={errors as Record<string, string>}
                    processing={processing}
                    submitLabel="Create User"
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('admin.employees.store'), {
                            onSuccess: () => {
                                reset();
                                setData('working_days', [1, 2, 3, 4, 5]);
                                setData('work_time_in', '09:00');
                                setData('work_time_out', '18:00');
                                setData('default_break_minutes', 60);
                                setData('company', 'Boilerplate Test');
                            },
                        });
                    }}
                />
            </TableCard>
        </>
    );
}
