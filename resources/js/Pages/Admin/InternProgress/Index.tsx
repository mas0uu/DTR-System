import MetricCard from '@/Components/ui/MetricCard';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import UserSearchControl from '@/Components/ui/UserSearchControl';
import { PageProps as AppPageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Progress, Space, Table, Tag } from 'antd';
import { useMemo, useState } from 'react';

type InternProgress = {
    id: number;
    name: string;
    email: string;
    school: string | null;
    intern_compensation_enabled: boolean;
    required_hours: number;
    logged_hours: number;
    remaining_hours: number;
    estimated_completion_date: string | null;
    completion_percent: number;
    progress_status: 'in_progress' | 'near_completion' | 'completed';
};

type Props = AppPageProps<{
    interns: InternProgress[];
}>;

export default function AdminInternProgressIndex() {
    const { interns } = usePage<Props>().props;
    const [userSearch, setUserSearch] = useState('');
    const filteredInterns = useMemo(() => {
        const query = userSearch.trim().toLowerCase();
        if (query === '') {
            return interns;
        }

        return interns.filter((row) => (
            row.name.toLowerCase().includes(query)
            || row.email.toLowerCase().includes(query)
            || (row.school || '').toLowerCase().includes(query)
        ));
    }, [interns, userSearch]);
    const completedCount = useMemo(
        () => interns.filter((item) => item.progress_status === 'completed').length,
        [interns],
    );
    const nearCompletionCount = useMemo(
        () => interns.filter((item) => item.progress_status === 'near_completion').length,
        [interns],
    );
    const inProgressCount = useMemo(
        () => interns.filter((item) => item.progress_status === 'in_progress').length,
        [interns],
    );

    return (
        <>
            <Head title="Intern Progress" />
            <PageHeader
                title="Intern Progress"
                subtitle="Track internship hour completion and program status for all interns."
                actions={(
                    <Link href={route('admin.employees.index')}>
                        <Button>Employees</Button>
                    </Link>
                )}
            />

            <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Total Interns" value={interns.length} />
                <MetricCard label="Completed" value={completedCount} />
                <MetricCard label="Near Completion" value={nearCompletionCount} />
                <MetricCard label="In Progress" value={inProgressCount} />
            </div>

            <TableCard
                title="Intern Completion Tracker"
                actions={(
                    <UserSearchControl value={userSearch} onChange={setUserSearch} />
                )}
            >
                <Table
                    rowKey="id"
                    dataSource={filteredInterns}
                    pagination={{ pageSize: 12 }}
                    columns={[
                        {
                            title: 'Intern',
                            render: (_, row) => (
                                <Space direction="vertical" size={0}>
                                    <span>{row.name}</span>
                                    <span className="text-xs text-gray-500">{row.email}</span>
                                </Space>
                            ),
                        },
                        { title: 'School', dataIndex: 'school', render: (v) => v || '-' },
                        {
                            title: 'Compensation',
                            dataIndex: 'intern_compensation_enabled',
                            render: (value) => (
                                <Tag color={value ? 'blue' : 'default'}>
                                    {value ? 'ENABLED' : 'DISABLED'}
                                </Tag>
                            ),
                        },
                        { title: 'Required Hours', dataIndex: 'required_hours' },
                        { title: 'Logged Hours', dataIndex: 'logged_hours' },
                        { title: 'Remaining', dataIndex: 'remaining_hours' },
                        { title: 'Estimated Completion', dataIndex: 'estimated_completion_date', render: (value) => value || '-' },
                        {
                            title: 'Progress',
                            render: (_, row) => (
                                <div style={{ minWidth: 180 }}>
                                    <Progress percent={Math.min(100, row.completion_percent)} size="small" />
                                </div>
                            ),
                        },
                        {
                            title: 'Status',
                            render: (_, row) => (
                                <Tag
                                    color={
                                        row.progress_status === 'completed'
                                            ? 'green'
                                            : row.progress_status === 'near_completion'
                                                ? 'gold'
                                                : 'blue'
                                    }
                                >
                                    {row.progress_status.replace('_', ' ').toUpperCase()}
                                </Tag>
                            ),
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
