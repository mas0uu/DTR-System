import type { ReactNode } from 'react';
import { Card } from 'antd';

type TableCardProps = {
    title?: ReactNode;
    subtitle?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
};

export default function TableCard({
    title,
    subtitle,
    actions,
    children,
    className = '',
}: TableCardProps) {
    return (
        <Card size="small" className={`ui-table-card ${className}`.trim()}>
            {(title || subtitle || actions) && (
                <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        {title ? <h2 className="text-base font-semibold text-slate-900">{title}</h2> : null}
                        {subtitle ? <p className="mt-1 text-sm text-slate-600">{subtitle}</p> : null}
                    </div>
                    {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
                </div>
            )}
            {children}
        </Card>
    );
}
