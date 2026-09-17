import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, ChevronRight, Menu } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

type PublicPage = 'home' | 'product' | 'integrations' | 'about';

const navigation: { label: string; href: string; key: PublicPage }[] = [
    { label: 'Product', href: '/product', key: 'product' },
    { label: 'Integrations', href: '/integrations', key: 'integrations' },
    { label: 'About', href: '/about', key: 'about' },
];

const mobileNavigation: {
    label: string;
    description: string;
    href: string;
    key: PublicPage;
}[] = [
    {
        label: 'Home',
        description: 'The latest from DevPulse',
        href: '/',
        key: 'home',
    },
    {
        label: 'Product',
        description: 'See the standup workflow',
        href: '/product',
        key: 'product',
    },
    {
        label: 'Integrations',
        description: 'Connect Slack, Jira, and Discord',
        href: '/integrations',
        key: 'integrations',
    },
    {
        label: 'About',
        description: 'Why DevPulse exists',
        href: '/about',
        key: 'about',
    },
];

export function PublicLayout({
    children,
    current,
}: {
    children: ReactNode;
    current: PublicPage;
}) {
    const { auth, name = 'DevPulse' } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const appName =
        typeof name === 'string' && name !== 'Laravel' ? name : 'DevPulse';
    const user = auth?.user;
    const actionHref = user ? '/dashboard' : '/register';
    const actionLabel = user ? 'Open dashboard' : 'Start free';

    return (
        <div className="public-site min-h-[100dvh] overflow-x-clip bg-[#07131f] text-[#f3f7fa]">
            <header className="sticky top-0 z-40 border-b border-white/8 bg-[#07131f]/92 backdrop-blur-xl">
                <div className="mx-auto flex h-[68px] max-w-[1400px] items-center justify-between px-5 md:px-8 lg:px-10">
                    <Link
                        href="/"
                        className="focus-public flex items-center gap-3 rounded-lg"
                        aria-label={`${appName} home`}
                    >
                        <AppLogoIcon className="size-9" />
                        <span className="text-[17px] font-semibold tracking-[-0.02em]">
                            {appName}
                        </span>
                    </Link>
                    <nav
                        className="hidden items-center gap-8 md:flex"
                        aria-label="Primary navigation"
                    >
                        {navigation.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                aria-current={
                                    current === item.key ? 'page' : undefined
                                }
                                className={`focus-public rounded-md text-sm transition-colors ${current === item.key ? 'text-cyan-300' : 'text-slate-300 hover:text-white'}`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="hidden items-center gap-2 md:flex">
                        {!user && (
                            <Link
                                href="/login"
                                className="focus-public rounded-lg px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:text-white"
                            >
                                Log in
                            </Link>
                        )}
                        <Link
                            href={actionHref}
                            className="focus-public inline-flex items-center gap-2 rounded-lg bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-[#07131f] transition hover:bg-cyan-200 active:translate-y-px"
                        >
                            {actionLabel}
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                    <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                        <SheetTrigger asChild>
                            <button
                                type="button"
                                className="focus-public inline-flex size-10 items-center justify-center rounded-lg border border-white/12 text-slate-200 transition-colors hover:border-white/20 hover:bg-white/5 md:hidden"
                                aria-label="Open menu"
                            >
                                <Menu className="size-5" aria-hidden="true" />
                            </button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            className="w-[min(92vw,420px)] max-w-[420px] gap-0 border-white/10 bg-[#07131f] p-0 text-[#f3f7fa] shadow-[-24px_0_80px_rgba(0,10,22,0.48)] md:hidden"
                            closeButtonClassName="top-5 right-5 inline-flex size-10 items-center justify-center rounded-lg border border-white/12 bg-transparent p-0 text-slate-300 opacity-100 hover:border-white/20 hover:bg-white/5 hover:text-white"
                        >
                            <SheetTitle className="sr-only">
                                Navigate {appName}
                            </SheetTitle>
                            <SheetDescription className="sr-only">
                                Explore the public pages or open your account.
                            </SheetDescription>

                            <div className="flex min-h-[76px] items-center gap-3 border-b border-white/8 px-5 pr-16">
                                <AppLogoIcon className="size-9" />
                                <div>
                                    <p className="text-[17px] font-semibold tracking-[-0.02em]">
                                        {appName}
                                    </p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Async standups, clear signals
                                    </p>
                                </div>
                            </div>

                            <nav
                                className="grid gap-2 px-4 py-6"
                                aria-label="Mobile navigation"
                            >
                                {mobileNavigation.map((item) => {
                                    const isCurrent = current === item.key;

                                    return (
                                        <SheetClose asChild key={item.key}>
                                            <Link
                                                href={item.href}
                                                aria-current={
                                                    isCurrent
                                                        ? 'page'
                                                        : undefined
                                                }
                                                className={`focus-public group relative flex min-h-[72px] items-center justify-between overflow-hidden rounded-xl border px-4 transition-colors ${isCurrent ? 'border-cyan-300/20 bg-cyan-300/[0.07] text-white' : 'border-transparent text-slate-200 hover:border-white/8 hover:bg-white/[0.035]'}`}
                                            >
                                                {isCurrent && (
                                                    <span
                                                        className="absolute inset-y-3 left-0 w-px bg-cyan-300"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                <span>
                                                    <span className="block text-lg font-semibold tracking-[-0.02em]">
                                                        {item.label}
                                                    </span>
                                                    <span className="mt-1 block text-sm text-slate-400">
                                                        {item.description}
                                                    </span>
                                                </span>
                                                <ChevronRight
                                                    className={`size-4 transition-transform group-hover:translate-x-0.5 ${isCurrent ? 'text-cyan-300' : 'text-slate-600'}`}
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        </SheetClose>
                                    );
                                })}
                            </nav>

                            <SheetFooter className="mt-auto gap-3 border-t border-white/8 bg-[#0a1825] p-5">
                                <p className="mb-1 text-sm leading-6 text-slate-400">
                                    Keep updates moving without another status
                                    meeting.
                                </p>
                                <SheetClose asChild>
                                    <Link
                                        href={actionHref}
                                        className="focus-public inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-cyan-300 px-5 text-sm font-semibold !text-[#07131f] transition hover:bg-cyan-200 active:translate-y-px"
                                    >
                                        {actionLabel}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </SheetClose>
                                {!user && (
                                    <SheetClose asChild>
                                        <Link
                                            href="/login"
                                            className="focus-public inline-flex min-h-12 items-center justify-center rounded-lg border border-white/12 px-5 text-sm font-semibold text-slate-200 transition-colors hover:border-white/20 hover:bg-white/5"
                                        >
                                            Log in
                                        </Link>
                                    </SheetClose>
                                )}
                            </SheetFooter>
                        </SheetContent>
                    </Sheet>
                </div>
            </header>
            {children}
            <footer className="border-t border-white/8">
                <div className="mx-auto grid max-w-[1400px] gap-10 px-5 py-12 md:grid-cols-[1fr_auto] md:items-end md:px-8 lg:px-10">
                    <div>
                        <Link
                            href="/"
                            className="focus-public inline-flex items-center gap-3 rounded-lg"
                        >
                            <AppLogoIcon className="size-8" />
                            <span className="font-semibold">{appName}</span>
                        </Link>
                        <p className="mt-4 max-w-sm text-sm leading-6 text-slate-400">
                            Async standups with enough context to move work
                            forward.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-400">
                        {navigation.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                className="focus-public rounded-md hover:text-white"
                            >
                                {item.label}
                            </Link>
                        ))}
                        <Link
                            href="/login"
                            className="focus-public rounded-md hover:text-white"
                        >
                            Log in
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}

export function PublicAction({ className = '' }: { className?: string }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <Link
            href={user ? '/dashboard' : '/register'}
            className={`focus-public inline-flex w-fit items-center gap-2 rounded-lg bg-cyan-300 px-5 py-3 text-sm font-semibold text-[#07131f] transition hover:bg-cyan-200 active:translate-y-px ${className}`}
        >
            {user ? 'Open dashboard' : 'Start free'}
            <ArrowRight className="size-4" aria-hidden="true" />
        </Link>
    );
}
