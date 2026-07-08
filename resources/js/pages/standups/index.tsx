import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays, MessageSquare } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { StandupStatusBadge } from '@/components/standup-status-badge';
import { AppDateFormatter } from '@/lib/date-format';

export default function StandupsIndex({ runs }: { runs: any[] }) {
    return (
        <>
            <Head title="Standups" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Run history</div>
                        <h1 className="dp-title">Standups</h1>
                        <p className="dp-subtitle">
                            Review active, completed, and cancelled standup runs
                            across workspaces.
                        </p>
                    </div>
                </div>

                <div className="dp-panel">
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">Runs</h2>
                            <p className="text-sm text-muted-foreground">
                                Newest run dates appear first.
                            </p>
                        </div>
                        <Badge variant="secondary">{runs.length} runs</Badge>
                    </div>

                    {runs.length ? (
                        runs.map((run) => (
                            <div
                                key={run.id}
                                className="dp-table-row grid-cols-[auto_1fr] items-center gap-4 md:grid-cols-[auto_1fr_auto_auto]"
                            >
                                <div className="dp-icon-tile mt-1">
                                    <MessageSquare className="size-5" />
                                </div>
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {run.template?.name}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                        <span>{run.workspace?.name}</span>
                                        <span>-</span>
                                        <CalendarDays className="size-3.5" />
                                        <span>
                                            {AppDateFormatter.friendlyDate(
                                                run.run_date,
                                            )}
                                        </span>
                                    </div>
                                </div>
                                <StandupStatusBadge status={run.status} />
                                <Button
                                    asChild
                                    variant="secondary"
                                    size="sm"
                                    className="col-span-2 justify-self-start md:col-span-1 md:justify-self-end"
                                >
                                    <Link href={`/standups/${run.id}`}>
                                        View run
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        ))
                    ) : (
                        <div className="p-5 text-sm text-muted-foreground">
                            No standup runs have been generated yet.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
