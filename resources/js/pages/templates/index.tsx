import { Head, Link } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
import {
    IndexDataControls,
    IndexDataState,
    IndexFilterSelect,
    IndexPagination,
} from '@/components/index-data-controls';
import { Button } from '@/components/ui/button';
import { useIndexData } from '@/hooks/use-index-data';

type Props = {
    indexEndpoint: string;
    workspaces: Array<{ id: number; name: string }>;
    templates?: Array<{
        id: number;
        name: string;
        description?: string | null;
        is_active: boolean;
        questions?: unknown[];
        workspace?: { name: string } | null;
    }>;
    canManageTemplates: boolean;
};

export default function TemplatesIndex({
    indexEndpoint,
    workspaces,
    canManageTemplates,
}: Props) {
    const { page, query, loading, error, updateQuery, setFilter } =
        useIndexData<NonNullable<Props['templates']>[number]>(indexEndpoint, {
            sort: 'created_at',
            direction: 'desc',
        });
    const templates = page.data;

    return (
        <>
            <Head title="Templates" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Standup design</div>
                        <h1 className="dp-title">Question templates</h1>
                        <p className="dp-subtitle">
                            Build reusable question sets for each daily standup.
                            Schedules pick a template, then Slack sends these
                            questions to the workspace.
                        </p>
                    </div>

                    {canManageTemplates && (
                        <Button asChild>
                            <Link href="/templates/create">
                                <Plus />
                                New template
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="dp-panel">
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">
                                Available templates
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {canManageTemplates
                                    ? 'Click a template to edit its questions.'
                                    : 'Templates are read-only for your role.'}
                            </p>
                        </div>
                        <div className="rounded-md bg-accent px-3 py-1 text-sm font-medium text-accent-foreground">
                            {page.total} total
                        </div>
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
                                label: 'Newest first',
                                value: 'created_at',
                                direction: 'desc',
                            },
                            { label: 'Name A-Z', value: 'name' },
                            {
                                label: 'Most questions',
                                value: 'questions_count',
                                direction: 'desc',
                            },
                        ]}
                    >
                        <IndexFilterSelect
                            label="All workspaces"
                            value={query.filters.workspace_id ?? ''}
                            onChange={(value) =>
                                setFilter('workspace_id', value)
                            }
                            options={workspaces.map((workspace) => ({
                                label: workspace.name,
                                value: workspace.id,
                            }))}
                        />
                        <IndexFilterSelect
                            label="Any status"
                            value={query.filters.active ?? ''}
                            onChange={(value) => setFilter('active', value)}
                            options={[
                                { label: 'Active', value: '1' },
                                { label: 'Inactive', value: '0' },
                            ]}
                        />
                    </IndexDataControls>

                    <div>
                        {templates.map((template) => {
                            const row = (
                                <>
                                    <div className="dp-icon-tile mt-1">
                                        <BookOpen className="size-5" />
                                    </div>
                                    <div>
                                        <div className="font-medium">
                                            {template.name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {template.description ||
                                                'No description'}{' '}
                                            - {template.questions?.length ?? 0}{' '}
                                            questions
                                        </div>
                                    </div>
                                    <div className="text-right text-sm text-muted-foreground">
                                        {template.workspace?.name ??
                                            'All workspaces'}
                                    </div>
                                </>
                            );

                            return canManageTemplates ? (
                                <Link
                                    key={template.id}
                                    href={`/templates/${template.id}/edit`}
                                    className="dp-table-row grid-cols-[auto_1fr_auto]"
                                >
                                    {row}
                                </Link>
                            ) : (
                                <div
                                    key={template.id}
                                    className="dp-table-row grid-cols-[auto_1fr_auto]"
                                >
                                    {row}
                                </div>
                            );
                        })}
                        {!templates.length && (
                            <IndexDataState
                                loading={loading}
                                error={error}
                                empty="No templates match these filters."
                            />
                        )}
                    </div>
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
