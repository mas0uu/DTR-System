import { Head, Link } from '@inertiajs/react';
import { Card, List, Button, Statistic, Row, Col, Empty, Tabs, Progress, Tag, Typography } from 'antd';
import { ArrowRightOutlined, EditOutlined, PrinterOutlined } from '@ant-design/icons';

interface User {
    student_name: string;
    student_no: string | null;
    school: string | null;
    required_hours: number;
    employee_type?: 'intern' | 'regular' | null;
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
}

const { Title, Paragraph } = Typography;

export default function DtrIndex({ months, user }: Props) {
    const isIntern = user.employee_type === 'intern';
    const displayName = user.student_name || '-';
    const totalLoggedHours = months.reduce((sum, month) => sum + month.total_hours, 0);
    const remainingHours = Math.max(0, user.required_hours - totalLoggedHours);
    const percent = user.required_hours
        ? Math.min(100, (totalLoggedHours / user.required_hours) * 100)
        : 0;

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
                            <Statistic title={isIntern ? 'Student Name' : 'Employee Name'} value={displayName} />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic
                                title="Employee Type"
                                value={isIntern ? 'Intern' : 'Regular Employee'}
                                formatter={(value) => value}
                            />
                        </Col>
                    </Row>
                    {isIntern && (
                        <Row gutter={16} className="mt-4">
                            <Col xs={24} sm={12}>
                                <Statistic title="Student Number" value={user.student_no || '-'} formatter={(value) => value} />
                            </Col>
                            <Col xs={24} sm={12}>
                                <Statistic title="School" value={user.school || '-'} />
                            </Col>
                        </Row>
                    )}
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic title="Total Logged Hours" value={totalLoggedHours.toFixed(2)} suffix="hours" />
                        </Col>
                        <Col xs={24} sm={12}>
                            {isIntern ? (
                                <Statistic title="Remaining Hours" value={remainingHours.toFixed(2)} suffix="hours" />
                            ) : (
                                <Statistic title="Required Hours" value="-" />
                            )}
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic title="Company" value={user.company} />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic title="Department" value={user.department} />
                        </Col>
                    </Row>
                    <Row gutter={16} className="mt-4">
                        <Col xs={24} sm={12}>
                            <Statistic title="Supervisor Name" value={user.supervisor_name} />
                        </Col>
                        <Col xs={24} sm={12}>
                            <Statistic title="Supervisor Position" value={user.supervisor_position} />
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
                        <Empty description="No DTR records found" style={{ marginTop: 50, marginBottom: 24 }} />
                    ) : (
                        <List
                            dataSource={months}
                            renderItem={(month) => (
                                <div key={month.id}>
                                    <List.Item
                                        actions={[
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
                                            description={`${month.total_hours.toFixed(2)} hours (days recorded: ${month.finished_rows})`}
                                        />
                                        {month.is_fulfilled && <Tag color="success">Fulfilled</Tag>}
                                    </List.Item>
                                </div>
                            )}
                        />
                    )}
                </div>
            ),
        },
    ];

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
                <Tabs
                    items={tabItems}
                    tabBarExtraContent={
                        <div style={{ width: 260, paddingLeft: 12 }}>
                            <div style={{ fontSize: 12, marginBottom: -10 }}>
                                {isIntern
                                    ? `${totalLoggedHours.toFixed(2)} / ${Number(user.required_hours).toFixed(2)} hrs`
                                    : `${totalLoggedHours.toFixed(2)} hrs logged`}
                            </div>
                            <Progress percent={isIntern ? Number(percent.toFixed(1)) : 0} size="small" />
                        </div>
                    }
                />
            </Card>
        </>
    );
}
