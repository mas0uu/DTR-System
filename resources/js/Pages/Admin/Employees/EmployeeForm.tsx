import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Col, Divider, Row, Select, Switch, TimePicker } from 'antd';
import dayjs from 'dayjs';

type EmployeeFormData = {
    name: string;
    email: string;
    role: '' | 'admin' | 'employee' | 'intern';
    intern_compensation_enabled: boolean;
    password: string;
    password_confirmation: string;
    student_no: string;
    school: string;
    required_hours: string | number;
    supervisor_name: string;
    company: string;
    department: string;
    starting_date: string;
    working_days: number[];
    work_time_in: string;
    work_time_out: string;
    default_break_minutes: number;
    salary_type: '' | 'monthly' | 'daily' | 'hourly';
    salary_amount: string | number;
    initial_paid_leave_days: string | number;
    current_paid_leave_balance: string | number;
    leave_reset_month: number;
    leave_reset_day: number;
};

type Props = {
    data: EmployeeFormData;
    setData: (key: keyof EmployeeFormData, value: any) => void;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    submitLabel: string;
    passwordOptional?: boolean;
};

export default function EmployeeForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
    passwordOptional = false,
}: Props) {
    const workingDayOptions = [
        { label: 'Monday', value: 1 },
        { label: 'Tuesday', value: 2 },
        { label: 'Wednesday', value: 3 },
        { label: 'Thursday', value: 4 },
        { label: 'Friday', value: 5 },
        { label: 'Saturday', value: 6 },
        { label: 'Sunday', value: 0 },
    ];
    const isAdmin = data.role === 'admin';
    const isEmployee = data.role === 'employee';
    const isIntern = data.role === 'intern';
    const hasSelectedRole = isAdmin || isEmployee || isIntern;

    return (
        <form onSubmit={onSubmit}>
            <div className="mb-6">
                <h3 className="mb-4 text-lg font-semibold text-gray-700">Account Information</h3>
                <Row gutter={16}>
                    <Col xs={24} sm={12}>
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput
                            id="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </Col>
                    <Col xs={24} sm={12}>
                        <InputLabel htmlFor="email" value="Email Address" />
                        <TextInput
                            id="email"
                            type="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </Col>
                </Row>
                <Row gutter={16} className="mt-4">
                    <Col xs={24} sm={12}>
                        <InputLabel htmlFor="role" value="Role / Account Type" />
                        <Select
                            id="role"
                            className="mt-1 block w-full"
                            value={data.role}
                            onChange={(value: EmployeeFormData['role']) => {
                                setData('role', value);
                                if (value === 'admin') {
                                    setData('salary_type', '');
                                    setData('salary_amount', '');
                                    setData('initial_paid_leave_days', '0');
                                    setData('current_paid_leave_balance', '0');
                                    setData('department', '');
                                    setData('student_no', '');
                                    setData('school', '');
                                    setData('required_hours', '');
                                    setData('supervisor_name', '');
                                    setData('intern_compensation_enabled', false);
                                } else if (value === 'employee') {
                                    setData('intern_compensation_enabled', true);
                                } else if (value === 'intern') {
                                    setData('intern_compensation_enabled', false);
                                }
                            }}
                            options={[
                                { label: 'Admin', value: 'admin' },
                                { label: 'Regular Employee', value: 'employee' },
                                { label: 'Intern', value: 'intern' },
                            ]}
                        />
                        <InputError message={errors.role} className="mt-2" />
                    </Col>
                </Row>
            </div>

            <Divider />
            <div className="mb-6">
                <h3 className="mb-4 text-lg font-semibold text-gray-700">Login Credentials</h3>
                <Row gutter={16}>
                    <Col xs={24} sm={12}>
                        <InputLabel htmlFor="password" value={passwordOptional ? 'Password (Optional)' : 'Password'} />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('password', e.target.value)}
                            required={!passwordOptional}
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </Col>
                    <Col xs={24} sm={12}>
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required={!passwordOptional}
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </Col>
                </Row>
            </div>

            <Divider />
            <div className="mb-6">
                <h3 className="mb-4 text-lg font-semibold text-gray-700">Role-Specific Information</h3>

                {isAdmin && (
                    <p className="text-sm text-gray-600">
                        Admin accounts use only account and login details. Payroll and leave balances are not initialized.
                    </p>
                )}

                {isEmployee && (
                    <>
                        <Row gutter={16}>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="department" value="Department" />
                                <TextInput
                                    id="department"
                                    value={data.department}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('department', e.target.value)}
                                    required={isEmployee}
                                />
                                <InputError message={errors.department} className="mt-2" />
                            </Col>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="salary_type" value="Salary Type" />
                                <Select
                                    id="salary_type"
                                    className="mt-1 block w-full"
                                    value={data.salary_type || undefined}
                                    onChange={(value) => setData('salary_type', value)}
                                    options={[
                                        { label: 'Monthly Salary', value: 'monthly' },
                                        { label: 'Daily Salary', value: 'daily' },
                                        { label: 'Hourly Salary', value: 'hourly' },
                                    ]}
                                />
                                <InputError message={errors.salary_type} className="mt-2" />
                            </Col>
                        </Row>
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="salary_amount" value="Salary Amount" />
                                <TextInput
                                    id="salary_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={String(data.salary_amount)}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('salary_amount', e.target.value)}
                                    required={isEmployee}
                                />
                                <InputError message={errors.salary_amount} className="mt-2" />
                            </Col>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="initial_paid_leave_days" value="Initial Paid Leave Days" />
                                <TextInput
                                    id="initial_paid_leave_days"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={String(data.initial_paid_leave_days)}
                                    className="mt-1 block w-full"
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        setData('initial_paid_leave_days', value);
                                        setData('current_paid_leave_balance', value);
                                    }}
                                    required={isEmployee}
                                />
                                <InputError message={errors.initial_paid_leave_days} className="mt-2" />
                            </Col>
                        </Row>
                    </>
                )}

                {isIntern && (
                    <>
                        <Row gutter={16}>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="school" value="School" />
                                <TextInput
                                    id="school"
                                    value={data.school}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('school', e.target.value)}
                                    required={isIntern}
                                />
                                <InputError message={errors.school} className="mt-2" />
                            </Col>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="student_no" value="Student Number" />
                                <TextInput
                                    id="student_no"
                                    value={data.student_no}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('student_no', e.target.value)}
                                    required={isIntern}
                                />
                                <InputError message={errors.student_no} className="mt-2" />
                            </Col>
                        </Row>
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="required_hours" value="Required Hours" />
                                <TextInput
                                    id="required_hours"
                                    type="number"
                                    min="0"
                                    value={String(data.required_hours)}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('required_hours', e.target.value)}
                                    required={isIntern}
                                />
                                <InputError message={errors.required_hours} className="mt-2" />
                            </Col>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="supervisor_name" value="Supervisor Name (Optional)" />
                                <TextInput
                                    id="supervisor_name"
                                    value={data.supervisor_name}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('supervisor_name', e.target.value)}
                                />
                                <InputError message={errors.supervisor_name} className="mt-2" />
                            </Col>
                        </Row>
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="intern_compensation_enabled" value="Intern Compensation Enabled" />
                                <div className="mt-2">
                                    <Switch
                                        id="intern_compensation_enabled"
                                        checked={data.intern_compensation_enabled}
                                        onChange={(value) => setData('intern_compensation_enabled', value)}
                                    />
                                </div>
                                <InputError message={errors.intern_compensation_enabled} className="mt-2" />
                            </Col>
                        </Row>
                    </>
                )}
            </div>

            {!isAdmin && hasSelectedRole && (
                <>
                    <Divider />
                    <div className="mb-6">
                        <h3 className="mb-4 text-lg font-semibold text-gray-700">Attendance Setup</h3>
                        <Row gutter={16}>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="starting_date" value="Starting Date" />
                                <TextInput
                                    id="starting_date"
                                    type="date"
                                    value={data.starting_date}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('starting_date', e.target.value)}
                                    required
                                />
                                <InputError message={errors.starting_date} className="mt-2" />
                            </Col>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="working_days" value="Working Days" />
                                <Select
                                    mode="multiple"
                                    id="working_days"
                                    className="mt-1 block w-full"
                                    value={data.working_days}
                                    onChange={(value) => setData('working_days', value)}
                                    options={workingDayOptions}
                                />
                                <InputError message={errors.working_days} className="mt-2" />
                            </Col>
                        </Row>
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="work_time_in" value="Work Time In" />
                                <TimePicker
                                    id="work_time_in"
                                    className="mt-1 block w-full"
                                    use12Hours
                                    format="h:mm A"
                                    value={data.work_time_in ? dayjs(data.work_time_in, 'HH:mm') : null}
                                    onChange={(value) => setData('work_time_in', value ? value.format('HH:mm') : '')}
                                />
                                <InputError message={errors.work_time_in} className="mt-2" />
                            </Col>
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="work_time_out" value="Work Time Out" />
                                <TimePicker
                                    id="work_time_out"
                                    className="mt-1 block w-full"
                                    use12Hours
                                    format="h:mm A"
                                    value={data.work_time_out ? dayjs(data.work_time_out, 'HH:mm') : null}
                                    onChange={(value) => setData('work_time_out', value ? value.format('HH:mm') : '')}
                                />
                                <InputError message={errors.work_time_out} className="mt-2" />
                            </Col>
                        </Row>
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <InputLabel htmlFor="default_break_minutes" value="Default Break Minutes" />
                                <Select
                                    id="default_break_minutes"
                                    className="mt-1 block w-full"
                                    value={data.default_break_minutes}
                                    onChange={(value) => setData('default_break_minutes', value)}
                                    options={[
                                        { label: '5 mins', value: 5 },
                                        { label: '10 mins', value: 10 },
                                        { label: '15 mins', value: 15 },
                                        { label: '30 mins', value: 30 },
                                        { label: '45 mins', value: 45 },
                                        { label: '60 mins', value: 60 },
                                    ]}
                                />
                                <InputError message={errors.default_break_minutes} className="mt-2" />
                            </Col>
                        </Row>
                    </div>
                </>
            )}

            <div className="mt-6 flex items-center justify-end">
                <PrimaryButton disabled={processing || !hasSelectedRole}>
                    {processing ? 'Saving...' : submitLabel}
                </PrimaryButton>
            </div>
        </form>
    );
}
