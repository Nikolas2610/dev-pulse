import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

export default function Welcome() {
    const { auth, name = 'DevPulse' } = usePage().props;
    const appName =
        typeof name === 'string' && name !== 'Laravel' ? name : 'DevPulse';
    const primaryHref = auth.user ? dashboard() : login();

    return (
        <>
            <Head title={appName} />
            <div className="flex min-h-screen flex-col overflow-x-hidden bg-[#07131f] text-white">
                <header className="mx-auto flex w-full max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-8 sm:py-5">
                    <Link
                        href={primaryHref}
                        className="flex min-w-0 items-center gap-2.5 sm:gap-3"
                    >
                        <AppLogoIcon className="size-9 shrink-0 sm:size-10" />
                        <span className="truncate text-base font-semibold tracking-normal sm:text-lg">
                            {appName}
                        </span>
                    </Link>

                    <nav className="flex shrink-0 items-center gap-1.5 text-sm sm:gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-md border border-white/15 px-3 py-2 font-medium text-white hover:border-cyan-300/70 sm:px-4"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-md border border-transparent px-3 py-2 font-medium text-white hover:border-white/15 sm:px-4"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-md bg-white px-3 py-2 font-medium text-[#07131f] hover:bg-cyan-100 sm:px-4"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto grid w-full max-w-7xl flex-1 content-start items-center gap-8 px-4 py-10 sm:px-8 sm:py-12 lg:grid-cols-[minmax(0,1fr)_520px] lg:content-center">
                    <section>
                        <div className="max-w-2xl">
                            <p className="mb-4 text-xs font-semibold tracking-[0.16em] text-cyan-300 uppercase sm:mb-5 sm:text-sm sm:tracking-[0.18em]">
                                Developer team rhythm
                            </p>
                            <h1 className="text-3xl leading-tight font-semibold tracking-normal sm:text-5xl lg:text-6xl">
                                Standups, schedules, and summaries in one pulse.
                            </h1>
                            <p className="mt-5 max-w-xl text-base leading-7 text-slate-300 sm:mt-6 sm:text-lg sm:leading-8">
                                {appName} helps teams coordinate standups,
                                schedules, workspaces, and Slack or Discord
                                delivery from one focused workspace.
                            </p>
                            <div className="mt-7 grid gap-3 min-[420px]:flex min-[420px]:flex-wrap sm:mt-8">
                                <Link
                                    href={primaryHref}
                                    className="rounded-md bg-cyan-300 px-5 py-3 text-center text-sm font-semibold text-[#07131f] hover:bg-cyan-200"
                                >
                                    {auth.user
                                        ? 'Open dashboard'
                                        : 'Get started'}
                                </Link>
                                {!auth.user && (
                                    <Link
                                        href={login()}
                                        className="rounded-md border border-white/15 px-5 py-3 text-center text-sm font-semibold hover:border-cyan-300/70"
                                    >
                                        Sign in
                                    </Link>
                                )}
                            </div>
                            <div className="mt-9 grid max-w-xl grid-cols-3 gap-2 text-sm text-slate-300 sm:mt-10 sm:gap-3">
                                <div className="border-l border-cyan-300/60 pl-3 sm:pl-4">
                                    <p className="text-xl font-semibold text-white sm:text-2xl">
                                        09:00
                                    </p>
                                    <p>Prompt window</p>
                                </div>
                                <div className="border-l border-lime-300/60 pl-3 sm:pl-4">
                                    <p className="text-xl font-semibold text-white sm:text-2xl">
                                        3
                                    </p>
                                    <p>Workspaces</p>
                                </div>
                                <div className="border-l border-white/20 pl-3 sm:pl-4">
                                    <p className="text-xl font-semibold text-white sm:text-2xl">
                                        Live
                                    </p>
                                    <p>Team pulse</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="hidden rounded-lg border border-white/10 bg-white/[0.04] p-4 shadow-2xl shadow-black/30 lg:block">
                        <div className="rounded-md border border-white/10 bg-[#0d1c2a] p-5">
                            <div className="mb-5 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-slate-400">
                                        Today
                                    </p>
                                    <p className="text-xl font-semibold">
                                        Engineering standup
                                    </p>
                                </div>
                                <span className="rounded-full bg-lime-300 px-3 py-1 text-xs font-semibold text-[#07131f]">
                                    Open
                                </span>
                            </div>
                            <div className="space-y-3">
                                {[
                                    [
                                        'Maya Chen',
                                        'Shipped Slack sync retry handling',
                                        'Done',
                                    ],
                                    [
                                        'Theo Martin',
                                        'Reviewing schedule visibility rules',
                                        'Now',
                                    ],
                                    [
                                        'Ari Novak',
                                        'Blocked on Discord webhook scope',
                                        'Flagged',
                                    ],
                                ].map(([person, update, status]) => (
                                    <div
                                        key={person}
                                        className="grid gap-3 rounded-md border border-white/10 bg-white/[0.03] p-4 sm:grid-cols-[1fr_auto]"
                                    >
                                        <div>
                                            <p className="font-semibold">
                                                {person}
                                            </p>
                                            <p className="mt-1 text-sm text-slate-300">
                                                {update}
                                            </p>
                                        </div>
                                        <span className="self-start rounded-full border border-cyan-300/30 px-3 py-1 text-xs text-cyan-200">
                                            {status}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-5 rounded-md bg-cyan-300 p-4 text-[#07131f]">
                                <p className="text-sm font-semibold">
                                    Summary ready for #engineering
                                </p>
                                <p className="mt-1 text-sm">
                                    2 shipped updates, 1 active review, 1
                                    blocker.
                                </p>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
