import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-[#003149] bg-[#00415f] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:-translate-y-px hover:bg-[#005a82] focus:bg-[#005a82] focus:outline-none focus:ring-2 focus:ring-[#4BB9D2] focus:ring-offset-2 active:translate-y-px active:bg-[#003149] ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
