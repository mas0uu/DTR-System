import { PageProps as AppPageProps } from '@/types';
import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import UserSearchControl from '@/Components/ui/UserSearchControl';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Select, Space, Table, Tag } from 'antd';
import { useMemo, useState } from 'react';

type Employee = {
    id: number;
    name: string;
    email: string;
    department: string | null;
    company: string | null;
    employee_type: 'intern' | 'regular' | null;
};

type Props = AppPageProps<{
    employees: Employee[];
}>;

export default function AdminAttendanceIndex() {
    const { employees } = usePage<Props>().props;
    const [roleFilter, setRoleFilter] = useState<'all' | 'regular' | 'intern'>('all');
    const [userSearch, setUserSearch] = useState('');

    const filteredEmployees = useMemo(() => {
        const query = userSearch.trim().toLowerCase();

        return employees.filter((employee) => {
            const passesRole = roleFilter === 'all' || employee.employee_type === roleFilter;
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
                employee.employee_type || '',
            ].some((value) => value.toLowerCase().includes(query));
        });
    }, [employees, roleFilter, userSearch]);
    const internCount = useMemo(
        () => employees.filter((employee) => employee.employee_type === 'intern').length,
        [employees],
    );
    const regularCount = useMemo(
        () => employees.filter((employee) => employee.employee_type === 'regular').length,
        [employees],
    );

    return (
        <>
            <Head title="Attendance Center" />
            <PageHeader
                title="Attendance Center"
                subtitle="Review attendance by employee and jump to row-level correction."
                actions={(
                    <Space>
                        <Link href={route('admin.attendance.logs')}>
                            <Button type="primary">Attendance Logs</Button>
                        </Link>
                        <Link href={route('admin.employees.index')}>
                            <Button>Employees</Button>
                        </Link>
                    </Space>
                )}
            />

            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <MetricCard label="Visible Employees" value={filteredEmployees.length} />
                <MetricCard label="Total Employees" value={employees.length} />
                <MetricCard label="Regular" value={regularCount} />
                <MetricCard label="Intern" value={internCount} />
            </div>

            <TableCard
                title="Employee Attendance Access"
                actions={(
                    <Space wrap>
                        <UserSearchControl value={userSearch} onChange={setUserSearch} />
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
                    </Space>
                )}
            >
                <Table
                    rowKey="id"
                    dataSource={filteredEmployees}
                    columns={[
                        { title: 'Name', dataIndex: 'name', ellipsis: true },
                        { title: 'Email', dataIndex: 'email', ellipsis: true },
                        { title: 'Department', dataIndex: 'department', ellipsis: true },
                        { title: 'Company', dataIndex: 'company', ellipsis: true },
                        {
                            title: 'Type',
                            render: (_, row) => (
                                <Tag color={row.employee_type === 'intern' ? 'gold' : 'blue'}>
                                    {(row.employee_type || '-').toUpperCase()}
                                </Tag>
                            ),
                        },
                        {
                            title: 'Action',
                            render: (_, row) => (
                                <Link href={route('admin.attendance.show', row.id)}>
                                    <Button type="primary" size="small">Review</Button>
                                </Link>
                            ),
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
