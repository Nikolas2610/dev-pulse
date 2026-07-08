import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name = 'DevPulse' } = usePage().props;
    const appName =
        typeof name === 'string' && name !== 'Laravel' ? name : 'DevPulse';

    return (
        <>
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                <AppLogoIcon className="size-9" />
            </span>
            <div className="ml-2 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {appName}
                </span>
            </div>
        </>
    );
}
