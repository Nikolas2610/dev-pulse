import { Head } from '@inertiajs/react';
import { Eye, Focus, Radio, Users } from 'lucide-react';
import { PublicAction, PublicLayout } from '@/components/public/public-layout';
import {
    MotionHero,
    MotionHeroItem,
    MotionReveal,
    MotionStagger,
    MotionStaggerItem,
} from '@/components/public/public-motion';

const principles = [
    {
        icon: Focus,
        title: 'Protect focus',
        body: 'Status sharing should not interrupt the work it is meant to explain.',
    },
    {
        icon: Eye,
        title: 'Make blockers visible',
        body: 'A blocker is useful only when it reaches someone who can act on it.',
    },
    {
        icon: Radio,
        title: 'Keep a reliable rhythm',
        body: 'The team should know when to answer and where the summary will appear.',
    },
];

export default function About() {
    return (
        <PublicLayout current="about">
            <Head title="About">
                <meta
                    name="description"
                    content="DevPulse helps engineering teams replace status chasing with clear async standups and earlier blocker visibility."
                />
            </Head>
            <main>
                <section className="relative min-h-[calc(82dvh-68px)] overflow-hidden border-b border-white/8">
                    <div className="public-grid-bg absolute inset-0 opacity-35" />
                    <div className="public-orbit absolute right-[-8rem] bottom-[-12rem] size-[34rem] rounded-full bg-cyan-300/[0.07] blur-3xl" />
                    <MotionHero className="relative mx-auto flex min-h-[calc(82dvh-68px)] max-w-[1400px] items-end px-5 py-20 md:px-8 lg:px-10 lg:py-24">
                        <MotionHeroItem className="max-w-5xl">
                            <p className="mb-5 text-xs font-semibold tracking-[0.16em] text-cyan-300 uppercase">
                                Why DevPulse
                            </p>
                            <h1 className="max-w-[15ch] text-5xl leading-[0.98] font-semibold tracking-[-0.055em] md:text-7xl">
                                Teams need shared context, not more status
                                meetings.
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300">
                                DevPulse creates a calm, repeatable way to share
                                progress, spot friction, and decide what needs
                                attention.
                            </p>
                        </MotionHeroItem>
                    </MotionHero>
                </section>

                <section className="mx-auto max-w-[1400px] px-5 py-24 md:px-8 md:py-32 lg:px-10">
                    <div className="grid gap-12 lg:grid-cols-[0.7fr_1.3fr] lg:gap-24">
                        <MotionReveal>
                            <Users className="size-6 text-cyan-300" />
                            <h2 className="mt-7 max-w-[12ch] text-4xl leading-none font-semibold tracking-[-0.045em] md:text-5xl">
                                Built around the way engineering work changes.
                            </h2>
                        </MotionReveal>
                        <MotionReveal className="max-w-2xl space-y-6 text-lg leading-8 text-slate-300">
                            <p>
                                Priorities shift. Reviews uncover new work. A
                                small dependency becomes a blocker. The team
                                needs a current picture without stopping
                                everyone to assemble it.
                            </p>
                            <p className="text-slate-400">
                                DevPulse keeps that picture current through
                                scheduled prompts, structured answers, work
                                context, and summaries delivered back to the
                                team.
                            </p>
                        </MotionReveal>
                    </div>
                </section>

                <section className="border-y border-white/8 bg-[#0a1825]">
                    <div className="mx-auto max-w-[1400px] px-5 py-20 md:px-8 lg:px-10 lg:py-24">
                        <h2 className="text-3xl font-semibold tracking-[-0.04em] md:text-4xl">
                            Product principles
                        </h2>
                        <MotionStagger className="mt-12 grid gap-10 md:grid-cols-[1.2fr_0.8fr] lg:grid-cols-[1.2fr_0.8fr_1fr]">
                            {principles.map(({ icon: Icon, title, body }) => (
                                <MotionStaggerItem
                                    key={title}
                                    className="border-t border-white/10 pt-7"
                                >
                                    <article>
                                        <Icon className="size-5 text-cyan-300" />
                                        <h3 className="mt-8 text-xl font-semibold tracking-[-0.025em]">
                                            {title}
                                        </h3>
                                        <p className="mt-3 max-w-sm text-sm leading-6 text-slate-400">
                                            {body}
                                        </p>
                                    </article>
                                </MotionStaggerItem>
                            ))}
                        </MotionStagger>
                    </div>
                </section>

                <MotionReveal className="mx-auto flex max-w-[1400px] flex-col items-start gap-8 px-5 py-20 md:px-8 lg:flex-row lg:items-end lg:justify-between lg:px-10 lg:py-24">
                    <div>
                        <h2 className="max-w-[14ch] text-4xl leading-none font-semibold tracking-[-0.045em] md:text-5xl">
                            Give useful context a place to land.
                        </h2>
                        <p className="mt-5 max-w-xl leading-7 text-slate-400">
                            Create a workspace and start with the standup your
                            team already knows.
                        </p>
                    </div>
                    <PublicAction />
                </MotionReveal>
            </main>
        </PublicLayout>
    );
}
