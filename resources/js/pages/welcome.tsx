import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CalendarClock,
    GitPullRequest,
    MessageSquareText,
} from 'lucide-react';
import { PublicAction, PublicLayout } from '@/components/public/public-layout';
import {
    MotionHero,
    MotionHeroItem,
    MotionReveal,
    MotionStagger,
    MotionStaggerItem,
} from '@/components/public/public-motion';
import { PulseVisual } from '@/components/public/pulse-visual';

const workflow = [
    {
        icon: CalendarClock,
        title: 'Prompt on schedule',
        body: 'Open the right standup for each workspace and timezone.',
    },
    {
        icon: MessageSquareText,
        title: 'Collect focused updates',
        body: 'Keep answers structured without another calendar event.',
    },
    {
        icon: AlertTriangle,
        title: 'Route blockers early',
        body: 'Give managers the context to act before work stalls.',
    },
];

export default function Welcome() {
    return (
        <PublicLayout current="home">
            <Head title="Async standups for focused engineering teams">
                <meta
                    name="description"
                    content="DevPulse schedules async standups, surfaces blockers, and delivers focused summaries to your team tools."
                />
            </Head>
            <main>
                <section className="relative isolate overflow-hidden">
                    <div className="public-grid-bg absolute inset-0 -z-10 opacity-45" />
                    <div className="public-orbit absolute top-12 right-[-12rem] -z-10 size-[34rem] rounded-full bg-cyan-300/[0.07] blur-3xl" />
                    <MotionHero className="mx-auto grid min-h-[calc(100dvh-68px)] max-w-[1400px] items-center gap-12 px-5 py-16 md:px-8 lg:grid-cols-[minmax(0,0.92fr)_minmax(520px,1.08fr)] lg:px-10 lg:py-20">
                        <MotionHeroItem className="max-w-[650px]">
                            <p className="mb-5 text-xs font-semibold tracking-[0.16em] text-cyan-300 uppercase">
                                Async standups, complete context
                            </p>
                            <h1 className="max-w-[15ch] text-[clamp(2.8rem,6vw,5rem)] leading-[0.96] font-semibold tracking-[-0.06em]">
                                Keep work moving. Skip status meetings.
                            </h1>
                            <p className="mt-6 max-w-[540px] text-base leading-7 text-slate-300 md:text-lg">
                                Schedule standups, catch blockers, and send
                                clear summaries where your team already works.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center gap-4">
                                <PublicAction />
                                <Link
                                    href="/product"
                                    className="focus-public inline-flex items-center gap-2 rounded-lg px-2 py-3 text-sm font-semibold text-slate-200 hover:text-white"
                                >
                                    See how it works
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                        </MotionHeroItem>
                        <MotionHeroItem>
                            <PulseVisual />
                        </MotionHeroItem>
                    </MotionHero>
                </section>

                <section className="border-y border-white/8 bg-white/[0.018]">
                    <MotionStagger className="mx-auto grid max-w-[1400px] px-5 md:grid-cols-3 md:px-8 lg:px-10">
                        {workflow.map(({ icon: Icon, title, body }) => (
                            <MotionStaggerItem
                                key={title}
                                className="border-b border-white/8 py-8 last:border-b-0 md:border-r md:border-b-0 md:px-8 md:first:pl-0 md:last:border-r-0 md:last:pr-0"
                            >
                                <article>
                                    <Icon
                                        className="size-5 text-cyan-300"
                                        aria-hidden="true"
                                    />
                                    <h2 className="mt-5 text-lg font-semibold tracking-[-0.02em]">
                                        {title}
                                    </h2>
                                    <p className="mt-2 max-w-sm text-sm leading-6 text-slate-400">
                                        {body}
                                    </p>
                                </article>
                            </MotionStaggerItem>
                        ))}
                    </MotionStagger>
                </section>

                <section className="mx-auto max-w-[1400px] px-5 py-24 md:px-8 md:py-32 lg:px-10">
                    <div className="grid items-start gap-12 lg:grid-cols-[0.75fr_1.25fr] lg:gap-20">
                        <MotionReveal className="lg:sticky lg:top-32">
                            <h2 className="max-w-[11ch] text-4xl leading-[1.02] font-semibold tracking-[-0.045em] md:text-5xl">
                                The signal arrives before the meeting would.
                            </h2>
                            <p className="mt-5 max-w-md leading-7 text-slate-400">
                                DevPulse turns individual updates into a shared
                                operating picture for the whole team.
                            </p>
                        </MotionReveal>
                        <div className="grid gap-4">
                            <MotionReveal>
                                <article className="rounded-xl border border-white/10 bg-[#0c1b29] p-6 md:p-8">
                                    <span className="flex size-11 items-center justify-center rounded-lg bg-amber-300/10 text-amber-200">
                                        <AlertTriangle className="size-5" />
                                    </span>
                                    <h3 className="mt-8 max-w-lg text-2xl font-semibold tracking-[-0.03em] md:text-3xl">
                                        Blockers become work, not meeting notes.
                                    </h3>
                                    <p className="mt-4 max-w-xl leading-7 text-slate-400">
                                        Flag the issue, preserve the surrounding
                                        update, and give the right manager a
                                        clear next action.
                                    </p>
                                </article>
                            </MotionReveal>
                            <MotionReveal>
                                <article className="grid gap-8 rounded-xl border border-cyan-300/15 bg-cyan-300/[0.055] p-6 md:grid-cols-[1fr_auto] md:items-end md:p-8">
                                    <div>
                                        <GitPullRequest className="size-5 text-cyan-300" />
                                        <h3 className="mt-8 max-w-lg text-2xl font-semibold tracking-[-0.03em] md:text-3xl">
                                            Work context stays attached.
                                        </h3>
                                        <p className="mt-4 max-w-xl leading-7 text-slate-300">
                                            Bring Jira issues and Bitbucket
                                            review activity into the same daily
                                            view.
                                        </p>
                                    </div>
                                    <Link
                                        href="/integrations"
                                        className="focus-public inline-flex items-center gap-2 rounded-lg text-sm font-semibold text-cyan-200 hover:text-cyan-100"
                                    >
                                        Explore integrations
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </article>
                            </MotionReveal>
                        </div>
                    </div>
                </section>

                <section className="border-t border-white/8">
                    <MotionReveal className="mx-auto grid max-w-[1400px] items-center gap-10 px-5 py-20 md:px-8 lg:grid-cols-[1fr_auto] lg:px-10 lg:py-24">
                        <div>
                            <h2 className="max-w-[14ch] text-4xl leading-none font-semibold tracking-[-0.045em] md:text-5xl">
                                Replace status chasing with a reliable rhythm.
                            </h2>
                            <p className="mt-5 max-w-xl leading-7 text-slate-400">
                                Set up the workspace, connect the team, and run
                                the first standup on your schedule.
                            </p>
                        </div>
                        <PublicAction className="lg:mr-2" />
                    </MotionReveal>
                </section>
            </main>
        </PublicLayout>
    );
}
