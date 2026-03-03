import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card, Divider, Row, Col, Select, TimePicker } from 'antd';
import dayjs from 'dayjs';

export default function Register() {
    const workingDayOptions = [
        { label: 'Monday', value: 1 },
        { label: 'Tuesday', value: 2 },
        { label: 'Wednesday', value: 3 },
        { label: 'Thursday', value: 4 },
        { label: 'Friday', value: 5 },
        { label: 'Saturday', value: 6 },
        { label: 'Sunday', value: 0 },
    ];

    const { data, setData, post, processing, errors, reset } = useForm({
        student_name: '',
        student_no: '',
        email: '',
        password: '',
        password_confirmation: '',
        school: '',
        required_hours: '',
        company: '',
        department: '',
        supervisor_name: '',
        supervisor_position: '',
        employee_type: '',
        starting_date: '',
        working_days: [1, 2, 3, 4, 5] as number[],
        work_time_in: '09:00',
        work_time_out: '18:00',
    });
    const isIntern = data.employee_type === 'intern';
    const hasSelectedType = data.employee_type === 'intern' || data.employee_type === 'regular';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <Card className="w-full max-w-4xl mx-auto">
                <div className="mb-6">
                    <h2 className="text-2xl font-bold text-center text-gray-800">
                        DTR System Registration
                    </h2>
                    <p className="text-center text-gray-600 mt-2">
                        Create your Daily Time Record account
                    </p>
                </div>

                <form onSubmit={submit}>
                    {/* Employment Setup Section */}
                    <div className="mb-6">
                        <h3 className="text-lg font-semibold mb-4 text-gray-700">
                            User Type
                        </h3>
                        <Row gutter={16}>
                            <Col xs={24} sm={24}>
                                <div>
                                    <InputLabel htmlFor="employee_type" value="Employee Type" />
                                    <Select
                                        id="employee_type"
                                        className="mt-1 block w-full"
                                        value={data.employee_type}
                                        onChange={(value) => setData('employee_type', value)}
                                        placeholder="Select employee type first"
                                        options={[
                                            { label: 'Intern', value: 'intern' },
                                            { label: 'Regular Employee', value: 'regular' },
                                        ]}
                                    />
                                    <InputError message={errors.employee_type} className="mt-2" />
                                </div>
                            </Col>
                        </Row>
                    </div>

                    {!hasSelectedType && (
                        <p className="mb-6 text-gray-600">
                            Select a user type to continue with the required registration details.
                        </p>
                    )}

                    {hasSelectedType && (
                        <>
                            <Divider />

                            {/* Basic Information Section */}
                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-4 text-gray-700">
                                    Basic Information
                                </h3>
                                <Row gutter={16}>
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="student_name" value={isIntern ? 'Student Name' : 'Full Name'} />
                                            <TextInput
                                                id="student_name"
                                                name="student_name"
                                                value={data.student_name}
                                                className="mt-1 block w-full"
                                                autoComplete="name"
                                                isFocused={true}
                                                onChange={(e) => setData('student_name', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.student_name} className="mt-2" />
                                        </div>
                                    </Col>
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="email" value="Email Address" />
                                            <TextInput
                                                id="email"
                                                type="email"
                                                name="email"
                                                value={data.email}
                                                className="mt-1 block w-full"
                                                autoComplete="email"
                                                onChange={(e) => setData('email', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.email} className="mt-2" />
                                        </div>
                                    </Col>
                                </Row>
                            </div>

                            {isIntern && (
                                <>
                                    <Divider />
                                    <div className="mb-6">
                                        <h3 className="text-lg font-semibold mb-4 text-gray-700">
                                            Academic Information
                                        </h3>
                                        <Row gutter={16}>
                                            <Col xs={24} sm={12}>
                                                <div>
                                                    <InputLabel htmlFor="student_no" value="Student Number" />
                                                    <TextInput
                                                        id="student_no"
                                                        name="student_no"
                                                        value={data.student_no}
                                                        className="mt-1 block w-full"
                                                        autoComplete="username"
                                                        onChange={(e) => setData('student_no', e.target.value)}
                                                        required={isIntern}
                                                    />
                                                    <InputError message={errors.student_no} className="mt-2" />
                                                </div>
                                            </Col>
                                            <Col xs={24} sm={12}>
                                                <div>
                                                    <InputLabel htmlFor="school" value="School / University" />
                                                    <TextInput
                                                        id="school"
                                                        name="school"
                                                        value={data.school}
                                                        className="mt-1 block w-full"
                                                        onChange={(e) => setData('school', e.target.value)}
                                                        required={isIntern}
                                                    />
                                                    <InputError message={errors.school} className="mt-2" />
                                                </div>
                                            </Col>
                                        </Row>

                                        <Row gutter={16} className="mt-4">
                                            <Col xs={24} sm={12}>
                                                <div>
                                                    <InputLabel htmlFor="required_hours" value="Required Internship Hours" />
                                                    <TextInput
                                                        id="required_hours"
                                                        type="number"
                                                        name="required_hours"
                                                        value={data.required_hours}
                                                        className="mt-1 block w-full"
                                                        onChange={(e) => setData('required_hours', e.target.value)}
                                                        required={isIntern}
                                                    />
                                                    <InputError message={errors.required_hours} className="mt-2" />
                                                </div>
                                            </Col>
                                        </Row>
                                    </div>
                                </>
                            )}

                            <Divider />

                            {/* Work Information Section */}
                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-4 text-gray-700">
                                    Work Information
                                </h3>
                                <Row gutter={16}>
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="company" value="Company" />
                                            <TextInput
                                                id="company"
                                                name="company"
                                                value={data.company}
                                                className="mt-1 block w-full"
                                                onChange={(e) => setData('company', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.company} className="mt-2" />
                                        </div>
                                    </Col>
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="department" value="Department" />
                                            <TextInput
                                                id="department"
                                                name="department"
                                                value={data.department}
                                                className="mt-1 block w-full"
                                                onChange={(e) => setData('department', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.department} className="mt-2" />
                                        </div>
                                    </Col>
                                </Row>

                                <Row gutter={16} className="mt-4">
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="supervisor_name" value="Supervisor Name" />
                                            <TextInput
                                                id="supervisor_name"
                                                name="supervisor_name"
                                                value={data.supervisor_name}
                                                className="mt-1 block w-full"
                                                onChange={(e) => setData('supervisor_name', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.supervisor_name} className="mt-2" />
                                        </div>
                                    </Col>
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="supervisor_position" value="Supervisor Position" />
                                            <TextInput
                                                id="supervisor_position"
                                                name="supervisor_position"
                                                value={data.supervisor_position}
                                                className="mt-1 block w-full"
                                                onChange={(e) => setData('supervisor_position', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.supervisor_position} className="mt-2" />
                                        </div>
                                    </Col>
                                </Row>
                            </div>

                            <Divider />

                            {/* Employment Setup Section */}
                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-4 text-gray-700">
                                    DTR Setup
                                </h3>
                                <Row gutter={16}>
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="starting_date" value="Starting Date (First Day of Work)" />
                                            <TextInput
                                                id="starting_date"
                                                type="date"
                                                name="starting_date"
                                                value={data.starting_date}
                                                className="mt-1 block w-full"
                                                onChange={(e) => setData('starting_date', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.starting_date} className="mt-2" />
                                        </div>
                                    </Col>
                                    <Col xs={24} sm={12}>
                                        <div>
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
                                        </div>
                                    </Col>
                                </Row>

                                <Row gutter={16} className="mt-4">
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="work_time_in" value="Work Time In" />
                                            <TimePicker
                                                id="work_time_in"
                                                className="mt-1 block w-full"
                                                use12Hours
                                                format="h:mm A"
                                                value={data.work_time_in ? dayjs(data.work_time_in, 'HH:mm') : null}
                                                onChange={(value) =>
                                                    setData('work_time_in', value ? value.format('HH:mm') : '')
                                                }
                                            />
                                            <InputError message={errors.work_time_in} className="mt-2" />
                                        </div>
                                    </Col>
                                    <Col xs={24} sm={12}>
                                        <div>
                                            <InputLabel htmlFor="work_time_out" value="Work Time Out" />
                                            <TimePicker
                                                id="work_time_out"
                                                className="mt-1 block w-full"
                                                use12Hours
                                                format="h:mm A"
                                                value={data.work_time_out ? dayjs(data.work_time_out, 'HH:mm') : null}
                                                onChange={(value) =>
                                                    setData('work_time_out', value ? value.format('HH:mm') : '')
                                                }
                                            />
                                            <InputError message={errors.work_time_out} className="mt-2" />
                                        </div>
                                    </Col>
                                </Row>
                            </div>

                            <Divider />
                        </>
                    )}

                    {/* Security Section */}
                    <div className="mb-6">
                        <h3 className="text-lg font-semibold mb-4 text-gray-700">
                            Security
                        </h3>
                        <Row gutter={16}>
                            <Col xs={24} sm={12}>
                                <div>
                                    <InputLabel htmlFor="password" value="Password" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        name="password"
                                        value={data.password}
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        onChange={(e) => setData('password', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.password} className="mt-2" />
                                </div>
                            </Col>
                            <Col xs={24} sm={12}>
                                <div>
                                    <InputLabel
                                        htmlFor="password_confirmation"
                                        value="Confirm Password"
                                    />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        value={data.password_confirmation}
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        onChange={(e) =>
                                            setData('password_confirmation', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                        className="mt-2"
                                    />
                                </div>
                            </Col>
                        </Row>
                    </div>

                    <Divider />

                    <div className="mt-6 flex items-center justify-between">
                        <Link
                            href={route('login')}
                            className="rounded-md text-sm text-blue-600 hover:text-blue-800 font-semibold"
                        >
                            Already registered?
                        </Link>

                        <PrimaryButton disabled={processing || !hasSelectedType}>
                            {processing ? 'Registering...' : 'Register'}
                        </PrimaryButton>
                    </div>
                </form>
            </Card>
        </GuestLayout>
    );
}
