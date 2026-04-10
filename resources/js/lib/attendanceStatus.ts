export const attendanceStatusColor = (status: string): string => {
    const normalized = String(status || '').trim().toLowerCase();

    if (normalized === 'timed in') return 'green';
    if (normalized === 'timed out') return 'blue';
    if (normalized === 'late') return 'orange';
    if (normalized === 'half day') return 'gold';
    if (normalized === 'undertime') return 'volcano';
    if (normalized === 'overtime') return 'cyan';
    if (normalized === 'long break') return 'magenta';
    if (normalized === 'extreme late') return 'red';
    return 'default';
};

export const resolveDisplayRowState = (row: {
    status: string;
    holiday?: string | null;
    time_in?: string | null;
    time_out?: string | null;
}): string => {
    const isBlankHoliday = Boolean(row.holiday && !row.time_in && !row.time_out);

    return isBlankHoliday ? 'holiday' : row.status;
};

export const rowStateColor = (status: string): string => {
    const normalized = String(status || '').trim().toLowerCase();

    if (normalized === 'draft') return 'default';
    if (normalized === 'in_progress') return 'blue';
    if (normalized === 'finished') return 'green';
    if (normalized === 'holiday') return 'gold';
    if (normalized === 'leave') return 'gold';
    if (normalized === 'missed') return 'red';
    return 'default';
};

export const rowStateLabel = (status: string): string => status.replace(/_/g, ' ').toUpperCase();
