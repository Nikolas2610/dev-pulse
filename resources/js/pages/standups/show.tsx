import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import { StandupStatusBadge } from '@/components/standup-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AppDateFormatter } from '@/lib/date-format';
import type { Auth } from '@/types';

export default function StandupsShow({
    run,
    members,
}: {
    run: any;
    members: any[];
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const runResponses = run.responses ?? [];
    const responses = runResponses.filter(
        (response: any) => response.status === 'submitted',
    );
    const pending = runResponses.filter(
        (response: any) => response.status === 'pending',
    );
    const currentUserPendingResponse = pending.find(
        (response: any) => response.user?.id === auth.user?.id,
    );
    const recipientCount = members.length || runResponses.length;

    return (
        <>
            <Head title={run.template?.name ?? 'Standup'} />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                        >
                            <Link href="/standups">
                                <ArrowLeft />
                                Standups
                            </Link>
                        </Button>
                        <div className="dp-kicker mt-3">Run detail</div>
                        <h1 className="dp-title">{run.template?.name}</h1>
                        <p className="dp-subtitle">
                            {run.workspace?.name} -{' '}
                            {AppDateFormatter.friendlyDate(run.run_date)} -{' '}
                            {responses.length}/{recipientCount} submitted
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StandupStatusBadge status={run.status} />
                        {currentUserPendingResponse && (
                            <Button asChild>
                                <Link href={`/standup-runs/${run.id}/submit`}>
                                    <Send />
                                    Submit answers
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-[1fr_320px]">
                    <div className="dp-panel">
                        <div className="dp-panel-header">
                            <div>
                                <h2 className="font-semibold">Responses</h2>
                                <p className="text-sm text-muted-foreground">
                                    Answers grouped by developer.
                                </p>
                            </div>
                        </div>
                        {responses.length ? (
                            responses.map((response: any) => (
                                <div
                                    key={response.id}
                                    className="dp-table-row grid-cols-[180px_1fr]"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {response.user?.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Submitted
                                        </div>
                                    </div>
                                    <div className="space-y-3">
                                        {response.answers?.map(
                                            (answer: any) => (
                                                <div key={answer.id}>
                                                    <div className="text-xs font-semibold text-muted-foreground">
                                                        {
                                                            answer.question
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
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="p-5 text-sm text-muted-foreground">
                                No submitted answers yet.
                            </div>
                        )}
                    </div>

                    <div className="dp-panel h-fit">
                        <div className="border-b px-4 py-3 font-semibold">
                            Pending members
                        </div>
                        <div className="p-4">
                            {pending.length ? (
                                pending.map((response: any) => (
                                    <div
                                        key={response.id}
                                        className="flex items-center justify-between border-b py-2 last:border-0"
                                    >
                                        <span>{response.user?.name}</span>
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
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
