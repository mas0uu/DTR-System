import { PageProps as AppPageProps } from '@/types';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/ui/PageHeader';
import TableCard from '@/Components/ui/TableCard';
import { Head, router, usePage } from '@inertiajs/react';
import { Alert, Avatar, Button, Space, Tag } from 'antd';
import { ChangeEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit() {
    const { auth, flash } = usePage<AppPageProps & { flash?: { success?: string } }>().props;
    const user = auth.user;
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);
    const [uploadingPhoto, setUploadingPhoto] = useState(false);
    const [removingPhoto, setRemovingPhoto] = useState(false);
    const [photoError, setPhotoError] = useState<string | null>(null);
    const isAdmin = user.role === 'admin' || !!user.is_admin;
    const roleLabel = isAdmin ? 'Admin' : user.role === 'intern' ? 'Intern' : 'Regular Employee';
    const roleColor = isAdmin ? 'red' : user.role === 'intern' ? 'green' : 'blue';
    const displayName = user.student_name || user.name || 'User';
    const initials = displayName
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'U';
    const avatarSrc = localPreviewUrl || user.profile_photo_url || undefined;
    const hasProfilePhoto = Boolean(localPreviewUrl || user.profile_photo_url || user.profile_photo_path);

    const clearFileInput = () => {
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const revokePreview = (previewUrl: string | null) => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    };

    useEffect(() => {
        return () => {
            revokePreview(localPreviewUrl);
        };
    }, [localPreviewUrl]);

    useEffect(() => {
        if (user.profile_photo_url && localPreviewUrl) {
            revokePreview(localPreviewUrl);
            setLocalPreviewUrl(null);
        }
    }, [user.profile_photo_url]);

    const openFilePicker = () => {
        if (uploadingPhoto) return;
        setPhotoError(null);
        fileInputRef.current?.click();
    };

    const handleAvatarKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openFilePicker();
        }
    };

    const handlePhotoSelected = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        revokePreview(localPreviewUrl);
        setLocalPreviewUrl(previewUrl);

        setUploadingPhoto(true);
        router.post(route('profile.photo.update'), {
            photo: file,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setPhotoError(null);
                clearFileInput();
            },
            onError: (errors) => {
                revokePreview(previewUrl);
                setLocalPreviewUrl(null);
                setPhotoError(typeof errors.photo === 'string' ? errors.photo : 'Photo upload failed.');
            },
            onFinish: () => setUploadingPhoto(false),
        });
    };

    const handleRemovePhoto = () => {
        if (!hasProfilePhoto || removingPhoto) return;

        setRemovingPhoto(true);
        router.delete(route('profile.photo.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                revokePreview(localPreviewUrl);
                setLocalPreviewUrl(null);
                setPhotoError(null);
                clearFileInput();
            },
            onFinish: () => setRemovingPhoto(false),
        });
    };

    return (
        <>
            <Head title="Profile" />

            <div className="space-y-6">
                <PageHeader
                    title="Profile"
                    subtitle="Manage your personal information and account settings."
                />
                {flash?.success && (
                    <Alert
                        type="info"
                        showIcon
                        message={flash.success}
                    />
                )}

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <div className="space-y-6 xl:col-span-4">
                        <TableCard title="Profile Overview" subtitle="Account identity and profile photo controls.">
                            <div className="flex flex-col items-center gap-4 md:flex-row md:items-start">
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="hidden"
                                    onChange={handlePhotoSelected}
                                />
                                <div
                                    className="relative cursor-pointer"
                                    role="button"
                                    tabIndex={0}
                                    onClick={openFilePicker}
                                    onKeyDown={handleAvatarKeyDown}
                                    aria-label="Upload profile photo"
                                >
                                    <Avatar
                                        size={108}
                                        src={avatarSrc}
                                        className="ring-4 ring-slate-100 transition hover:ring-slate-200"
                                    >
                                        {initials}
                                    </Avatar>
                                </div>
                                <div className="min-w-0 flex-1 text-center md:text-left">
                                    <h3 className="truncate text-lg font-semibold text-slate-900">{displayName}</h3>
                                    <p className="truncate text-sm text-slate-500">{user.email}</p>
                                    <Space wrap className="mt-3">
                                        <Tag color={roleColor}>{roleLabel}</Tag>
                                        {user.department && <Tag>{user.department}</Tag>}
                                        {user.employee_type && <Tag>{String(user.employee_type).toUpperCase()}</Tag>}
                                    </Space>
                                    <div className="mt-4 flex flex-wrap justify-center gap-2 md:justify-start">
                                        <Button onClick={openFilePicker} loading={uploadingPhoto}>
                                            {hasProfilePhoto ? 'Change Photo' : 'Upload Photo'}
                                        </Button>
                                        {hasProfilePhoto && (
                                            <Button danger type="text" onClick={handleRemovePhoto} loading={removingPhoto}>
                                                Remove Photo
                                            </Button>
                                        )}
                                    </div>
                                    <p className="mt-2 text-xs text-slate-500">JPG, PNG, or WEBP. Max file size: 2MB.</p>
                                    <InputError className="mt-2" message={photoError || undefined} />
                                </div>
                            </div>
                        </TableCard>
                    </div>

                    <div className="space-y-6 xl:col-span-8">
                        <TableCard title="Personal Information" subtitle="Update your identity and work details.">
                            <UpdateProfileInformationForm />
                        </TableCard>

                        <TableCard title="Security" subtitle="Change your password and keep your account protected.">
                            <UpdatePasswordForm />
                        </TableCard>
                    </div>
                </div>
            </div>
        </>
    );
}
