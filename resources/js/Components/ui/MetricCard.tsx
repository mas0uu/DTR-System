import type { ReactNode } from 'react';
import { Card } from 'antd';

type MetricCardProps = {
    label: ReactNode;
    value: ReactNode;
    helper?: ReactNode;
    className?: string;
    valueClassName?: string;
};

export default function MetricCard({
    label,
    value,
    helper,
    className = '',
    valueClassName = '',
}: MetricCardProps) {
    return (
        <Card size="small" className={`h-full rounded-xl border-slate-200 ${className}`.trim()}>
            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`text-[24px] font-semibold leading-tight text-slate-900 ${valueClassName}`.trim()}>{value}</p>
            {helper ? <p className="mt-2 text-xs text-slate-500">{helper}</p> : null}
        </Card>
    );
}
