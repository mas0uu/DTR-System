import { PageProps as AppPageProps } from '@/types';
import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import UserSearchControl from '@/Components/ui/UserSearchControl';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Alert, Button, Dropdown, Popconfirm, Select, Space, Table, Tag } from 'antd';
import type { MenuProps } from 'antd';
import { useMemo, useState } from 'react';

type Employee = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'employee' | 'intern';
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
    is_self: boolean;
    can_delete_admin: boolean;
};

type Props = AppPageProps<{
    employees: Employee[];
    flash?: {
        success?: string;
    };
    errors?: Record<string, string>;
}>;

export default function EmployeesIndex() {
    const { employees, flash, errors } = usePage<Props>().props;
    const [roleFilter, setRoleFilter] = useState<'all' | 'admin' | 'employee' | 'intern'>('all');
    const [userSearch, setUserSearch] = useState('');

    const filteredEmployees = useMemo(() => {
        const query = userSearch.trim().toLowerCase();

        return employees.filter((employee) => {
            const passesRole = roleFilter === 'all' || employee.role === roleFilter;
            if (!passesRole) {
                return false;
            }

            if (query === '') {
                return true;
            }

            return [
                employee.name,
                employee.email,
                employee.department || '',
                employee.company || '',
                employee.role,
            ].some((value) => value.toLowerCase().includes(query));
        });
    }, [employees, roleFilter, userSearch]);
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
    const adminCount = useMemo(
        () => employees.filter((employee) => employee.role === 'admin').length,
        [employees],
    );
    const firstError = useMemo(() => {
        if (!errors) {
            return null;
        }

        const entries = Object.values(errors).filter((value) => typeof value === 'string' && value.trim() !== '');
        return entries.length > 0 ? entries[0] : null;
    }, [errors]);

    return (
        <>
            <Head title="Users" />
            <PageHeader
                title="Users"
                subtitle="Manage admin, employee, and intern accounts from one panel."
                actions={(
                    <Space>
                        <Link href={route('admin.payroll.index')}>
                            <Button>Payroll Center</Button>
                        </Link>
                        <Link href={route('admin.leaves.index')}>
                            <Button>Leave Queue</Button>
                        </Link>
                        <Link href={route('admin.employees.create')}>
                            <Button type="primary">Create User</Button>
                        </Link>
                    </Space>
                )}
            />

            {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}
            {firstError && <Alert type="error" message={firstError} showIcon className="mb-4" />}

            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <MetricCard label="Total Users" value={employees.length} />
                <MetricCard label="Admins" value={adminCount} />
                <MetricCard label="Active Staff" value={activeCount} />
                <MetricCard label="Inactive/Archived" value={inactiveCount + archivedCount} />
            </div>

            <TableCard
                title="User Directory"
                actions={(
                    <Space wrap>
                        <UserSearchControl value={userSearch} onChange={setUserSearch} />
                        <Select
                            value={roleFilter}
                            style={{ width: 190 }}
                            onChange={(value) => setRoleFilter(value)}
                            options={[
                                { label: 'All roles', value: 'all' },
                                { label: 'Admin', value: 'admin' },
                                { label: 'Employee', value: 'employee' },
                                { label: 'Intern', value: 'intern' },
                            ]}
                        />
                    </Space>
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
                            title: 'Role',
                            key: 'role',
                            render: (_, row) => (
                                <Tag
                                    color={
                                        row.role === 'admin'
                                            ? 'red'
                                            : row.role === 'employee'
                                                ? 'blue'
                                                : 'green'
                                    }
                                >
                                    {row.role.toUpperCase()}
                                </Tag>
                            ),
                        },
                        {
                            title: 'Status',
                            key: 'employment_status',
                            render: (_, row) => (
                                row.role === 'admin' ? <Tag color="processing">SYSTEM ADMIN</Tag> : (
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
                                )
                            ),
                        },
                        { title: 'Department', dataIndex: 'department', key: 'department', ellipsis: true },
                        { title: 'Company', dataIndex: 'company', key: 'company', ellipsis: true },
                        {
                            title: 'Salary',
                            key: 'salary',
                            render: (_, row) => {
                                if (row.role === 'admin') {
                                    return <Tag color="default">N/A</Tag>;
                                }

                                if (row.role === 'intern') {
                                    return <Tag color="green">INTERNSHIP TRACKING</Tag>;
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
                                        key: 'edit',
                                        label: <Link href={route('admin.employees.edit', row.id)}>Edit</Link>,
                                    },
                                ];

                                if (row.role !== 'admin') {
                                    menuItems.unshift({
                                        key: 'attendance',
                                        label: <Link href={route('admin.attendance.show', row.id)}>Attendance</Link>,
                                    });

                                    if (row.employment_status === 'active') {
                                        menuItems.push({
                                            key: 'deactivate',
                                            danger: true,
                                            label: (
                                                <Popconfirm
                                                    title="Deactivate user?"
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
                                                    title="Archive user?"
                                                    description="User will be archived and hidden from active operations."
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
                                                    title="Reactivate user?"
                                                    okText="Reactivate"
                                                    onConfirm={() => router.patch(route('admin.employees.reactivate', row.id))}
                                                >
                                                    <span>Reactivate</span>
                                                </Popconfirm>
                                            ),
                                        });
                                    }
                                } else {
                                    if (row.can_delete_admin) {
                                        menuItems.push({
                                            key: 'delete-admin',
                                            danger: true,
                                            label: (
                                                <Popconfirm
                                                    title="Delete admin account?"
                                                    description="This action permanently removes the admin account."
                                                    okText="Delete"
                                                    okButtonProps={{ danger: true }}
                                                    onConfirm={() => router.delete(route('admin.employees.destroy', row.id))}
                                                >
                                                    <span>Delete Admin</span>
                                                </Popconfirm>
                                            ),
                                        });
                                    }
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
