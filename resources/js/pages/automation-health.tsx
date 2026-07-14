import { Head, Link } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock3,
    ExternalLink,
    ServerCog,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Execution = {
    id: number;
    task: string;
    status: 'running' | 'succeeded' | 'failed';
    started_at: string;
    duration_ms: number | null;
    metrics_json: Record<string, number> | null;
    error: string | null;
};

const taskLabels: Record<string, string> = {
    'standups.generate-runs': 'Generate standup runs',
    'standups.process-due-runs': 'Process due standups',
};

function relativeTime(value: string | null) {
    return value
        ? formatDistanceToNow(new Date(value), { addSuffix: true })
        : 'Never';
}

export default function AutomationHealth({
    health,
    recentExecutions,
    failedJobs,
}: {
    health: any;
    recentExecutions: Execution[];
    failedJobs: any[];
}) {
    const missingCount = health.missing_runs.length;
    const stalledCount = health.stalled_runs.length;
    const allHealthy =
        health.scheduler.status === 'healthy' &&
        health.queue.status === 'healthy' &&
        missingCount === 0;

    return (
        <>
            <Head title="Automation health" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Operations</div>
                        <h1 className="dp-title">Automation health</h1>
                        <p className="dp-subtitle">
                            Verify that the scheduler, queue, and daily standup
                            pipeline are moving on time.
                        </p>
                    </div>
                    <Badge
                        variant={allHealthy ? 'default' : 'destructive'}
                        className="gap-1.5 px-3 py-1.5"
                    >
                        {allHealthy ? (
                            <CheckCircle2 className="size-3.5" />
                        ) : (
                            <AlertTriangle className="size-3.5" />
                        )}
                        {allHealthy ? 'All systems moving' : 'Needs attention'}
                    </Badge>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <HealthCard
                        icon={Activity}
                        label="Laravel scheduler"
                        healthy={health.scheduler.status === 'healthy'}
                        value={
                            health.scheduler.status === 'healthy'
                                ? 'Running'
                                : 'Not detected'
                        }
                        detail={`Last heartbeat ${relativeTime(health.scheduler.last_seen_at)}`}
                    />
                    <HealthCard
                        icon={ServerCog}
                        label="Queue"
                        healthy={health.queue.status === 'healthy'}
                        value={
                            health.queue.status === 'healthy'
                                ? 'Processing'
                                : 'Needs attention'
                        }
                        detail={`${health.queue.pending_jobs} pending · ${health.queue.failed_jobs} failed`}
                    />
                    <HealthCard
                        icon={Clock3}
                        label="Expected standups"
                        healthy={missingCount === 0 && stalledCount === 0}
                        value={
                            missingCount + stalledCount === 0
                                ? 'On time'
                                : `${missingCount + stalledCount} issue${missingCount + stalledCount === 1 ? '' : 's'}`
                        }
                        detail={`${missingCount} missing · ${stalledCount} stalled`}
                    />
                </div>

                {(missingCount > 0 || stalledCount > 0) && (
                    <section className="dp-panel border-destructive/30">
                        <div className="dp-panel-header">
                            <div>
                                <h2 className="font-semibold">
                                    Standups needing attention
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    These schedules have no run today or passed
                                    a processing deadline.
                                </p>
                            </div>
                            <AlertTriangle className="size-5 text-destructive" />
                        </div>
                        <div className="divide-y">
                            {health.missing_runs.map((item: any) => (
                                <IssueRow
                                    key={`missing-${item.id}`}
                                    title={item.name}
                                    detail={`${item.workspace} · Expected ${item.expected_date} (${item.timezone})`}
                                    status="Missing run"
                                    href={`/schedules/${item.id}/edit`}
                                />
                            ))}
                            {health.stalled_runs.map((item: any) => (
                                <IssueRow
                                    key={`stalled-${item.id}`}
                                    title={
                                        item.name ?? `Standup run #${item.id}`
                                    }
                                    detail={`${item.workspace} · Run is still ${item.status}`}
                                    status="Stalled"
                                    href={`/standups/${item.id}`}
                                />
                            ))}
                        </div>
                    </section>
                )}

                <section className="dp-panel">
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">Task executions</h2>
                            <p className="text-sm text-muted-foreground">
                                Latest queue-worker executions for standup
                                generation and processing.
                            </p>
                        </div>
                    </div>
                    <div className="divide-y">
                        {recentExecutions.length === 0 ? (
                            <EmptyRow text="No task executions recorded yet. Run the scheduler and queue worker to begin the audit trail." />
                        ) : (
                            recentExecutions.map((execution) => (
                                <div
                                    key={execution.id}
                                    className="grid gap-2 px-5 py-4 sm:grid-cols-[1fr_auto] sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 font-medium">
                                            {execution.status ===
                                            'succeeded' ? (
                                                <CheckCircle2 className="size-4 text-emerald-600" />
                                            ) : execution.status ===
                                              'failed' ? (
                                                <XCircle className="size-4 text-destructive" />
                                            ) : (
                                                <Clock3 className="size-4 text-amber-600" />
                                            )}
                                            {taskLabels[execution.task] ??
                                                execution.task}
                                        </div>
                                        <p className="mt-1 truncate text-xs text-muted-foreground">
                                            {execution.error ||
                                                metricsText(
                                                    execution.metrics_json,
                                                ) ||
                                                'Completed without changes'}
                                        </p>
                                    </div>
                                    <div className="text-left text-xs text-muted-foreground sm:text-right">
                                        <div>
                                            {relativeTime(execution.started_at)}
                                        </div>
                                        {execution.duration_ms !== null && (
                                            <div>
                                                {execution.duration_ms} ms
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </section>

                <section className="dp-panel">
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">Failed queue jobs</h2>
                            <p className="text-sm text-muted-foreground">
                                Jobs Laravel stopped retrying and retained for
                                investigation.
                            </p>
                        </div>
                        <Badge variant="secondary">{failedJobs.length}</Badge>
                    </div>
                    <div className="divide-y">
                        {failedJobs.length === 0 ? (
                            <EmptyRow text="No failed queue jobs." />
                        ) : (
                            failedJobs.map((job) => (
                                <div key={job.id} className="px-5 py-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="font-medium">
                                            {job.connection} / {job.queue}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {relativeTime(job.failed_at)}
                                        </div>
                                    </div>
                                    <p className="mt-1 text-xs break-words text-destructive">
                                        {job.error}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

function HealthCard({ icon: Icon, label, value, detail, healthy }: any) {
    return (
        <div className="dp-panel p-5">
            <div className="flex items-start justify-between gap-4">
                <div className="dp-icon-tile">
                    <Icon className="size-5" />
                </div>
                <span
                    className={`mt-1 size-2.5 rounded-full ${healthy ? 'bg-emerald-500' : 'bg-destructive'}`}
                    aria-label={healthy ? 'Healthy' : 'Needs attention'}
                />
            </div>
            <div className="mt-5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-1 text-xl font-semibold">{value}</div>
            <div className="mt-1 text-xs text-muted-foreground">{detail}</div>
        </div>
    );
}

function IssueRow({ title, detail, status, href }: any) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div>
                <div className="font-medium">{title}</div>
                <div className="text-xs text-muted-foreground">{detail}</div>
            </div>
            <div className="flex items-center gap-2">
                <Badge variant="destructive">{status}</Badge>
                <Button asChild size="icon" variant="ghost">
                    <Link href={href} aria-label={`Open ${title}`}>
                        <ExternalLink className="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    );
}

function EmptyRow({ text }: { text: string }) {
    return (
        <div className="px-5 py-8 text-sm text-muted-foreground">{text}</div>
    );
}

function metricsText(metrics: Record<string, number> | null): string {
    if (!metrics) {
        return '';
    }

    return Object.entries(metrics)
        .map(([key, value]) => `${key.replaceAll('_', ' ')}: ${value}`)
        .join(' · ');
}
