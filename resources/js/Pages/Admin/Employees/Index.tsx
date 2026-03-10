import { PageProps as AppPageProps } from '@/types';
import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Alert, Button, Dropdown, Popconfirm, Select, Space, Table, Tag } from 'antd';
import type { MenuProps } from 'antd';
import { useMemo, useState } from 'react';

type Employee = {
    id: number;
    name: string;
    email: string;
    employee_type: 'intern' | 'regular' | null;
    intern_compensation_enabled: boolean;
    department: string | null;
    company: string | null;
    salary_type: 'monthly' | 'daily' | 'hourly' | null;
    salary_amount: number | null;
    starting_date: string | null;
    employment_status: 'active' | 'inactive' | 'archived';
    deactivated_at: string | null;
    archived_at: string | null;
    status_reason: string | null;
};

type Props = AppPageProps<{
    employees: Employee[];
    flash?: {
        success?: string;
    };
}>;

export default function EmployeesIndex() {
    const { employees, flash } = usePage<Props>().props;
    const [roleFilter, setRoleFilter] = useState<'all' | 'regular' | 'intern'>('all');

    const filteredEmployees = useMemo(
        () => (roleFilter === 'all' ? employees : employees.filter((employee) => employee.employee_type === roleFilter)),
        [employees, roleFilter],
    );
    const activeCount = useMemo(
        () => employees.filter((employee) => employee.employment_status === 'active').length,
        [employees],
    );
    const inactiveCount = useMemo(
        () => employees.filter((employee) => employee.employment_status === 'inactive').length,
        [employees],
    );
    const archivedCount = useMemo(
        () => employees.filter((employee) => employee.employment_status === 'archived').length,
        [employees],
    );

    return (
        <>
            <Head title="Employees" />
            <PageHeader
                title="Employees"
                subtitle="Manage employee lifecycle, compensation setup, and profile records."
                actions={(
                    <Space>
                        <Link href={route('admin.payroll.index')}>
                            <Button>Payroll Center</Button>
                        </Link>
                        <Link href={route('admin.leaves.index')}>
                            <Button>Leave Queue</Button>
                        </Link>
                        <Link href={route('admin.employees.create')}>
                            <Button type="primary">Create Employee</Button>
                        </Link>
                    </Space>
                )}
            />

            {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}

            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <MetricCard label="Total Employees" value={employees.length} />
                <MetricCard label="Active" value={activeCount} />
                <MetricCard label="Inactive" value={inactiveCount} />
                <MetricCard label="Archived" value={archivedCount} />
            </div>

            <TableCard
                title="Employee Directory"
                actions={(
                    <Select
                        value={roleFilter}
                        style={{ width: 190 }}
                        onChange={(value) => setRoleFilter(value)}
                        options={[
                            { label: 'All roles', value: 'all' },
                            { label: 'Regular', value: 'regular' },
                            { label: 'Intern', value: 'intern' },
                        ]}
                    />
                )}
            >
                <Table
                    rowKey="id"
                    dataSource={filteredEmployees}
                    pagination={{ pageSize: 10 }}
                    tableLayout="fixed"
                    columns={[
                        { title: 'Name', dataIndex: 'name', key: 'name', ellipsis: true },
                        {
                            title: 'Type',
                            key: 'employee_type',
                            render: (_, row) => (
                                <Tag color={row.employee_type === 'intern' ? 'gold' : 'blue'}>
                                    {(row.employee_type || '-').toUpperCase()}
                                </Tag>
                            ),
                        },
                        {
                            title: 'Status',
                            key: 'employment_status',
                            render: (_, row) => (
                                <Tag
                                    color={
                                        row.employment_status === 'active'
                                            ? 'green'
                                            : row.employment_status === 'inactive'
                                                ? 'orange'
                                                : 'red'
                                    }
                                >
                                    {row.employment_status.toUpperCase()}
                                </Tag>
                            ),
                        },
                        { title: 'Department', dataIndex: 'department', key: 'department', ellipsis: true },
                        { title: 'Company', dataIndex: 'company', key: 'company', ellipsis: true },
                        {
                            title: 'Salary',
                            key: 'salary',
                            render: (_, row) => {
                                if (row.employee_type === 'intern' && !row.intern_compensation_enabled) {
                                    return <Tag color="default">UNPAID INTERN</Tag>;
                                }

                                return `${(row.salary_type || '-').toUpperCase()} / PHP ${Number(row.salary_amount || 0).toFixed(2)}`;
                            },
                        },
                        {
                            title: 'Actions',
                            key: 'actions',
                            width: 130,
                            render: (_, row) => {
                                const menuItems: MenuProps['items'] = [
                                    {
                                        key: 'attendance',
                                        label: <Link href={route('admin.attendance.show', row.id)}>Attendance</Link>,
                                    },
                                    {
                                        key: 'edit',
                                        label: <Link href={route('admin.employees.edit', row.id)}>Edit</Link>,
                                    },
                                ];

                                if (row.employment_status === 'active') {
                                    menuItems.push({
                                        key: 'deactivate',
                                        danger: true,
                                        label: (
                                            <Popconfirm
                                                title="Deactivate employee?"
                                                description="Login will be blocked, but records are retained."
                                                okText="Deactivate"
                                                okButtonProps={{ danger: true }}
                                                onConfirm={() => router.patch(route('admin.employees.deactivate', row.id))}
                                            >
                                                <span>Deactivate</span>
                                            </Popconfirm>
                                        ),
                                    });
                                    menuItems.push({
                                        key: 'archive',
                                        danger: true,
                                        label: (
                                            <Popconfirm
                                                title="Archive employee?"
                                                description="Employee will be archived and hidden from active operations."
                                                okText="Archive"
                                                okButtonProps={{ danger: true }}
                                                onConfirm={() => router.patch(route('admin.employees.archive', row.id))}
                                            >
                                                <span>Archive</span>
                                            </Popconfirm>
                                        ),
                                    });
                                } else {
                                    menuItems.push({
                                        key: 'reactivate',
                                        label: (
                                            <Popconfirm
                                                title="Reactivate employee?"
                                                okText="Reactivate"
                                                onConfirm={() => router.patch(route('admin.employees.reactivate', row.id))}
                                            >
                                                <span>Reactivate</span>
                                            </Popconfirm>
                                        ),
                                    });
                                }

                                return (
                                    <Dropdown menu={{ items: menuItems }} trigger={['click']}>
                                        <Button size="small">Actions</Button>
                                    </Dropdown>
                                );
                            },
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
