import { AlertTriangle, Check, MessageSquareText, Send } from 'lucide-react';

const updates = [
    { name: 'Maya', text: 'Slack retry handling is ready for review.' },
    { name: 'Theo', text: 'Schedule visibility rules are in progress.' },
];

export function PulseVisual() {
    return (
        <div
            className="relative mx-auto w-full max-w-[620px]"
            aria-label="DevPulse standup flow preview"
        >
            <div className="public-visual-shell relative overflow-hidden rounded-xl border border-white/12 bg-[#0c1b29] shadow-[0_32px_90px_rgba(0,10,22,0.42)]">
                <div className="flex items-center justify-between border-b border-white/8 px-5 py-4">
                    <div>
                        <p className="text-xs font-medium text-slate-400">
                            Today
                        </p>
                        <h2 className="mt-1 text-base font-semibold">
                            Engineering standup
                        </h2>
                    </div>
                    <span className="rounded-md bg-lime-300/12 px-2.5 py-1 text-xs font-semibold text-lime-200">
                        Open
                    </span>
                </div>
                <div className="grid gap-3 p-4 sm:p-5">
                    {updates.map((update) => (
                        <article
                            key={update.name}
                            className="grid grid-cols-[36px_1fr] gap-3 rounded-xl border border-white/8 bg-white/[0.035] p-3.5"
                        >
                            <div className="flex size-9 items-center justify-center rounded-lg bg-cyan-300/10 text-sm font-semibold text-cyan-200">
                                {update.name.charAt(0)}
                            </div>
                            <div>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-sm font-semibold">
                                        {update.name}
                                    </p>
                                    <span className="inline-flex items-center gap-1 text-xs text-slate-400">
                                        <Check className="size-3.5 text-cyan-300" />
                                        Submitted
                                    </span>
                                </div>
                                <p className="mt-1.5 text-sm leading-5 text-slate-300">
                                    {update.text}
                                </p>
                            </div>
                        </article>
                    ))}
                    <article className="grid grid-cols-[36px_1fr] gap-3 rounded-xl border border-amber-300/20 bg-amber-300/[0.055] p-3.5">
                        <div className="flex size-9 items-center justify-center rounded-lg bg-amber-300/10 text-amber-200">
                            <AlertTriangle className="size-4" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">
                                Blocker surfaced
                            </p>
                            <p className="mt-1.5 text-sm leading-5 text-slate-300">
                                Discord webhook scope needs an owner before
                                release.
                            </p>
                        </div>
                    </article>
                </div>
                <div className="border-t border-white/8 bg-cyan-300/[0.07] p-4 sm:p-5">
                    <div className="flex items-start gap-3">
                        <MessageSquareText className="mt-0.5 size-5 text-cyan-300" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold">
                                Summary ready
                            </p>
                            <p className="mt-1 text-sm leading-5 text-slate-300">
                                Two updates submitted. One blocker needs
                                attention.
                            </p>
                        </div>
                        <Send className="mt-0.5 size-4 text-slate-400" />
                    </div>
                </div>
            </div>
        </div>
    );
}
