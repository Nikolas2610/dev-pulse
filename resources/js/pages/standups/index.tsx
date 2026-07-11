import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays, MessageSquare } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { StandupStatusBadge } from '@/components/standup-status-badge';
import { AppDateFormatter } from '@/lib/date-format';
import {
    IndexDataControls,
    IndexDataState,
    IndexFilterSelect,
    IndexPagination,
} from '@/components/index-data-controls';
import { useIndexData } from '@/hooks/use-index-data';

export default function StandupsIndex({
    indexEndpoint,
}: {
    indexEndpoint: string;
}) {
    const { page, query, loading, error, updateQuery, setFilter } =
        useIndexData<any>(indexEndpoint, {
            sort: 'run_date',
            direction: 'desc',
        });
    const runs = page.data;

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
                        <Badge variant="secondary">{page.total} runs</Badge>
                    </div>

                    <IndexDataControls
                        search={query.search}
                        onSearchChange={(search) => updateQuery({ search })}
                        sort={query.sort}
                        direction={query.direction}
                        onSortChange={(sort, direction) =>
                            updateQuery({ sort, direction })
                        }
                        sortOptions={[
                            {
                                label: 'Newest run',
                                value: 'run_date',
                                direction: 'desc',
                            },
                            {
                                label: 'Oldest run',
                                value: 'run_date',
                                direction: 'asc',
                            },
                            { label: 'Template A-Z', value: 'template' },
                            { label: 'Status A-Z', value: 'status' },
                        ]}
                    >
                        <IndexFilterSelect
                            label="All statuses"
                            value={query.filters.status ?? ''}
                            onChange={(value) => setFilter('status', value)}
                            options={[
                                { label: 'Scheduled', value: 'scheduled' },
                                { label: 'Active', value: 'active' },
                                { label: 'Completed', value: 'completed' },
                                { label: 'Cancelled', value: 'cancelled' },
                            ]}
                        />
                        <DatePicker
                            value={query.filters.date ?? ''}
                            onValueChange={(value) => setFilter('date', value)}
                            placeholder="Any run date"
                            aria-label="Filter by run date"
                            className="lg:w-44"
                        />
                    </IndexDataControls>

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
                        <IndexDataState
                            loading={loading}
                            error={error}
                            empty="No standup runs match these filters."
                        />
                    )}
                    <IndexPagination
                        currentPage={page.current_page}
                        lastPage={page.last_page}
                        from={page.from}
                        to={page.to}
                        total={page.total}
                        perPage={query.perPage}
                        onPageChange={(page) => updateQuery({ page })}
                        onPerPageChange={(perPage) => updateQuery({ perPage })}
                    />
                </div>
            </div>
        </>
    );
}
