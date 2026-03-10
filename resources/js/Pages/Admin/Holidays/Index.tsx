import { PageProps as AppPageProps } from '@/types';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Alert, Button, DatePicker, Input, Modal, Select, Space, Switch, Table, Tag } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';

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
    is_active: boolean;
    created_by: string | null;
};

type Props = AppPageProps<{
    holidays: Holiday[];
    flash?: {
        success?: string;
    };
}>;

export default function AdminHolidayIndex() {
    const { holidays, flash } = usePage<Props>().props;
    const [editingHolidayId, setEditingHolidayId] = useState<number | null>(null);

    const {
        data: createData,
        setData: setCreateData,
        post,
        processing,
        errors: createErrors,
        reset: resetCreate,
    } = useForm({
        name: '',
        date_start: '',
        date_end: '',
        holiday_type: 'regular' as Holiday['holiday_type'],
        is_paid: true,
        is_active: true,
    });
    const {
        data: editData,
        setData: setEditData,
        patch: patchEdit,
        processing: editProcessing,
        errors: editErrors,
        reset: resetEdit,
        clearErrors: clearEditErrors,
    } = useForm({
        name: '',
        date_start: '',
        date_end: '',
        holiday_type: 'regular' as Holiday['holiday_type'],
        is_paid: true,
        is_active: true,
    });

    const openEditModal = (holiday: Holiday) => {
        setEditingHolidayId(holiday.id);
        clearEditErrors();
        setEditData({
            name: holiday.name,
            date_start: holiday.date_start,
            date_end: holiday.date_end ?? '',
            holiday_type: holiday.holiday_type,
            is_paid: holiday.is_paid,
            is_active: holiday.is_active,
        });
    };

    const closeEditModal = () => {
        setEditingHolidayId(null);
        clearEditErrors();
        resetEdit();
    };

    const submitEdit = () => {
        if (!editingHolidayId) return;
        patchEdit(route('admin.holidays.update', editingHolidayId), {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
    };

    return (
        <>
            <Head title="Holidays" />
            <PageHeader
                title="Holiday Management"
                subtitle="Configure holiday schedule and paid/unpaid behavior for attendance and payroll."
                actions={(
                    <Link href={route('admin.employees.index')}>
                        <Button>Employees</Button>
                    </Link>
                )}
            />

            {flash?.success && <Alert type="success" message={flash.success} showIcon className="mb-4" />}

            <TableCard title="Add Holiday" className="mb-5">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(220px,2fr)_minmax(180px,1.5fr)_minmax(180px,1.5fr)_minmax(170px,1.2fr)_max-content_max-content_max-content] xl:items-end">
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Holiday Name</label>
                        <input
                            className="w-full rounded-md border border-gray-300 px-3 py-2"
                            value={createData.name}
                            onChange={(event) => setCreateData('name', event.target.value)}
                            placeholder="e.g. Labor Day"
                        />
                        {createErrors.name && <p className="text-sm text-red-600 mt-1">{createErrors.name}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Start Date</label>
                        <DatePicker
                            className="w-full"
                            format="YYYY-MM-DD"
                            value={createData.date_start ? dayjs(createData.date_start) : null}
                            onChange={(date) => setCreateData('date_start', date ? date.format('YYYY-MM-DD') : '')}
                        />
                        {createErrors.date_start && <p className="text-sm text-red-600 mt-1">{createErrors.date_start}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">End Date (Optional)</label>
                        <DatePicker
                            className="w-full"
                            format="YYYY-MM-DD"
                            value={createData.date_end ? dayjs(createData.date_end) : null}
                            onChange={(date) => setCreateData('date_end', date ? date.format('YYYY-MM-DD') : '')}
                        />
                        {createErrors.date_end && <p className="text-sm text-red-600 mt-1">{createErrors.date_end}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Type</label>
                        <Select
                            className="w-full"
                            value={createData.holiday_type}
                            onChange={(value) => setCreateData('holiday_type', value)}
                            options={[
                                { label: 'Regular', value: 'regular' },
                                { label: 'Special', value: 'special' },
                            ]}
                        />
                        {createErrors.holiday_type && <p className="text-sm text-red-600 mt-1">{createErrors.holiday_type}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Paid</label>
                        <div className="flex h-[40px] items-center">
                            <Switch checked={createData.is_paid} onChange={(value) => setCreateData('is_paid', value)} />
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-transparent select-none">Bonus</label>
                        <span className="flex h-[40px] flex-col justify-center text-xs leading-4 text-slate-500 whitespace-nowrap">
                            Bonus auto-applied:
                            <br />
                            Regular +100%, Special +30%
                        </span>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-transparent select-none">Action</label>
                        <Button
                            type="primary"
                            loading={processing}
                            className="h-[40px] w-full md:w-auto"
                            onClick={() =>
                                post(route('admin.holidays.store'), {
                                    onSuccess: () => {
                                        resetCreate('name', 'date_start', 'date_end');
                                        setCreateData('holiday_type', 'regular');
                                        setCreateData('is_paid', true);
                                    },
                                })
                            }
                        >
                            Add Holiday
                        </Button>
                    </div>
                </div>
            </TableCard>

            <TableCard title="Holiday List">
                <Table
                    rowKey="id"
                    dataSource={holidays}
                    pagination={{ pageSize: 12 }}
                    columns={[
                        { title: 'Name', dataIndex: 'name' },
                        {
                            title: 'Date',
                            render: (_, row) =>
                                row.date_end && row.date_end !== row.date_start
                                    ? `${row.date_start} to ${row.date_end}`
                                    : row.date_start,
                        },
                        { title: 'Type', dataIndex: 'holiday_type', render: (v) => <Tag>{String(v).toUpperCase()}</Tag> },
                        { title: 'Paid', dataIndex: 'is_paid', render: (v) => <Tag color={v ? 'green' : 'red'}>{v ? 'PAID' : 'UNPAID'}</Tag> },
                        {
                            title: 'Attendance Bonus',
                            render: (_, row) => {
                                if (!row.has_attendance_bonus) {
                                    return <Tag color="default">NONE</Tag>;
                                }

                                return <Tag color="purple">{Number(row.attendance_bonus_value || 0).toFixed(2)}%</Tag>;
                            },
                        },
                        {
                            title: 'Status',
                            dataIndex: 'is_active',
                            render: (v) => <Tag color={v ? 'green' : 'default'}>{v ? 'ACTIVE' : 'INACTIVE'}</Tag>,
                        },
                        {
                            title: 'Actions',
                            render: (_, row) => (
                                <Space>
                                    <Button
                                        size="small"
                                        onClick={() => openEditModal(row)}
                                    >
                                        Edit
                                    </Button>
                                    {row.is_active ? (
                                        <Button
                                            size="small"
                                            onClick={() =>
                                                router.patch(route('admin.holidays.update', row.id), {
                                                    name: row.name,
                                                    date_start: row.date_start,
                                                    date_end: row.date_end,
                                                    holiday_type: row.holiday_type,
                                                    is_paid: row.is_paid,
                                                    is_active: false,
                                                }, { preserveScroll: true })
                                            }
                                        >
                                            Deactivate
                                        </Button>
                                    ) : (
                                        <Button
                                            size="small"
                                            onClick={() =>
                                                router.patch(route('admin.holidays.update', row.id), {
                                                    name: row.name,
                                                    date_start: row.date_start,
                                                    date_end: row.date_end,
                                                    holiday_type: row.holiday_type,
                                                    is_paid: row.is_paid,
                                                    is_active: true,
                                                }, { preserveScroll: true })
                                            }
                                        >
                                            Reactivate
                                        </Button>
                                    )}
                                    <Button
                                        size="small"
                                        danger
                                        onClick={() => {
                                            const confirmed = window.confirm(`Delete "${row.name}" permanently?`);
                                            if (!confirmed) return;
                                            router.delete(route('admin.holidays.destroy', row.id), { preserveScroll: true });
                                        }}
                                    >
                                        Delete
                                    </Button>
                                </Space>
                            ),
                        },
                    ]}
                />
            </TableCard>

            <Modal
                title="Edit Holiday"
                open={editingHolidayId !== null}
                onCancel={closeEditModal}
                onOk={submitEdit}
                okText="Save Changes"
                confirmLoading={editProcessing}
                destroyOnClose
            >
                <div className="grid gap-3">
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Holiday Name</label>
                        <Input value={editData.name} onChange={(event) => setEditData('name', event.target.value)} />
                        {editErrors.name && <p className="mt-1 text-sm text-red-600">{editErrors.name}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Start Date</label>
                        <DatePicker
                            className="w-full"
                            format="YYYY-MM-DD"
                            value={editData.date_start ? dayjs(editData.date_start) : null}
                            onChange={(date) => setEditData('date_start', date ? date.format('YYYY-MM-DD') : '')}
                        />
                        {editErrors.date_start && <p className="mt-1 text-sm text-red-600">{editErrors.date_start}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">End Date (Optional)</label>
                        <DatePicker
                            className="w-full"
                            format="YYYY-MM-DD"
                            value={editData.date_end ? dayjs(editData.date_end) : null}
                            onChange={(date) => setEditData('date_end', date ? date.format('YYYY-MM-DD') : '')}
                        />
                        {editErrors.date_end && <p className="mt-1 text-sm text-red-600">{editErrors.date_end}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm text-gray-700">Type</label>
                        <Select
                            className="w-full"
                            value={editData.holiday_type}
                            onChange={(value) => setEditData('holiday_type', value)}
                            options={[
                                { label: 'Regular', value: 'regular' },
                                { label: 'Special', value: 'special' },
                            ]}
                        />
                        {editErrors.holiday_type && <p className="mt-1 text-sm text-red-600">{editErrors.holiday_type}</p>}
                    </div>
                    <div>
                        <label className="flex items-center gap-2 text-sm">
                            <Switch checked={editData.is_paid} onChange={(value) => setEditData('is_paid', value)} />
                            Paid Holiday
                        </label>
                    </div>
                    <p className="text-xs text-slate-500">
                        Bonus policy is fixed by holiday type: Regular +100%, Special +30%.
                    </p>
                </div>
            </Modal>
        </>
    );
}
