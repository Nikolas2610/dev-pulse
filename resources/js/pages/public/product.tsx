import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    MessageSquareText,
    Send,
    Users,
} from 'lucide-react';
import { PublicAction, PublicLayout } from '@/components/public/public-layout';
import { PulseVisual } from '@/components/public/pulse-visual';

const stages = [
    {
        icon: CalendarClock,
        title: 'Set the rhythm',
        body: 'Choose the workspace, schedule, timezone, recipients, and reminder windows.',
    },
    {
        icon: MessageSquareText,
        title: 'Ask better questions',
        body: 'Use focused templates that collect progress, next steps, and blockers.',
    },
    {
        icon: AlertTriangle,
        title: 'Surface what is stuck',
        body: 'Keep blocker context visible to the managers who can move it forward.',
    },
    {
        icon: Send,
        title: 'Publish the summary',
        body: 'Deliver the completed standup back to Slack or Discord for the team.',
    },
];

export default function Product() {
    return (
        <PublicLayout current="product">
            <Head title="Product">
                <meta
                    name="description"
                    content="See how DevPulse schedules standups, gathers updates, surfaces blockers, and publishes useful team summaries."
                />
            </Head>
            <main>
                <section className="relative overflow-hidden border-b border-white/8">
                    <div className="public-grid-bg absolute inset-0 opacity-35" />
                    <div className="relative mx-auto grid max-w-[1400px] items-center gap-14 px-5 py-20 md:px-8 lg:grid-cols-[0.85fr_1.15fr] lg:px-10 lg:py-28">
                        <div className="public-enter">
                            <p className="mb-5 text-xs font-semibold tracking-[0.16em] text-cyan-300 uppercase">
                                A complete async standup loop
                            </p>
                            <h1 className="max-w-[11ch] text-5xl leading-[0.98] font-semibold tracking-[-0.055em] md:text-7xl">
                                One rhythm from prompt to action.
                            </h1>
                            <p className="mt-6 max-w-lg text-lg leading-8 text-slate-300">
                                DevPulse gathers the update, preserves the
                                context, and sends the team a useful summary.
                            </p>
                            <PublicAction className="mt-8" />
                        </div>
                        <div className="public-enter public-enter-delay">
                            <PulseVisual />
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-[1400px] px-5 py-24 md:px-8 md:py-32 lg:px-10">
                    <div className="max-w-2xl">
                        <h2 className="text-4xl leading-none font-semibold tracking-[-0.045em] md:text-5xl">
                            The workday, connected.
                        </h2>
                        <p className="mt-5 max-w-xl leading-7 text-slate-400">
                            Each stage reduces the gap between what the team
                            knows and what the team can act on.
                        </p>
                    </div>
                    <div className="mt-14 grid gap-x-10 gap-y-0 md:grid-cols-2">
                        {stages.map(({ icon: Icon, title, body }) => (
                            <article
                                key={title}
                                className="public-reveal grid grid-cols-[44px_1fr] gap-5 border-t border-white/10 py-8 md:py-10"
                            >
                                <span className="flex size-11 items-center justify-center rounded-lg bg-cyan-300/10 text-cyan-200">
                                    <Icon className="size-5" />
                                </span>
                                <div>
                                    <h3 className="text-xl font-semibold tracking-[-0.025em]">
                                        {title}
                                    </h3>
                                    <p className="mt-3 max-w-md text-sm leading-6 text-slate-400">
                                        {body}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="border-y border-white/8 bg-[#0a1825]">
                    <div className="mx-auto grid max-w-[1400px] gap-10 px-5 py-20 md:px-8 lg:grid-cols-[0.7fr_1.3fr] lg:px-10 lg:py-24">
                        <div className="public-reveal">
                            <Users className="size-6 text-cyan-300" />
                            <h2 className="mt-7 max-w-[12ch] text-4xl leading-none font-semibold tracking-[-0.04em]">
                                A clear view for every role.
                            </h2>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <article className="public-reveal rounded-xl border border-white/10 bg-white/[0.025] p-6">
                                <h3 className="text-lg font-semibold">
                                    For contributors
                                </h3>
                                <p className="mt-3 text-sm leading-6 text-slate-400">
                                    Answer focused questions on your schedule
                                    and keep the team informed without repeating
                                    the same update.
                                </p>
                            </article>
                            <article className="public-reveal rounded-xl border border-cyan-300/15 bg-cyan-300/[0.045] p-6">
                                <h3 className="text-lg font-semibold">
                                    For managers
                                </h3>
                                <p className="mt-3 text-sm leading-6 text-slate-300">
                                    See participation, blockers, and work
                                    context across active workspaces from one
                                    place.
                                </p>
                            </article>
                        </div>
                    </div>
                </section>

                <section className="mx-auto flex max-w-[1400px] flex-col items-start gap-8 px-5 py-20 md:px-8 lg:flex-row lg:items-end lg:justify-between lg:px-10 lg:py-24">
                    <div>
                        <h2 className="max-w-[15ch] text-4xl leading-none font-semibold tracking-[-0.045em] md:text-5xl">
                            Give the team a rhythm that holds.
                        </h2>
                        <p className="mt-5 max-w-xl leading-7 text-slate-400">
                            Create the first workspace and shape a standup
                            around the way your team works.
                        </p>
                    </div>
                    <PublicAction />
                </section>
            </main>
        </PublicLayout>
    );
}
