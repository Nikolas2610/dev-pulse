import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="DevPulse"
        >
            <rect width="40" height="40" rx="10" fill="#07131F" />
            <path
                d="M11.5 10.5h8.2c5.7 0 9.8 3.9 9.8 9.5s-4.1 9.5-9.8 9.5h-8.2v-19Zm7.9 14.9c3.1 0 5.2-2.2 5.2-5.4s-2.1-5.4-5.2-5.4h-3v10.8h3Z"
                fill="#F8FAFC"
            />
            <path
                d="M12.2 21.5h5.1l2-5.2 3.6 9.4 2.2-4.2h4.7"
                fill="none"
                stroke="#22D3EE"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2.4"
            />
            <path
                d="M31 8.8v22.4"
                stroke="#A3E635"
                strokeLinecap="round"
                strokeWidth="2.6"
            />
        </svg>
    );
}
