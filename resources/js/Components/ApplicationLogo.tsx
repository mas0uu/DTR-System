import { HTMLAttributes } from 'react';

export default function ApplicationLogo({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            {...props}
            className={
                'inline-flex items-center border-[#d7e3e8] bg-white px-3 py-2 shadow-sm ' +
                className
            }
        >
            <img
                src="/images/doxsys-logo-full.png"
                alt="Doxsys logo"
                className="h-9 w-auto object-contain"
            />
        </div>
    );
}
