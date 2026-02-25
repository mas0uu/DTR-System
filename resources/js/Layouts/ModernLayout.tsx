import { useState, ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { 
    MenuFoldOutlined, 
    MenuUnfoldOutlined,
    DashboardOutlined,
    ShoppingCartOutlined,
    ShopOutlined,
    UserOutlined,
    TeamOutlined,
    SafetyOutlined,
    FileTextOutlined,
    AppstoreOutlined,
    LogoutOutlined,
    SettingOutlined,
} from '@ant-design/icons';
import { Layout, Menu, Button, Avatar, Dropdown, Space, ConfigProvider, theme } from 'antd';
import type { MenuProps } from 'antd';

const { Header, Sider, Content } = Layout;

interface ModernLayoutProps {
    children: ReactNode;
}

export default function ModernLayout({ children }: ModernLayoutProps) {
    const user = usePage().props.auth.user;
    const [collapsed, setCollapsed] = useState(false);
    const currentPath = window.location.pathname;

    // Get active key based on current path
    const getActiveKey = () => {
        if (currentPath.includes('/products')) return 'products-list';
        if (currentPath.includes('/categories')) return 'categories';
        if (currentPath.includes('/orders')) return 'orders';
        if (currentPath.includes('/customers')) return 'customers';
        if (currentPath.includes('/purchase-orders')) return 'purchase-orders';
        if (currentPath.includes('/suppliers')) return 'suppliers';
        if (currentPath.includes('/admin/users')) return 'users';
        if (currentPath.includes('/admin/roles')) return 'roles';
        if (currentPath === '/dashboard') return 'dashboard';
        return 'dashboard';
    };

    const menuItems: MenuProps['items'] = [
        {
            key: 'dashboard',
            icon: <DashboardOutlined style={{ fontSize: '18px' }} />,
            label: <Link href={route('dashboard')} style={{ fontSize: '14px', fontWeight: 500 }}>Dashboard</Link>,
        },
        {
            type: 'divider',
        },
        {
            key: 'products-group',
            icon: <AppstoreOutlined style={{ fontSize: '18px' }} />,
            label: <span style={{ fontSize: '14px', fontWeight: 500 }}>Products</span>,
            children: [
                {
                    key: 'products-list',
                    label: <Link href={route('products.index')}>All Products</Link>,
                },
                {
                    key: 'categories',
                    label: <Link href={route('categories.index')}>Categories</Link>,
                },
            ],
        },
        {
            key: 'sales-group',
            icon: <ShoppingCartOutlined style={{ fontSize: '18px' }} />,
            label: <span style={{ fontSize: '14px', fontWeight: 500 }}>Sales</span>,
            children: [
                {
                    key: 'orders',
                    label: <Link href={route('orders.index')}>Sales Orders</Link>,
                },
                {
                    key: 'customers',
                    label: <Link href={route('customers.index')}>Customers</Link>,
                },
            ],
        },
        {
            key: 'purchase-group',
            icon: <ShopOutlined style={{ fontSize: '18px' }} />,
            label: <span style={{ fontSize: '14px', fontWeight: 500 }}>Purchasing</span>,
            children: [
                {
                    key: 'purchase-orders',
                    label: <Link href={route('purchase-orders.index')}>Purchase Orders</Link>,
                },
                {
                    key: 'suppliers',
                    label: <Link href={route('suppliers.index')}>Suppliers</Link>,
                },
            ],
        },
        {
            type: 'divider',
        },
        {
            key: 'admin-group',
            icon: <SafetyOutlined style={{ fontSize: '18px' }} />,
            label: <span style={{ fontSize: '14px', fontWeight: 500 }}>Admin</span>,
            children: [
                {
                    key: 'users',
                    label: <Link href={route('admin.users.index')}>Users</Link>,
                },
                {
                    key: 'roles',
                    label: <Link href={route('admin.roles.index')}>Roles</Link>,
                },
            ],
        },
    ];

    const userMenuItems: MenuProps['items'] = [
        {
            key: 'profile',
            icon: <SettingOutlined />,
            label: <Link href={route('profile.edit')}>Settings</Link>,
        },
        {
            type: 'divider',
        },
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: (
                <Link href={route('logout')} method="post" as="button">
                    Sign out
                </Link>
            ),
        },
    ];

    return (
        <ConfigProvider
            theme={{
                algorithm: theme.defaultAlgorithm,
                token: {
                    colorPrimary: '#000000',
                    borderRadius: 4,
                    fontSize: 14,
                },
                components: {
                    Layout: {
                        siderBg: '#ffffff',
                        triggerBg: '#000000',
                    },
                    Menu: {
                        itemBg: '#ffffff',
                        itemSelectedBg: '#ff0000',
                        itemSelectedColor: '#000000',
                        itemColor: '#666666',
                        itemHoverBg: '#00ff4c',
                        itemHoverColor: '#000000',
                    },
                },
            }}
        >
            <Layout style={{ minHeight: '100vh', background: '#7D8491' }}>
                <Sider 
                    trigger={null} 
                    collapsible 
                    collapsed={collapsed}
                    width={240}
                    style={{
                        overflow: 'auto',
                        height: '100vh',
                        position: 'fixed',
                        left: 0,
                        top: 0,
                        bottom: 0,
                        borderRight: '1px solid #7D8491',
                        background: '#7D8491',
                    }}
                >
                    <div style={{ 
                        height: '64px', 
                        display: 'flex', 
                        alignItems: 'center', 
                        justifyContent: collapsed ? 'center' : 'flex-start',
                        paddingLeft: collapsed ? 0 : '24px',
                        borderBottom: '1px solid #f0f0f0',
                    }}>
                        <div style={{
                            fontSize: collapsed ? '18px' : '16px',
                            fontWeight: 700,
                            color: '#000000',
                            letterSpacing: '-0.5px',
                        }}>
                            {collapsed ? 'DN' : 'DONGKIES'}
                        </div>
                    </div>
                    <Menu

                        mode="inline"
                        selectedKeys={[getActiveKey()]}
                        defaultOpenKeys={['products-group', 'sales-group', 'purchase-group', 'admin-group']}
                        items={menuItems}
                        style={{ 
                            borderRight: 0,
                            marginTop: '8px',
                            background: '#0059ff',
                        }}
                    />
                </Sider>
                <Layout style={{ marginLeft: collapsed ? 80 : 240, transition: 'margin-left 0.2s', background: '#3F334D' }}>
                    <Header style={{ 
                        padding: '0 32px', 
                        background: '#574B60',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        borderBottom: '1px solid #f0f0f0',
                        height: '64px',
                    }}>
                        <Button
                            type="text"
                            icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                            onClick={() => setCollapsed(!collapsed)}
                            style={{
                                fontSize: '16px',
                                width: 40,
                                height: 40,
                                color: '#D9DBF1',
                            }}
                        />
                        <Dropdown menu={{ items: userMenuItems }} placement="bottomRight" trigger={['click']}>
                            <Space style={{ cursor: 'pointer' }}>
                                <div style={{ 
                                    width: 32, 
                                    height: 32, 
                                    borderRadius: '50%', 
                                    background: '#000000',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    color: '#ffffff',
                                    fontSize: '12px',
                                    fontWeight: 600,
                                }}>
                                    {user.name.charAt(0).toUpperCase()}
                                </div>
                                <span style={{ fontSize: '14px', fontWeight: 500, color: '#000000' }}>
                                    {user.name}
                                </span>
                            </Space>
                        </Dropdown>
                    </Header>
                    <Content style={{ 
                        margin: '24px 32px',
                        minHeight: 'calc(100vh - 112px)',
                    }}>
                        <div style={{
                            background: '#574B60',
                            padding: '32px',
                            borderRadius: '8px',
                            border: '1px solid #f0f0f0',
                            minHeight: '100%',
                        }}>
                            {children}
                        </div>
                    </Content>
                </Layout>
            </Layout>
        </ConfigProvider>
    );
}
