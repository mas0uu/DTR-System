import { Head, Link } from '@inertiajs/react';
import { Button, Card, Col, Result, Row, Statistic } from 'antd';
import { FileTextOutlined, UserOutlined, ClockCircleOutlined } from '@ant-design/icons';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6">
                <Row gutter={[16, 16]}>
                    <Col xs={24} md={8}>
                        <Card>
                            <Statistic title="Attendance Records" value="DTR" prefix={<FileTextOutlined />} />
                        </Card>
                    </Col>
                    <Col xs={24} md={8}>
                        <Card>
                            <Statistic title="Profile Settings" value="Ready" prefix={<UserOutlined />} />
                        </Card>
                    </Col>
                    <Col xs={24} md={8}>
                        <Card>
                            <Statistic title="Time Tracking" value="Active" prefix={<ClockCircleOutlined />} />
                        </Card>
                    </Col>
                </Row>

                <Card>
                    <Result
                        status="success"
                        title="Welcome to DTR System"
                        subTitle="Manage your daily time records and monitor internship progress from one dashboard."
                        extra={
                            <Link href={route('dtr.index')}>
                                <Button type="primary" size="large">
                                    Open DTR Records
                                </Button>
                            </Link>
                        }
                    />
                </Card>
            </div>
        </>
    );
}
