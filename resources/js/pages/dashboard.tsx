import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    Clock3,
    MessageSquare,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type PeriodStats = {
    label: string;
    submitted: number;
    expected: number;
    pending: number;
    blockers: number;
    completionRate: number;
};

type DashboardProps = {
    pendingStandup: {
        runId: number;
        workspaceName: string;
        templateName: string;
    } | null;
    todayRun: any | null;
    latestSummary: { summary_text: string } | null;
    stats: {
        today: PeriodStats & {
            hasRun: boolean;
            status: string | null;
        };
        week: PeriodStats & {
            runs: number;
            completedRuns: number;
        };
    };
    engineeringWork: {
        jiraOpen: number;
        pullRequests: number;
        blocked: number;
        pendingReviews: number;
        stalePullRequests: number;
        lastSyncedAt: string | null;
        items: any[];
    } | null;
};

const nonBlockerAnswers = new Set([
    '-',
    'n/a',
    'na',
    'no',
    'no blocker',
    'no blockers',
    'none',
    'nope',
    'not blocked',
    'nothing',
]);

export default function Dashboard({
    todayRun,
    latestSummary,
    stats,
    engineeringWork,
    pendingStandup,
}: DashboardProps) {
    const submittedResponses = (todayRun?.responses ?? []).filter(
        (response: any) => response.status === 'submitted',
    );
    const responses = todayRun?.responses ?? [];
    const pending = responses
        .filter((response: any) => response.status === 'pending')
        .map((response: any) => response.user)
        .filter(Boolean);
    const blockers = submittedResponses.flatMap((response: any) =>
        (response.answers ?? [])
            .filter((answer: any) =>
                String(answer.question?.question_key ?? '')
                    .toLowerCase()
                    .includes('blocker'),
            )
            .filter((answer: any) => {
                const value = String(answer.answer_text ?? '')
                    .trim()
                    .toLowerCase();

                return value.length > 0 && !nonBlockerAnswers.has(value);
            })
            .map((answer: any) => ({
                member: response.user?.name,
                text: answer.answer_text,
            })),
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="dp-page">
                {pendingStandup && (
                    <div className="mb-6 flex flex-col gap-4 rounded-xl border border-primary/25 bg-primary/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-sm font-semibold">
                                Your standup is waiting
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {pendingStandup.templateName} for{' '}
                                {pendingStandup.workspaceName} is ready to
                                complete.
                            </p>
                        </div>
                        <Button asChild className="shrink-0">
                            <Link
                                href={`/standup-runs/${pendingStandup.runId}/submit`}
                            >
                                Fill standup
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>
                )}
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Daily pulse</div>
                        <h1 className="dp-title">Standup command center</h1>
                        <p className="dp-subtitle">
                            Track today’s answers, pending members, and blockers
                            before the daily summary goes to Slack.
                        </p>
                    </div>
                    {todayRun && (
                        <Button asChild>
                            <Link href={`/standups/${todayRun.id}`}>
                                <MessageSquare />
                                Open run
                            </Link>
                        </Button>
                    )}
                </div>

                <section aria-labelledby="pulse-overview-title">
                    <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2
                                id="pulse-overview-title"
                                className="font-semibold"
                            >
                                Participation pulse
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Today compared with the current calendar week.
                            </p>
                        </div>
                        <Badge variant="outline">
                            {todayRun?.workspace?.name ?? 'Current workspace'}
                        </Badge>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <PeriodCard
                            period="Today"
                            range={stats.today.label}
                            icon={<Clock3 aria-hidden="true" />}
                            completionRate={stats.today.completionRate}
                            hasActivity={stats.today.hasRun}
                            activityLabel={
                                stats.today.hasRun
                                    ? 'Participation'
                                    : 'No run today'
                            }
                            status={stats.today.status}
                            items={[
                                {
                                    label: 'Responses',
                                    value: `${stats.today.submitted}/${stats.today.expected}`,
                                    detail: 'received',
                                },
                                {
                                    label: 'Waiting',
                                    value: String(stats.today.pending),
                                    detail: 'team members',
                                },
                                {
                                    label: 'Blockers',
                                    value: String(stats.today.blockers),
                                    detail: 'reported',
                                },
                            ]}
                        />
                        <PeriodCard
                            period="This week"
                            range={stats.week.label}
                            icon={<CalendarDays aria-hidden="true" />}
                            completionRate={stats.week.completionRate}
                            hasActivity={stats.week.expected > 0}
                            activityLabel="Weekly participation"
                            items={[
                                {
                                    label: 'Responses',
                                    value: `${stats.week.submitted}/${stats.week.expected}`,
                                    detail: 'received',
                                },
                                {
                                    label: 'Runs',
                                    value: String(stats.week.runs),
                                    detail: `${stats.week.completedRuns} completed`,
                                },
                                {
                                    label: 'Blockers',
                                    value: String(stats.week.blockers),
                                    detail: 'reported',
                                },
                            ]}
                        />
                    </div>
                </section>

                {engineeringWork && (
                    <section aria-labelledby="engineering-work-title">
                        <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h2
                                    id="engineering-work-title"
                                    className="font-semibold"
                                >
                                    Engineering work
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Live Jira workload and Bitbucket review
                                    queue.
                                </p>
                            </div>
                            {engineeringWork.lastSyncedAt && (
                                <Badge variant="outline">
                                    Synced{' '}
                                    {new Date(
                                        engineeringWork.lastSyncedAt,
                                    ).toLocaleString()}
                                </Badge>
                            )}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <WorkMetric
                                label="Jira open"
                                value={engineeringWork.jiraOpen}
                            />
                            <WorkMetric
                                label="Open PRs"
                                value={engineeringWork.pullRequests}
                            />
                            <WorkMetric
                                label="Reviews"
                                value={engineeringWork.pendingReviews}
                            />
                            <WorkMetric
                                label="Blocked"
                                value={engineeringWork.blocked}
                            />
                            <WorkMetric
                                label="Stale PRs"
                                value={engineeringWork.stalePullRequests}
                            />
                        </div>
                        {engineeringWork.items.length > 0 && (
                            <Card className="mt-4">
                                <CardHeader>
                                    <CardTitle>Needs attention</CardTitle>
                                    <CardDescription>
                                        Recently updated and blocked work across
                                        this workspace.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    {engineeringWork.items.map((item) => (
                                        <a
                                            key={item.id}
                                            href={item.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex flex-col gap-1 rounded-lg border p-3 hover:bg-muted/50"
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">
                                                    {item.external_key ??
                                                        item.provider}
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {item.status}
                                                </Badge>
                                                {item.is_blocked && (
                                                    <Badge variant="destructive">
                                                        Blocked
                                                    </Badge>
                                                )}
                                            </div>
                                            <span className="font-medium">
                                                {item.title}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {item.assignee?.user?.name ??
                                                    item.author?.user?.name ??
                                                    'Unmapped user'}
                                            </span>
                                        </a>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </section>
                )}

                {todayRun ? (
                    <>
                        <div className="grid gap-5 xl:grid-cols-[1fr_360px]">
                            <div className="dp-panel">
                                <div className="dp-panel-header">
                                    <div>
                                        <h2 className="font-semibold">
                                            Submitted answers
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Grouped by developer for quick
                                            review.
                                        </p>
                                    </div>
                                    <Badge>{todayRun.template?.name}</Badge>
                                </div>
                                <div>
                                    {submittedResponses.length ? (
                                        submittedResponses.map(
                                            (response: any) => (
                                                <div
                                                    key={response.id}
                                                    className="dp-table-row grid-cols-[180px_1fr]"
                                                >
                                                    <div>
                                                        <div className="font-medium">
                                                            {
                                                                response.user
                                                                    ?.name
                                                            }
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            Submitted
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2">
                                                        {response.answers?.map(
                                                            (answer: any) => (
                                                                <div
                                                                    key={
                                                                        answer.id
                                                                    }
                                                                >
                                                                    <div className="text-xs font-semibold text-muted-foreground">
                                                                        {
                                                                            answer
                                                                                .question
                                                                                ?.question_text
                                                                        }
                                                                    </div>
                                                                    <div className="text-sm">
                                                                        {answer.answer_text ||
                                                                            '-'}
                                                                    </div>
                                                                </div>
                                                            ),
                                                        )}
                                                        {response.items?.map(
                                                            (item: any) => (
                                                                <div
                                                                    key={
                                                                        item.id
                                                                    }
                                                                >
                                                                    <div className="text-xs font-semibold text-muted-foreground">
                                                                        {String(
                                                                            item.relation,
                                                                        ).replaceAll(
                                                                            '_',
                                                                            ' ',
                                                                        )}
                                                                    </div>
                                                                    <a
                                                                        href={
                                                                            item.url_snapshot
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="text-sm font-medium hover:underline"
                                                                    >
                                                                        {
                                                                            item.external_key
                                                                        }{' '}
                                                                        {
                                                                            item.title_snapshot
                                                                        }
                                                                    </a>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )
                                    ) : (
                                        <EmptyPanel text="No one has submitted yet." />
                                    )}
                                </div>
                            </div>

                            <div className="space-y-5">
                                <SidePanel title="Pending members">
                                    {pending.length ? (
                                        pending.map((member: any) => (
                                            <div
                                                key={member.id}
                                                className="flex items-center justify-between border-b py-2 last:border-0"
                                            >
                                                <span>{member.name}</span>
                                                <Badge variant="secondary">
                                                    Pending
                                                </Badge>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Everyone has responded.
                                        </p>
                                    )}
                                </SidePanel>
                                <SidePanel title="Blockers">
                                    {blockers.length ? (
                                        blockers.map(
                                            (blocker: any, index: number) => (
                                                <div
                                                    key={`${blocker.member}-${index}`}
                                                    className="rounded-md border border-destructive/25 bg-destructive/5 p-3"
                                                >
                                                    <div className="font-medium">
                                                        {blocker.member}
                                                    </div>
                                                    <p className="text-sm">
                                                        {blocker.text}
                                                    </p>
                                                </div>
                                            ),
                                        )
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No blockers reported.
                                        </p>
                                    )}
                                </SidePanel>
                                {latestSummary && (
                                    <SidePanel title="Latest summary">
                                        <pre className="text-sm whitespace-pre-wrap">
                                            {latestSummary.summary_text}
                                        </pre>
                                    </SidePanel>
                                )}
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="dp-panel dp-signal">
                        <div className="dp-panel-body">
                            <h2 className="font-semibold">
                                No standup run today
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Create a schedule or generate today’s run to
                                start collecting updates.
                            </p>
                            <Button asChild className="mt-4">
                                <Link href="/schedules">Manage schedules</Link>
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function WorkMetric({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl tabular-nums">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function PeriodCard({
    icon,
    period,
    range,
    completionRate,
    hasActivity,
    activityLabel,
    status,
    items,
}: {
    icon: React.ReactNode;
    period: string;
    range: string;
    completionRate: number;
    hasActivity: boolean;
    activityLabel: string;
    status?: string | null;
    items: Array<{ label: string; value: string; detail: string }>;
}) {
    return (
        <Card className="gap-5 overflow-hidden py-5">
            <CardHeader className="flex-row items-start justify-between gap-4 px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground [&>svg]:size-4">
                        {icon}
                    </div>
                    <div className="min-w-0">
                        <CardTitle>{period}</CardTitle>
                        <CardDescription>{range}</CardDescription>
                    </div>
                </div>
                {status && <Badge variant="secondary">{status}</Badge>}
            </CardHeader>

            <CardContent className="px-5">
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <div className="text-4xl font-semibold tracking-tight tabular-nums">
                            {hasActivity ? `${completionRate}%` : '—'}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {activityLabel}
                        </p>
                    </div>
                    {hasActivity && completionRate === 100 && (
                        <div className="flex items-center gap-1.5 text-sm font-medium text-primary">
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                            All caught up
                        </div>
                    )}
                </div>

                <div
                    className="mt-4 h-1.5 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-label={`${period} participation`}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={hasActivity ? completionRate : 0}
                >
                    <div
                        className="h-full rounded-full bg-primary transition-[width] motion-reduce:transition-none"
                        style={{
                            width: `${hasActivity ? completionRate : 0}%`,
                        }}
                    />
                </div>
            </CardContent>

            <CardFooter className="grid grid-cols-3 border-t px-5 pt-5">
                {items.map((item) => (
                    <div
                        key={item.label}
                        className="min-w-0 border-r px-3 first:pl-0 last:border-r-0 last:pr-0"
                    >
                        <div className="text-xs font-medium text-muted-foreground">
                            {item.label}
                        </div>
                        <div className="mt-1 text-xl font-semibold tabular-nums">
                            {item.value}
                        </div>
                        <div className="truncate text-xs text-muted-foreground">
                            {item.detail}
                        </div>
                    </div>
                ))}
            </CardFooter>
        </Card>
    );
}

function SidePanel({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="dp-panel">
            <div className="border-b px-4 py-3 font-semibold">{title}</div>
            <div className="space-y-3 p-4">{children}</div>
        </div>
    );
}

function EmptyPanel({ text }: { text: string }) {
    return <div className="p-5 text-sm text-muted-foreground">{text}</div>;
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
