import { Head } from '@inertiajs/react';
import {
    ArrowRight,
    GitBranch,
    GitPullRequest,
    Hash,
    MessageCircle,
    RefreshCw,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { PublicAction, PublicLayout } from '@/components/public/public-layout';
import {
    MotionHero,
    MotionHeroItem,
    MotionReveal,
    MotionStagger,
    MotionStaggerItem,
} from '@/components/public/public-motion';

const integrations = [
    {
        name: 'Slack',
        kind: 'Communication',
        icon: Hash,
        body: 'Send standup prompts, reminders, and completed summaries to the right channels.',
    },
    {
        name: 'Discord',
        kind: 'Communication',
        icon: MessageCircle,
        body: 'Deliver team prompts and standup summaries through your Discord workspace.',
    },
    {
        name: 'Jira',
        kind: 'Work context',
        icon: RefreshCw,
        body: 'Bring assigned issue activity into the daily view and connect updates to active work.',
    },
    {
        name: 'Bitbucket',
        kind: 'Work context',
        icon: GitPullRequest,
        body: 'Include repository and pull request context alongside each team update.',
    },
];

export default function Integrations() {
    return (
        <PublicLayout current="integrations">
            <Head title="Integrations">
                <meta
                    name="description"
                    content="Connect DevPulse with Slack, Discord, Jira, and Bitbucket to keep standups close to the work."
                />
            </Head>
            <main>
                <section className="relative border-b border-white/8">
                    <div className="public-grid-bg absolute inset-0 opacity-35" />
                    <MotionHero className="relative mx-auto grid max-w-[1400px] items-center gap-14 px-5 py-20 md:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:px-10 lg:py-24">
                        <MotionHeroItem className="max-w-4xl">
                            <p className="mb-5 text-xs font-semibold tracking-[0.16em] text-cyan-300 uppercase">
                                Integrations
                            </p>
                            <h1 className="max-w-[14ch] text-5xl leading-[0.98] font-semibold tracking-[-0.055em] md:text-7xl">
                                Standups belong beside the work.
                            </h1>
                            <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                                Connect the conversation tools your team uses
                                with the issue and review context that explains
                                progress.
                            </p>
                        </MotionHeroItem>
                        <MotionHeroItem
                            className="grid grid-cols-[1fr_auto_1fr] items-center gap-3 rounded-xl border border-white/10 bg-[#0c1b29] p-4 shadow-[0_28px_80px_rgba(0,10,22,0.35)] sm:gap-5 sm:p-6"
                            aria-label="Integration flow from communication tools through DevPulse to work context"
                        >
                            <div className="grid gap-3">
                                {[
                                    [Hash, 'Slack'],
                                    [MessageCircle, 'Discord'],
                                ].map(([Icon, label]) => {
                                    const IntegrationIcon = Icon as typeof Hash;

                                    return (
                                        <div
                                            key={label as string}
                                            className="flex items-center gap-3 rounded-lg border border-white/8 bg-white/[0.025] p-3"
                                        >
                                            <IntegrationIcon className="size-4 text-cyan-300" />
                                            <span className="text-sm font-semibold">
                                                {label as string}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="grid justify-items-center gap-3 text-slate-500">
                                <ArrowRight className="size-4" />
                                <span className="flex size-14 items-center justify-center rounded-xl border border-cyan-300/20 bg-[#07131f] shadow-[0_0_40px_rgba(34,211,238,0.08)]">
                                    <AppLogoIcon className="size-10" />
                                </span>
                                <ArrowRight className="size-4" />
                            </div>
                            <div className="grid gap-3">
                                {[
                                    [RefreshCw, 'Jira'],
                                    [GitPullRequest, 'Bitbucket'],
                                ].map(([Icon, label]) => {
                                    const IntegrationIcon = Icon as typeof Hash;

                                    return (
                                        <div
                                            key={label as string}
                                            className="flex items-center gap-3 rounded-lg border border-white/8 bg-white/[0.025] p-3"
                                        >
                                            <IntegrationIcon className="size-4 text-cyan-300" />
                                            <span className="text-sm font-semibold">
                                                {label as string}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </MotionHeroItem>
                    </MotionHero>
                </section>

                <section className="mx-auto max-w-[1400px] px-5 py-20 md:px-8 md:py-28 lg:px-10">
                    <MotionStagger className="grid gap-4 md:grid-cols-2 lg:grid-cols-[1.15fr_0.85fr]">
                        {integrations.map(
                            ({ name, kind, icon: Icon, body }, index) => (
                                <MotionStaggerItem
                                    key={name}
                                    className="h-full"
                                >
                                    <article
                                        className={`relative h-full overflow-hidden rounded-xl border p-6 md:p-8 ${index === 0 || index === 3 ? 'border-cyan-300/15 bg-cyan-300/[0.05]' : 'border-white/10 bg-[#0c1b29]'}`}
                                    >
                                        <div className="flex items-start justify-between gap-6">
                                            <span className="flex size-12 items-center justify-center rounded-lg border border-white/10 bg-[#07131f] text-cyan-200">
                                                <Icon className="size-5" />
                                            </span>
                                            <span className="text-xs font-medium text-slate-500">
                                                {kind}
                                            </span>
                                        </div>
                                        <h2 className="mt-12 text-3xl font-semibold tracking-[-0.04em]">
                                            {name}
                                        </h2>
                                        <p className="mt-4 max-w-md leading-7 text-slate-400">
                                            {body}
                                        </p>
                                    </article>
                                </MotionStaggerItem>
                            ),
                        )}
                    </MotionStagger>
                </section>

                <section className="border-y border-white/8 bg-white/[0.018]">
                    <div className="mx-auto grid max-w-[1400px] gap-12 px-5 py-20 md:px-8 lg:grid-cols-[0.8fr_1.2fr] lg:px-10 lg:py-24">
                        <MotionReveal>
                            <GitBranch className="size-6 text-cyan-300" />
                            <h2 className="mt-7 max-w-[12ch] text-4xl leading-none font-semibold tracking-[-0.045em]">
                                Context moves in both directions.
                            </h2>
                        </MotionReveal>
                        <MotionReveal className="max-w-2xl text-lg leading-8 text-slate-300">
                            <p>
                                Communication integrations keep participation
                                close to the team. Work integrations help each
                                update arrive with the issue or review context
                                that shaped it.
                            </p>
                            <p className="mt-6 text-slate-400">
                                Workspace-level assignments keep channels,
                                projects, repositories, and team members
                                aligned.
                            </p>
                        </MotionReveal>
                    </div>
                </section>

                <MotionReveal className="mx-auto flex max-w-[1400px] flex-col items-start gap-8 px-5 py-20 md:px-8 lg:flex-row lg:items-end lg:justify-between lg:px-10 lg:py-24">
                    <div>
                        <h2 className="max-w-[14ch] text-4xl leading-none font-semibold tracking-[-0.045em] md:text-5xl">
                            Connect the tools. Keep the signal.
                        </h2>
                        <p className="mt-5 max-w-xl leading-7 text-slate-400">
                            Start with the communication channel your team
                            already checks every day.
                        </p>
                    </div>
                    <PublicAction />
                </MotionReveal>
            </main>
        </PublicLayout>
    );
}
