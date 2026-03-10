export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    student_name?: string | null;
    student_no?: string | null;
    school?: string | null;
    required_hours?: number | null;
    company?: string | null;
    department?: string | null;
    supervisor_name?: string | null;
    supervisor_position?: string | null;
    employee_type?: 'intern' | 'regular' | null;
    intern_compensation_enabled?: boolean;
    starting_date?: string | null;
    working_days?: number[] | null;
    work_time_in?: string | null;
    work_time_out?: string | null;
    default_break_minutes?: number | null;
    salary_type?: 'monthly' | 'daily' | 'hourly' | null;
    salary_amount?: number | null;
    initial_paid_leave_days?: number | null;
    current_paid_leave_balance?: number | null;
    leave_reset_month?: number | null;
    leave_reset_day?: number | null;
    last_leave_refresh_year?: number | null;
    is_admin?: boolean;
    profile_photo_url?: string | null;
    profile_photo_path?: string | null;
    employment_status?: 'active' | 'inactive' | 'archived';
    deactivated_at?: string | null;
    archived_at?: string | null;
    status_reason?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
