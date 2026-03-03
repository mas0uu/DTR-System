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
                    borderRadius: 8,
                    fontSize: 14,
                    fontFamily: 'Tahoma, Segoe UI, Verdana, sans-serif',
                },
                components: {
                    Layout: {
                        colorBgHeader: '#eef5ff',
                        colorBgBody: 'transparent',
                    },
                    Menu: {
                        itemBg: '#f7fbff',
                        itemSelectedBg: '#dce9ff',
                        itemSelectedColor: '#1f4f9e',
                        itemColor: '#355a8c',
                        itemHoverBg: '#eaf2ff',
                        itemHoverColor: '#1f4f9e',
                    },
                },
            }}
        >
            <Layout className="app-shell" style={{ minHeight: '100vh' }}>
                <Header className="liquid-header" style={{ 
                    padding: '0 16px', 
                    height: '72px',
                    boxShadow: '0 10px 36px rgba(15, 23, 42, 0.06)',
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
                                className="liquid-pill"
                                icon={<FileTextOutlined style={{ fontSize: '18px' }} />}
                                style={{
                                    height: '42px',
                                    borderRadius: '10px',
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
                                    <span style={{ fontSize: '14px', fontWeight: 500, color: '#1f365d' }}>
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
                        className="glass-panel"
                        style={{
                            width: '100%',
                            maxWidth: '920px',
                            padding: '28px',
                        }}
                    >
                        {children}
                    </div>
                </Content>
            </Layout>
        </ConfigProvider>
    );
}
