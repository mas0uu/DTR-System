import { PageProps as AppPageProps } from '@/types';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { Head, usePage } from '@inertiajs/react';
import { Table, Tag } from 'antd';

type Holiday = {
    id: number;
    name: string;
    date_start: string;
    date_end: string | null;
    holiday_type: 'regular' | 'special';
    is_paid: boolean;
    has_attendance_bonus: boolean;
    attendance_bonus_type: 'fixed_amount' | 'percent_of_daily_rate' | null;
    attendance_bonus_value: number | null;
};

type Props = AppPageProps<{
    holidays: Holiday[];
}>;

export default function EmployeeHolidaysIndex() {
    const { holidays } = usePage<Props>().props;

    return (
        <>
            <Head title="Holiday Schedule" />
            <PageHeader
                title="Holiday Schedule"
                subtitle="Holiday settings are managed by Admin. This is a read-only schedule for employee reference."
            />
            <TableCard>
                <Table
                    rowKey="id"
                    dataSource={holidays}
                    pagination={{ pageSize: 20 }}
                    columns={[
                        { title: 'Holiday', dataIndex: 'name' },
                        {
                            title: 'Date',
                            render: (_, row) =>
                                row.date_end && row.date_end !== row.date_start
                                    ? `${row.date_start} to ${row.date_end}`
                                    : row.date_start,
                        },
                        { title: 'Type', dataIndex: 'holiday_type', render: (value) => <Tag>{String(value).toUpperCase()}</Tag> },
                        { title: 'Paid', dataIndex: 'is_paid', render: (value) => <Tag color={value ? 'green' : 'red'}>{value ? 'YES' : 'NO'}</Tag> },
                        {
                            title: 'Attendance Bonus',
                            render: (_, row) => {
                                if (!row.has_attendance_bonus) {
                                    return <Tag color="default">NONE</Tag>;
                                }

                                return row.attendance_bonus_type === 'fixed_amount'
                                    ? <Tag color="blue">PHP {Number(row.attendance_bonus_value || 0).toFixed(2)}</Tag>
                                    : <Tag color="purple">{Number(row.attendance_bonus_value || 0).toFixed(2)}%</Tag>;
                            },
                        },
                    ]}
                />
            </TableCard>
        </>
    );
}
