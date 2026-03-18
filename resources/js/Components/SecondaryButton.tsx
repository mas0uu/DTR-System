import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-md border border-[#d7e3e8] bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition duration-150 ease-in-out hover:-translate-y-px hover:border-[#4BB9D2] hover:bg-[#edf5f8] hover:text-[#00415f] focus:outline-none focus:ring-2 focus:ring-[#4BB9D2] focus:ring-offset-2 disabled:opacity-25 active:translate-y-px ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
