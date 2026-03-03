import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { Card, List, Button, Statistic, Row, Col, Empty, Tabs, Progress, Tag, Typography, Modal, Form, Select, InputNumber, Popconfirm, Tooltip, message } from 'antd';
import { ArrowRightOutlined, DeleteOutlined, EditOutlined, PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import { useState } from 'react';
import axios from 'axios';

interface User {
    student_name: string;
    student_no: string;
    school: string;
    required_hours: number;
    company: string;
    department: string;
    supervisor_name: string;
    supervisor_position: string;
}

interface Month {
    id: number;
    month: number;
    year: number;
    monthName: string;
    is_fulfilled: boolean;
    total_hours: number;
    finished_rows: number;
}

interface Props {
    months: Month[];
    user: User;
    current_month_id: number;
    initial_tab?: '1' | '2';
}

const { Title, Paragraph } = Typography;

export default function DtrIndex({
    months,
    user,
    current_month_id,
    initial_tab = '1',
}: Props) {
    const [isAddMonthModalOpen, setIsAddMonthModalOpen] = useState(false);
    const [submittingMonth, setSubmittingMonth] = useState(false);
    const [activeTab, setActiveTab] = useState<string>(initial_tab);
    const [form] = Form.useForm();
    const totalLoggedHours = months.reduce((sum, month) => sum + month.total_hours, 0);
    const remainingHours = Math.max(0, user.required_hours - totalLoggedHours);
    const currentDate = new Date();

    const monthOptions = [
        { label: 'January', value: 1 },
        { label: 'February', value: 2 },
        { label: 'March', value: 3 },
        { label: 'April', value: 4 },
        { label: 'May', value: 5 },
        { label: 'June', value: 6 },
        { label: 'July', value: 7 },
        { label: 'August', value: 8 },
        { label: 'September', value: 9 },
        { label: 'October', value: 10 },
        { label: 'November', value: 11 },
        { label: 'December', value: 12 },
    ];

    const handleOpenAddMonth = () => {
        const previousMonthDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
        form.setFieldsValue({
            month: previousMonthDate.getMonth() + 1,
            year: previousMonthDate.getFullYear(),
        });
        setIsAddMonthModalOpen(true);
    };

    const handleAddMonth = async () => {
        try {
            const values = await form.validateFields();
            setSubmittingMonth(true);
            await axios.post(route('dtr.months.store'), values);
            message.success('Month added successfully');
            setIsAddMonthModalOpen(false);
            form.resetFields();
            router.get(route('dtr.index', { tab: '2' }), {}, { preserveScroll: true });
        } catch (error: any) {
            if (error?.errorFields) return;
            const errorMsg = error.response?.data?.message || 'Failed to add month';
            message.error(errorMsg);
        } finally {
            setSubmittingMonth(false);
        }
    };

    const handleDeleteMonth = async (monthId: number) => {
        try {
            await axios.delete(route('dtr.months.destroy', { month: monthId }));
            message.success('Month deleted successfully');
            router.get(route('dtr.index', { tab: '2' }), {}, { preserveScroll: true });
        } catch (error: any) {
            const errorMsg = error.response?.data?.message || 'Failed to delete month';
            message.error(errorMsg);
        }
    };

    const tabItems = [
        {
            key: '1',
            label: 'User Information',
            children: (
                <Card>
                    <div className="mb-4 flex justify-end">
                        <Link href={route('profile.edit')}>
                            <Button icon={<EditOutlined />}>Edit User Information</Button>
                        </Link>
                    </div>
                    <Row gutter={16}>
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="Student Name"
                                value={user.student_name}
                            />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="Student Number"
                                value={user.student_no}
                                formatter={(value) => value}
                            />
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="School"
                                value={user.school}
                            />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="Required Hours"
                                value={user.required_hours}
                                suffix="hours"
                            />
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic
                                title="Total Logged Hours"
                                value={totalLoggedHours.toFixed(2)}
                                suffix="hours"
                            />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic
                                title="Remaining Hours"
                                value={remainingHours.toFixed(2)}
                                suffix="hours"
                            />
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="Company"
                                value={user.company}
                            />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="Department"
                                value={user.department}
                            />
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="Supervisor Name"
                                value={user.supervisor_name}
                            />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic 
                                title="Supervisor Position"
                                value={user.supervisor_position}
                            />
                        </Col>
                    </Row>
                </Card>
            ),
        },
        {
            key: '2',
            label: `Monthly Records (${months.length})`,
            children: (
                <div>
                    {months.length === 0 ? (
                        <Empty 
                            description="No DTR records found"
                            style={{ marginTop: 50, marginBottom: 24 }}
                        />
                    ) : (
                        <List
                            dataSource={months}
                            renderItem={(month) => (
                                <div key={month.id}>
                                    <List.Item
                                        actions={[
                                            <Popconfirm
                                                title="Delete month"
                                                description={`Delete ${month.monthName}?`}
                                                okText="Delete"
                                                okButtonProps={{ danger: true }}
                                                cancelText="Cancel"
                                                onConfirm={() => handleDeleteMonth(month.id)}
                                            >
                                                <Tooltip title={month.id === current_month_id ? 'Current month cannot be deleted' : ''}>
                                                    <Button
                                                        danger
                                                        icon={<DeleteOutlined />}
                                                        disabled={month.id === current_month_id}
                                                    >
                                                        Delete
                                                    </Button>
                                                </Tooltip>
                                            </Popconfirm>,
                                            <a
                                                href={route('dtr.months.show', { month: month.id, print: 1 })}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Button icon={<PrinterOutlined />}>Print DTR</Button>
                                            </a>,
                                            <Link href={route('dtr.months.show', month.id)}>
                                                <Button type="primary">
                                                    View <ArrowRightOutlined />
                                                </Button>
                                            </Link>,
                                        ]}
                                    >
                                        <List.Item.Meta
                                            title={month.monthName}
                                            description={`${month.total_hours.toFixed(2)} hours ( days recorded: ${month.finished_rows} )`}
                                        />
                                        {month.is_fulfilled && (
                                            <Tag color="success">Fulfilled</Tag>
                                        )}
                                    </List.Item>
                                </div>
                            )}
                        />
                    )}
                    <div className="mt-4 flex justify-center">
                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            onClick={handleOpenAddMonth}
                        >
                            Add Month
                        </Button>
                    </div>
                </div>
            ),
        },
    ];

    
    const percent = user.required_hours
    ? Math.min(100, (totalLoggedHours / user.required_hours) * 100)
    : 0;
    return (
    <>
        <Tabs
        items={tabItems}
        tabBarExtraContent={
            <div style={{ width: 260, paddingLeft: 12 }}>
            <div style={{ fontSize: 12, marginBottom: -10 }}>
                {totalLoggedHours.toFixed(2)} / {Number(user.required_hours).toFixed(2)} hrs
            </div>
            <Progress percent={Number(percent.toFixed(1))} size="small" />
            </div>
        }
        />
    </>
    );

    return (
        <>
            <Head title="DTR Records" />

            <Card
                title={<Title level={3} style={{ margin: 0 }}>Daily Time Record</Title>}
                extra={
                    <div className="text-right">
                        <Paragraph className="!mb-0 !text-slate-600">Welcome, {user.student_name}!</Paragraph>
                    </div>
                }
            >
                <Tabs activeKey={activeTab} onChange={setActiveTab} items={tabItems} />
            </Card>

            <Modal
                title="Add Month"
                open={isAddMonthModalOpen}
                onOk={handleAddMonth}
                okText="Add Month"
                confirmLoading={submittingMonth}
                onCancel={() => setIsAddMonthModalOpen(false)}
            >
                <Form form={form} layout="vertical">
                    <Form.Item
                        label="Month"
                        name="month"
                        rules={[{ required: true, message: 'Please select a month' }]}
                    >
                        <Select options={monthOptions} />
                    </Form.Item>

                    <Form.Item
                        label="Year"
                        name="year"
                        rules={[{ required: true, message: 'Please enter a year' }]}
                    >
                        <InputNumber style={{ width: '100%' }} min={2000} max={2100} />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}
