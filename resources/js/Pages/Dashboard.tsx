import { Head } from '@inertiajs/react';
import { Layout, Card, Row, Col, Statistic } from 'antd';
import {
    ShoppingCartOutlined,
    ShoppingOutlined,
    DollarOutlined,
    WarningOutlined,
} from '@ant-design/icons';

const { Content } = Layout;

export default function Dashboard() {
    return (
        <Layout style={{ minHeight: '100vh', background: '#574B60', color: '#ffffff'}} >
            <Head title="Dashboard" />

            <h1 style={{ fontSize: '24px', fontWeight: 'bold', marginBottom: '24px' }}>
                Dashboard Overview
            </h1>

            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic
                            title="Total Sales Today"
                            value={45000}
                            precision={2}
                            prefix={<DollarOutlined />}
                            valueStyle={{ color: '#3f8600' }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic
                            title="Pending Orders"
                            value={12}
                            prefix={<ShoppingCartOutlined />}
                            valueStyle={{ color: '#1890ff' }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic
                            title="Purchase Orders"
                            value={8}
                            prefix={<ShoppingOutlined />}
                            valueStyle={{ color: '#722ed1' }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic
                            title="Low Stock Items"
                            value={15}
                            prefix={<WarningOutlined />}
                            valueStyle={{ color: '#cf1322' }}
                        />
                    </Card>
                </Col>
            </Row>

            <Row gutter={[16, 16]} style={{ marginTop: '24px' }}>
                <Col xs={24} lg={12}>
                    <Card title="Recent Orders" bordered={false}>
                        <p>No recent orders</p>
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Low Stock Alert" bordered={false}>
                        <p>No low stock items</p>
                    </Card>
                </Col>
            </Row>
        </Layout>
    );
}
