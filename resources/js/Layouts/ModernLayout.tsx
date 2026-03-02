import { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { 
    FileTextOutlined,
    LogoutOutlined,
    SettingOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { Layout, Button, Avatar, Dropdown, Space, ConfigProvider, theme } from 'antd';
import type { MenuProps } from 'antd';

const { Header, Content } = Layout;

interface ModernLayoutProps {
    children: ReactNode;
}

export default function ModernLayout({ children }: ModernLayoutProps) {
    const user = usePage().props.auth.user;

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
                    colorPrimary: '#2563eb',
                    borderRadius: 10,
                    fontSize: 14,
                },
                components: {
                    Layout: {
                        colorBgHeader: '#ffffff',
                        colorBgBody: '#f8fafc',
                    },
                    Menu: {
                        itemBg: '#ffffff',
                        itemSelectedBg: '#dbeafe',
                        itemSelectedColor: '#1d4ed8',
                        itemColor: '#666666',
                        itemHoverBg: '#eff6ff',
                        itemHoverColor: '#000000',
                    },
                },
            }}
        >
            <Layout style={{ minHeight: '100vh' }}>
                <Header style={{ 
                    padding: '0 16px', 
                    background: '#ffffff',
                    borderBottom: '1px solid #e2e8f0',
                    height: '72px',
                    boxShadow: '0 1px 2px rgba(0, 0, 0, 0.04)',
                    display: 'flex',
                    justifyContent: 'center',
                }}>
                    <div
                        style={{
                            width: '100%',
                            maxWidth: '920px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: '12px',
                        }}
                    >
                        <Link href={route('dtr.index')}>
                            <Button
                                type="text"
                                icon={<FileTextOutlined style={{ fontSize: '18px' }} />}
                                style={{
                                    height: '42px',
                                    borderRadius: '10px',
                                    border: '1px solid #dbeafe',
                                    background: '#eff6ff',
                                    color: '#1d4ed8',
                                    fontSize: '18px',
                                    fontWeight: 700,
                                    paddingInline: '14px',
                                }}
                            >
                                DTR System
                            </Button>
                        </Link>

                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <Dropdown menu={{ items: userMenuItems }} placement="bottomRight" trigger={['click']}>
                                <Space style={{ cursor: 'pointer' }}>
                                    <Avatar
                                        size={32}
                                        icon={<UserOutlined />}
                                        style={{ background: '#1d4ed8' }}
                                    />
                                    <span style={{ fontSize: '14px', fontWeight: 500, color: '#0f172a' }}>
                                        {user.name}
                                    </span>
                                </Space>
                            </Dropdown>
                        </div>
                    </div>
                </Header>

                <Content style={{ 
                    minHeight: 'calc(100vh - 72px)',
                    padding: '28px 16px',
                    display: 'flex',
                    justifyContent: 'center',
                }}>
                    <div
                        style={{
                            width: '100%',
                            maxWidth: '920px',
                            background: '#ffffff',
                            padding: '28px',
                            borderRadius: '14px',
                            boxShadow: '0 1px 3px rgba(0, 0, 0, 0.08)',
                        }}
                    >
                        {children}
                    </div>
                </Content>
            </Layout>
        </ConfigProvider>
    );
}
