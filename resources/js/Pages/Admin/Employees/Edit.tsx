import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from 'antd';
import EmployeeForm from './EmployeeForm';

type Employee = {
    id: number;
    student_name: string;
    student_no: string | null;
    email: string;
    school: string | null;
    required_hours: number | null;
    company: string | null;
    department: string | null;
    supervisor_name: string | null;
    supervisor_position: string | null;
    employee_type: 'intern' | 'regular' | null;
    intern_compensation_enabled: boolean;
    starting_date: string | null;
    working_days: number[] | null;
    work_time_in: string | null;
    work_time_out: string | null;
    default_break_minutes: number | null;
    salary_type: 'monthly' | 'daily' | 'hourly' | null;
    salary_amount: number | null;
    initial_paid_leave_days: number | null;
    current_paid_leave_balance: number | null;
    leave_reset_month: number | null;
    leave_reset_day: number | null;
};

export default function EditEmployee({ employee }: { employee: Employee }) {
    const { data, setData, patch, processing, errors } = useForm({
        student_name: employee.student_name || '',
        student_no: employee.student_no || '',
        email: employee.email || '',
        password: '',
        password_confirmation: '',
        school: employee.school || '',
        required_hours: employee.required_hours ?? '',
        company: employee.company || '',
        department: employee.department || '',
        supervisor_name: employee.supervisor_name || '',
        supervisor_position: employee.supervisor_position || '',
        employee_type: (employee.employee_type || '') as '' | 'intern' | 'regular',
        intern_compensation_enabled: !!employee.intern_compensation_enabled,
        starting_date: employee.starting_date || '',
        working_days: employee.working_days || [1, 2, 3, 4, 5],
        work_time_in: employee.work_time_in || '09:00',
        work_time_out: employee.work_time_out || '18:00',
        default_break_minutes: employee.default_break_minutes ?? 60,
        salary_type: (employee.salary_type || '') as '' | 'monthly' | 'daily' | 'hourly',
        salary_amount: employee.salary_amount ?? '',
        initial_paid_leave_days: employee.initial_paid_leave_days ?? '0',
        current_paid_leave_balance: employee.current_paid_leave_balance ?? '0',
        leave_reset_month: employee.leave_reset_month ?? 1,
        leave_reset_day: employee.leave_reset_day ?? 1,
    });

    return (
        <>
            <Head title="Edit Employee" />
            <PageHeader
                title="Edit Employee"
                subtitle={employee.email}
                actions={(
                    <Link href={route('admin.employees.index')}>
                        <Button>Back to Employees</Button>
                    </Link>
                )}
            />

            <TableCard className="mx-auto w-full max-w-5xl">
                <EmployeeForm
                    data={data}
                    setData={setData as any}
                    errors={errors as Record<string, string>}
                    processing={processing}
                    submitLabel="Save Changes"
                    passwordOptional
                    onSubmit={(e) => {
                        e.preventDefault();
                        patch(route('admin.employees.update', employee.id));
                    }}
                />
            </TableCard>
        </>
    );
}
