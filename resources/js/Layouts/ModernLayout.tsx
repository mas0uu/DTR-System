import { ReactNode, useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarOutlined,
    DollarOutlined,
    FileTextOutlined,
    LogoutOutlined,
    MenuOutlined,
    MoonOutlined,
    SettingOutlined,
    SunOutlined,
    TeamOutlined,
    UnorderedListOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { Avatar, Button, ConfigProvider, Drawer, Dropdown, Layout, Menu, Space, theme } from 'antd';
import type { MenuProps } from 'antd';
import { useThemeMode } from '@/lib/theme';

const { Sider, Header, Content } = Layout;

interface ModernLayoutProps {
    children: ReactNode;
}

export default function ModernLayout({ children }: ModernLayoutProps) {
    const page = usePage();
    const { isDark, toggleTheme } = useThemeMode();
    const user = page.props.auth.user;
    const displayName = user.student_name || user.name || 'User';
    const avatarInitials = useMemo(() => {
        return displayName
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part: string) => part[0]?.toUpperCase() ?? '')
            .join('') || 'U';
    }, [displayName]);
    const isAdmin = user.role === 'admin' || !!user.is_admin;
    const isIntern = user.employee_type === 'intern';
    const canAccessPayroll = !isIntern || !!user.intern_compensation_enabled;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [avatarImageError, setAvatarImageError] = useState(false);
    const profilePhotoSrc = useMemo(() => {
        if (!user.profile_photo_url) {
            return null;
        }

        const separator = user.profile_photo_url.includes('?') ? '&' : '?';
        const cacheKey = user.profile_photo_path ?? user.profile_photo_url;

        return `${user.profile_photo_url}${separator}v=${encodeURIComponent(cacheKey)}`;
    }, [user.profile_photo_path, user.profile_photo_url]);

    useEffect(() => {
        setAvatarImageError(false);
    }, [profilePhotoSrc]);
    const currentTab = useMemo(() => {
        const queryString = page.url.includes('?') ? page.url.split('?')[1] : '';
        const params = new URLSearchParams(queryString);

        return params.get('tab') || '1';
    }, [page.url]);

    const navItems: Array<{
        key: string;
        href: string;
        label: string;
        icon: ReactNode;
        isActive: boolean;
    }> = isAdmin
        ? [
            {
                key: 'employees',
                href: route('admin.employees.index'),
                label: 'Employees',
                icon: <TeamOutlined />,
                isActive: route().current('admin.employees.*'),
            },
            {
                key: 'attendance',
                href: route('admin.attendance.index'),
                label: 'Attendance',
                icon: <CalendarOutlined />,
                isActive: route().current('admin.attendance.*') && !route().current('admin.attendance.logs'),
            },
            {
                key: 'attendance-logs',
                href: route('admin.attendance.logs'),
                label: 'Attendance Logs',
                icon: <UnorderedListOutlined />,
                isActive: route().current('admin.attendance.logs'),
            },
            {
                key: 'anomalies',
                href: route('admin.anomalies.index'),
                label: 'Anomalies',
                icon: <UnorderedListOutlined />,
                isActive: route().current('admin.anomalies.*'),
            },
            {
                key: 'password-resets',
                href: route('admin.password_reset_requests.index'),
                label: 'Password Resets',
                icon: <SettingOutlined />,
                isActive: route().current('admin.password_reset_requests.*'),
            },
            {
                key: 'leaves',
                href: route('admin.leaves.index'),
                label: 'Leave Requests',
                icon: <CalendarOutlined />,
                isActive: route().current('admin.leaves.*'),
            },
            {
                key: 'holidays',
                href: route('admin.holidays.index'),
                label: 'Holidays',
                icon: <CalendarOutlined />,
                isActive: route().current('admin.holidays.*'),
            },
            {
                key: 'payroll',
                href: route('admin.payroll.index'),
                label: 'Payroll',
                icon: <DollarOutlined />,
                isActive: route().current('admin.payroll.*'),
            },
            {
                key: 'intern-progress',
                href: route('admin.intern_progress.index'),
                label: 'Intern Progress',
                icon: <TeamOutlined />,
                isActive: route().current('admin.intern_progress.*'),
            },
            {
                key: 'audit',
                href: route('admin.audit.index'),
                label: 'Audit Trail',
                icon: <SettingOutlined />,
                isActive: route().current('admin.audit.*'),
            },
        ]
        : [
            {
                key: 'dtr-home',
                href: route('dtr.index', { tab: '1' }),
                label: 'Home',
                icon: <FileTextOutlined />,
                isActive: route().current('dtr.index') && currentTab === '1',
            },
            {
                key: 'dtr-monthly',
                href: route('dtr.index', { tab: '2' }),
                label: 'Monthly Records',
                icon: <CalendarOutlined />,
                isActive: (route().current('dtr.index') && currentTab === '2') || route().current('dtr.months.show'),
            },
            {
                key: 'dtr-user-info',
                href: route('dtr.index', { tab: '3' }),
                label: 'User Information',
                icon: <UserOutlined />,
                isActive: route().current('dtr.index') && currentTab === '3',
            },
            {
                key: 'leaves',
                href: route('leaves.index'),
                label: isIntern ? 'Absence Requests' : 'Leave Requests',
                icon: <CalendarOutlined />,
                isActive: route().current('leaves.*'),
            },
            {
                key: 'holidays',
                href: route('holidays.index'),
                label: 'Holidays',
                icon: <CalendarOutlined />,
                isActive: route().current('holidays.*'),
            },
            {
                key: 'payroll',
                href: route('payroll.index'),
                label: 'Payroll',
                icon: <DollarOutlined />,
                isActive: route().current('payroll.*') || (route().current('dtr.index') && currentTab === '4'),
            },
        ].filter((item) => canAccessPayroll || item.key !== 'payroll');

    const selectedNavKey = useMemo(() => {
        const matched = navItems.find((item) => item.isActive);
        return matched?.key ?? navItems[0]?.key;
    }, [navItems]);
    const pageTitle = useMemo(() => {
        const activeItem = navItems.find((item) => item.key === selectedNavKey);
        if (!activeItem) {
            return isAdmin ? 'Admin Dashboard' : 'Daily Time Records';
        }

        if (activeItem.key === 'dtr-home') {
            return 'Daily Time Records';
        }

        return activeItem.label;
    }, [isAdmin, navItems, selectedNavKey]);

    const buildMenuItems = (closeOnClick: boolean): MenuProps['items'] =>
        navItems.map((item) => ({
            key: item.key,
            icon: item.icon,
            label: (
                <Link href={item.href} onClick={closeOnClick ? () => setMobileNavOpen(false) : undefined}>
                    {item.label}
                </Link>
            ),
        }));

    const userMenuItems: MenuProps['items'] = [
        {
            key: 'profile',
            icon: <SettingOutlined />,
            label: 'Settings',
        },
        {
            type: 'divider',
        },
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: 'Sign out',
        },
    ];
    const handleUserMenuClick: MenuProps['onClick'] = ({ key }) => {
        if (key === 'profile') {
            router.visit(route('profile.edit'));
            return;
        }

        if (key === 'logout') {
            router.post(route('logout'));
        }
    };

    return (
        <ConfigProvider
            theme={{
                algorithm: isDark ? theme.darkAlgorithm : theme.defaultAlgorithm,
                token: {
                    colorPrimary: isDark ? '#4BB9D2' : '#00415f',
                    borderRadius: 10,
                    fontSize: 13,
                    fontFamily: 'Manrope, Segoe UI, Tahoma, Verdana, sans-serif',
                },
                components: {
                    Layout: {
                        colorBgHeader: 'var(--xp-panel)',
                        colorBgBody: 'transparent',
                    },
                    Menu: {
                        itemBg: 'var(--xp-panel-soft)',
                        itemSelectedBg: 'var(--xp-hover-bg)',
                        itemSelectedColor: 'var(--xp-primary)',
                        itemColor: 'var(--xp-text)',
                        itemHoverBg: 'var(--xp-hover-bg)',
                        itemHoverColor: 'var(--xp-text)',
                        itemHeight: 38,
                    },
                },
            }}
        >
            <Layout className="app-shell" style={{ minHeight: '100vh' }}>
                <Sider
                    width={236}
                    theme={isDark ? 'dark' : 'light'}
                    className="hidden lg:block"
                    style={{ borderRight: '1px solid var(--xp-line)', background: 'var(--xp-panel-soft)' }}
                >
                    <div className="px-4 py-5">
                        <Link
                            href={isAdmin ? route('admin.employees.index') : route('dtr.index')}
                            className="block p-0"
                        >
                            <img
                                src="/images/doxsys-logo-full.png"
                                alt="Doxsys"
                                className="h-8 w-auto object-contain"
                            />
                        </Link>
                    </div>

                    <div className="px-3">
                        <Menu
                            mode="inline"
                            selectedKeys={selectedNavKey ? [selectedNavKey] : []}
                            items={buildMenuItems(false)}
                            style={{ borderInlineEnd: 0, background: 'var(--xp-panel-soft)' }}
                        />
                    </div>
                </Sider>

                <Layout>
                    <Header
                        className="liquid-header"
                        style={{
                            padding: '0 16px',
                            height: '64px',
                            borderBottom: '1px solid var(--xp-line)',
                            display: 'flex',
                            justifyContent: 'center',
                        }}
                    >
                        <div
                            style={{
                                width: '100%',
                                maxWidth: isAdmin ? '1240px' : '1240px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: '12px',
                            }}
                        >
                            <div className="flex items-center gap-2.5">
                                <Button
                                    className="lg:hidden"
                                    type="text"
                                    icon={<MenuOutlined />}
                                    onClick={() => setMobileNavOpen(true)}
                                />
                                <img
                                    src="/images/doxsys-logo-icon.png"
                                    alt="Doxsys"
                                    className="hidden h-7 w-auto object-contain sm:block"
                                />
                                <span className="text-base font-semibold" style={{ color: 'var(--xp-text)' }}>{pageTitle}</span>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    type="default"
                                    icon={isDark ? <SunOutlined /> : <MoonOutlined />}
                                    onClick={toggleTheme}
                                    className="theme-toggle-btn"
                                >
                                    <span className="hidden sm:inline">{isDark ? 'Light' : 'Dark'}</span>
                                </Button>

                                <Dropdown menu={{ items: userMenuItems, onClick: handleUserMenuClick }} placement="bottomRight" trigger={['click']}>
                                    <Space style={{ cursor: 'pointer' }}>
                                        <Avatar
                                            size={32}
                                            style={{ background: 'var(--xp-primary)', overflow: 'hidden' }}
                                            src={profilePhotoSrc && !avatarImageError ? profilePhotoSrc : undefined}
                                            onError={() => {
                                                setAvatarImageError(true);
                                                return false;
                                            }}
                                        >
                                            {avatarInitials}
                                        </Avatar>
                                        <span style={{ fontSize: '14px', fontWeight: 500, color: 'var(--xp-text)' }}>
                                            {displayName}
                                        </span>
                                    </Space>
                                </Dropdown>
                            </div>
                        </div>
                    </Header>

                    <Content
                        style={{
                            minHeight: 'calc(100vh - 64px)',
                            padding: '20px 14px',
                            display: 'flex',
                            justifyContent: 'center',
                        }}
                    >
                        <div
                            style={{
                                width: '100%',
                                maxWidth: isAdmin ? '1240px' : '1240px',
                                padding: '10px',
                            }}
                        >
                            {children}
                        </div>
                    </Content>
                </Layout>
            </Layout>

            <Drawer
                title="Navigation"
                placement="left"
                width={260}
                open={mobileNavOpen}
                onClose={() => setMobileNavOpen(false)}
                bodyStyle={{ padding: 12 }}
            >
                <div className="mb-3">
                    <Link
                        href={isAdmin ? route('admin.employees.index') : route('dtr.index')}
                        onClick={() => setMobileNavOpen(false)}
                        className="block p-0"
                    >
                        <img
                            src="/images/doxsys-logo-full.png"
                            alt="Doxsys"
                            className="h-8 w-auto object-contain"
                        />
                    </Link>
                </div>
                <Menu
                    mode="inline"
                    selectedKeys={selectedNavKey ? [selectedNavKey] : []}
                    items={buildMenuItems(true)}
                    style={{ borderInlineEnd: 0 }}
                />
            </Drawer>
        </ConfigProvider>
    );
}
