import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Building2,
    CalendarClock,
    Check,
    FileText,
    PlugZap,
    Users,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

const steps = [
    {
        key: 'account',
        label: 'Account',
        detail: 'Your admin access',
        icon: Activity,
    },
    {
        key: 'organization',
        label: 'Organization',
        detail: 'Team and workspace',
        icon: Building2,
    },
    {
        key: 'integrations',
        label: 'Integrations',
        detail: 'Connect delivery',
        icon: PlugZap,
    },
    {
        key: 'members',
        label: 'Members',
        detail: 'Invite the team',
        icon: Users,
    },
    {
        key: 'template',
        label: 'Template',
        detail: 'Shape the questions',
        icon: FileText,
    },
    {
        key: 'schedule',
        label: 'Schedule',
        detail: 'Set the first pulse',
        icon: CalendarClock,
    },
];

type OnboardingPageProps = {
    auth?: { user?: { name: string; email: string } | null };
    onboarding?: { currentStep: string; furthestStep: string };
};

export default function OnboardingLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const page = usePage<OnboardingPageProps>();
    const currentStep = page.props.onboarding?.currentStep ?? 'organization';
    const furthestStep = page.props.onboarding?.furthestStep ?? 'organization';
    const currentIndex = steps.findIndex((step) => step.key === currentStep);
    const furthestIndex = Math.max(
        0,
        steps.findIndex((step) => step.key === furthestStep),
    );

    return (
        <div className="min-h-svh overflow-x-hidden bg-[radial-gradient(circle_at_88%_8%,color-mix(in_oklab,var(--accent)_48%,transparent),transparent_28%),var(--background)]">
            <header className="border-b border-border/70 bg-background/80 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-[1480px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
                    <Link
                        href="/"
                        className="flex min-w-0 items-center gap-2.5 font-semibold tracking-tight"
                    >
                        <AppLogoIcon className="size-8 shrink-0 fill-current text-primary" />
                        <span className="truncate">DevPulse</span>
                    </Link>
                    {page.props.auth?.user && (
                        <div className="flex shrink-0 items-center gap-3 text-sm">
                            <div className="hidden text-right sm:block">
                                <div className="font-medium">
                                    {page.props.auth.user.name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Setup administrator
                                </div>
                            </div>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="rounded-md border bg-card px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Log out
                            </Link>
                        </div>
                    )}
                </div>
            </header>

            <div className="mx-auto grid min-h-[calc(100svh-4rem)] max-w-[1480px] lg:grid-cols-[310px_minmax(0,1fr)]">
                <aside className="min-w-0 border-b border-border/70 px-4 py-4 sm:px-6 lg:border-r lg:border-b-0 lg:px-8 lg:py-10">
                    <div className="mb-6 hidden lg:block">
                        <div className="font-mono text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">
                            Setup signal
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold tracking-[-0.035em]">
                            From zero to first pulse.
                        </h1>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            Configure the smallest useful system for a team that
                            checks in and keeps moving.
                        </p>
                    </div>

                    <nav
                        aria-label="Onboarding progress"
                        className="-mx-4 overflow-x-auto px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:overflow-visible lg:px-0"
                    >
                        <ol className="flex min-w-max gap-2 lg:min-w-0 lg:flex-col lg:gap-0">
                            {steps.map((step, index) => {
                                const Icon = step.icon;
                                const complete =
                                    index < currentIndex ||
                                    (furthestStep === 'complete' &&
                                        index <= furthestIndex);
                                const active = index === currentIndex;
                                const available =
                                    index > 0 && index <= furthestIndex;
                                const content = (
                                    <div
                                        className={cn(
                                            'group relative flex min-w-32 items-center gap-2.5 rounded-lg px-3 py-2.5 text-left transition-colors sm:min-w-40 sm:gap-3 lg:min-w-0 lg:rounded-none lg:px-0 lg:py-3',
                                            active
                                                ? 'bg-primary/8 lg:bg-transparent'
                                                : 'hover:bg-accent/45 lg:hover:bg-transparent',
                                        )}
                                    >
                                        {index < steps.length - 1 && (
                                            <span
                                                className={cn(
                                                    'absolute top-9 left-[18px] hidden h-[calc(100%-1.15rem)] w-px lg:block',
                                                    complete
                                                        ? 'bg-primary'
                                                        : 'bg-border',
                                                )}
                                            />
                                        )}
                                        <span
                                            className={cn(
                                                'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border bg-background transition-colors',
                                                active &&
                                                    'border-primary bg-primary text-primary-foreground shadow-[0_0_0_5px_color-mix(in_oklab,var(--primary)_12%,transparent)]',
                                                complete &&
                                                    !active &&
                                                    'border-primary bg-primary/10 text-primary',
                                            )}
                                        >
                                            {complete && !active ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <Icon className="size-4" />
                                            )}
                                        </span>
                                        <span className="min-w-0">
                                            <span
                                                className={cn(
                                                    'block text-sm font-medium',
                                                    active
                                                        ? 'text-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {step.label}
                                            </span>
                                            <span className="hidden text-xs text-muted-foreground lg:block">
                                                {step.detail}
                                            </span>
                                        </span>
                                    </div>
                                );

                                return (
                                    <li key={step.key}>
                                        {available ? (
                                            <Link
                                                href={`/onboarding?step=${step.key}`}
                                            >
                                                {content}
                                            </Link>
                                        ) : (
                                            content
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>
                </aside>

                <main className="flex min-w-0 items-start justify-center px-3 py-6 sm:px-8 sm:py-8 lg:px-12 lg:py-12">
                    <div className="w-full max-w-5xl animate-in duration-500 fade-in slide-in-from-bottom-2 motion-reduce:animate-none">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
