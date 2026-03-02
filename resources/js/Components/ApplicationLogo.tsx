import { HTMLAttributes } from 'react';

export default function ApplicationLogo({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            {...props}
            className={
                'inline-flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-2 text-blue-700 ' +
                className
            }
        >
            <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <path d="M6 3H14L19 8V21H6V3Z" stroke="currentColor" strokeWidth="1.8" />
                <path d="M14 3V8H19" stroke="currentColor" strokeWidth="1.8" />
                <path d="M9 12H16" stroke="currentColor" strokeWidth="1.8" />
                <path d="M9 16H16" stroke="currentColor" strokeWidth="1.8" />
            </svg>
            <span className="text-3xl font-semibold tracking-tight">
                DTR <span className="font-bold">System</span>
            </span>
        </div>
    );
}
