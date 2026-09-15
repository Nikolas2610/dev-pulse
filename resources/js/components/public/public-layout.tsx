import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Menu, X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

type PublicPage = 'home' | 'product' | 'integrations' | 'about';

const navigation: { label: string; href: string; key: PublicPage }[] = [
    { label: 'Product', href: '/product', key: 'product' },
    { label: 'Integrations', href: '/integrations', key: 'integrations' },
    { label: 'About', href: '/about', key: 'about' },
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
        <div className="public-site min-h-[100dvh] overflow-x-hidden bg-[#07131f] text-[#f3f7fa]">
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
                    <button
                        type="button"
                        className="focus-public rounded-lg border border-white/12 p-2 text-slate-200 md:hidden"
                        aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                        aria-expanded={menuOpen}
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        {menuOpen ? (
                            <X className="size-5" aria-hidden="true" />
                        ) : (
                            <Menu className="size-5" aria-hidden="true" />
                        )}
                    </button>
                </div>
                {menuOpen && (
                    <div className="border-t border-white/8 bg-[#07131f] px-5 py-5 md:hidden">
                        <nav
                            className="mx-auto grid max-w-[1400px] gap-1"
                            aria-label="Mobile navigation"
                        >
                            {navigation.map((item) => (
                                <Link
                                    key={item.key}
                                    href={item.href}
                                    aria-current={
                                        current === item.key
                                            ? 'page'
                                            : undefined
                                    }
                                    className="focus-public rounded-lg px-3 py-3 text-base text-slate-200 hover:bg-white/5"
                                    onClick={() => setMenuOpen(false)}
                                >
                                    {item.label}
                                </Link>
                            ))}
                            <div className="mt-4 grid grid-cols-2 gap-2 border-t border-white/8 pt-4">
                                {!user && (
                                    <Link
                                        href="/login"
                                        className="focus-public rounded-lg border border-white/12 px-4 py-3 text-center text-sm font-semibold"
                                    >
                                        Log in
                                    </Link>
                                )}
                                <Link
                                    href={actionHref}
                                    className={`focus-public rounded-lg bg-cyan-300 px-4 py-3 text-center text-sm font-semibold text-[#07131f] ${user ? 'col-span-2' : ''}`}
                                >
                                    {actionLabel}
                                </Link>
                            </div>
                        </nav>
                    </div>
                )}
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
