import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    className = '',
}: {
    className?: string;
}) {
    const user = usePage().props.auth.user;
    const isIntern = user.employee_type === 'intern';

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            student_name: user.student_name ?? '',
            student_no: user.student_no ?? '',
            school: user.school ?? '',
            required_hours:
                user.required_hours !== null && user.required_hours !== undefined
                    ? String(user.required_hours)
                    : '',
            company: user.company ?? '',
            department: user.department ?? '',
            supervisor_name: user.supervisor_name ?? '',
            supervisor_position: user.supervisor_position ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <form onSubmit={submit} className="space-y-7">
                <div>
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Basic Details</h3>
                    <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="name" value="Name" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                isFocused
                                autoComplete="name"
                            />
                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div>
                            <InputLabel htmlFor="student_name" value={isIntern ? 'Student Name' : 'Employee Name'} />
                            <TextInput
                                id="student_name"
                                className="mt-1 block w-full"
                                value={data.student_name}
                                onChange={(e) => setData('student_name', e.target.value)}
                                autoComplete="name"
                            />
                            <InputError className="mt-2" message={errors.student_name} />
                        </div>

                        {isIntern && (
                            <div>
                                <InputLabel htmlFor="student_no" value="Student Number" />
                                <TextInput
                                    id="student_no"
                                    className="mt-1 block w-full"
                                    value={data.student_no}
                                    onChange={(e) => setData('student_no', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.student_no} />
                            </div>
                        )}

                        <div>
                            <InputLabel htmlFor="email" value="Email" />
                            <TextInput
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoComplete="username"
                            />
                            <InputError className="mt-2" message={errors.email} />
                        </div>
                    </div>
                </div>

                <div className="border-t border-slate-200 pt-6">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Work Details</h3>
                    <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {isIntern && (
                            <div>
                                <InputLabel htmlFor="school" value="School" />
                                <TextInput
                                    id="school"
                                    className="mt-1 block w-full"
                                    value={data.school}
                                    onChange={(e) => setData('school', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.school} />
                            </div>
                        )}

                        {isIntern && (
                            <div>
                                <InputLabel htmlFor="required_hours" value="Required Hours" />
                                <TextInput
                                    id="required_hours"
                                    type="number"
                                    className="mt-1 block w-full"
                                    value={data.required_hours}
                                    onChange={(e) => setData('required_hours', e.target.value)}
                                    min={1}
                                />
                                <InputError className="mt-2" message={errors.required_hours} />
                            </div>
                        )}

                        <div>
                            <InputLabel htmlFor="company" value="Company" />
                            <TextInput
                                id="company"
                                className="mt-1 block w-full"
                                value={data.company}
                                onChange={(e) => setData('company', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.company} />
                        </div>

                        <div>
                            <InputLabel htmlFor="department" value="Department" />
                            <TextInput
                                id="department"
                                className="mt-1 block w-full"
                                value={data.department}
                                onChange={(e) => setData('department', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.department} />
                        </div>

                        <div>
                            <InputLabel htmlFor="supervisor_name" value="Supervisor Name" />
                            <TextInput
                                id="supervisor_name"
                                className="mt-1 block w-full"
                                value={data.supervisor_name}
                                onChange={(e) => setData('supervisor_name', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.supervisor_name} />
                        </div>

                        <div>
                            <InputLabel htmlFor="supervisor_position" value="Supervisor Position" />
                            <TextInput
                                id="supervisor_position"
                                className="mt-1 block w-full"
                                value={data.supervisor_position}
                                onChange={(e) => setData('supervisor_position', e.target.value)}
                            />
                            <InputError className="mt-2" message={errors.supervisor_position} />
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-4 border-t border-slate-200 pt-5">
                    <PrimaryButton disabled={processing}>Save Changes</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-slate-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
