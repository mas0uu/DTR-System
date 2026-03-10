import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: ReactNode;
    subtitle?: ReactNode;
    actions?: ReactNode;
    className?: string;
};

export default function PageHeader({ title, subtitle, actions, className = '' }: PageHeaderProps) {
    return (
        <div className={`mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between ${className}`.trim()}>
            <div className="min-w-0">
                <h1 className="text-[26px] font-semibold tracking-tight text-slate-900">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
