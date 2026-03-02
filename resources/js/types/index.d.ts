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
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
