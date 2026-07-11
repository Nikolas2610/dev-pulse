import { Head, router, useForm } from '@inertiajs/react';
import { BriefcaseBusiness, Pencil, Plus, Trash2 } from 'lucide-react';
import {
    FormActions,
    FormCheckbox,
    FormField,
    FormGrid,
    TimezoneSelect,
} from '@/components/forms/form-components';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    IndexDataControls,
    IndexDataState,
    IndexFilterSelect,
    IndexPagination,
} from '@/components/index-data-controls';
import { useIndexData } from '@/hooks/use-index-data';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

type Workspace = {
    id: number;
    name: string;
    slug: string;
    timezone: string;
    is_active: boolean;
    users_count?: number;
    templates_count?: number;
};

type Props = {
    indexEndpoint: string;
    canManageWorkspaces: boolean;
};

function WorkspaceEditSheet({ workspace }: { workspace: Workspace }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: workspace.name,
        slug: workspace.slug,
        timezone: workspace.timezone,
        is_active: workspace.is_active,
    });

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil />
                    Edit
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>{workspace.name}</SheetTitle>
                    <SheetDescription>
                        Update workspace naming, timezone, and availability.
                    </SheetDescription>
                </SheetHeader>
                <form
                    className="space-y-4 px-4 pb-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        patch(`/workspaces/${workspace.id}`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <FormGrid>
                        <FormField
                            label="Name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            error={errors.name}
                        />
                        <FormField
                            label="Slug"
                            value={data.slug}
                            onChange={(event) =>
                                setData('slug', event.target.value)
                            }
                            error={errors.slug}
                        />
                    </FormGrid>
                    <TimezoneSelect
                        label="Timezone"
                        value={data.timezone}
                        onChange={(event) =>
                            setData('timezone', event.target.value)
                        }
                    />
                    <FormCheckbox
                        label="Active workspace"
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked)
                        }
                        className="w-fit"
                    />
                    <div className="flex flex-wrap gap-2">
                        <FormActions
                            submitLabel="Save workspace"
                            processing={processing}
                        />
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                router.delete(`/workspaces/${workspace.id}`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 />
                            Delete workspace
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function WorkspaceRow({
    workspace,
    canManageWorkspaces,
}: {
    workspace: Workspace;
    canManageWorkspaces: boolean;
}) {
    return (
        <div className="dp-table-row grid-cols-[auto_1fr_auto] items-center gap-4">
            <div className="dp-icon-tile">
                <BriefcaseBusiness className="size-5" />
            </div>
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="font-medium">{workspace.name}</div>
                    <Badge
                        variant={workspace.is_active ? 'secondary' : 'outline'}
                    >
                        {workspace.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </div>
                <div className="mt-1 text-sm text-muted-foreground">
                    {workspace.slug} - {workspace.timezone}
                </div>
                <div className="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
                    <span className="rounded-md border px-2 py-1">
                        {workspace.users_count ?? 0} members
                    </span>
                    <span className="rounded-md border px-2 py-1">
                        {workspace.templates_count ?? 0} templates
                    </span>
                </div>
            </div>

            {canManageWorkspaces && (
                <WorkspaceEditSheet workspace={workspace} />
            )}
        </div>
    );
}

export default function WorkspacesIndex({
    indexEndpoint,
    canManageWorkspaces,
}: Props) {
    const { page, query, loading, error, updateQuery, setFilter } =
        useIndexData<Workspace>(indexEndpoint, {
            sort: 'name',
            direction: 'asc',
        });
    const workspaces = page.data;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        slug: '',
        timezone: 'Asia/Nicosia',
        is_active: true,
    });

    return (
        <>
            <Head title="Workspaces" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Organization structure</div>
                        <h1 className="dp-title">Workspaces</h1>
                        <p className="dp-subtitle">
                            Create and maintain the workspace boundaries used by
                            schedules, templates, standups, and team membership.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="secondary">
                            {page.total} workspaces
                        </Badge>
                        {canManageWorkspaces && (
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button>
                                        <Plus />
                                        New workspace
                                    </Button>
                                </SheetTrigger>
                                <SheetContent className="w-full sm:max-w-xl">
                                    <SheetHeader>
                                        <SheetTitle>
                                            Create workspace
                                        </SheetTitle>
                                        <SheetDescription>
                                            Add a team area for standups,
                                            templates, and member access.
                                        </SheetDescription>
                                    </SheetHeader>
                                    <form
                                        className="space-y-4 px-4 pb-6"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            post('/workspaces', {
                                                onSuccess: () => reset(),
                                            });
                                        }}
                                    >
                                        <FormGrid>
                                            <FormField
                                                label="Name"
                                                value={data.name}
                                                onChange={(event) =>
                                                    setData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Product"
                                                error={errors.name}
                                            />
                                            <FormField
                                                label="Slug"
                                                value={data.slug}
                                                onChange={(event) =>
                                                    setData(
                                                        'slug',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="product"
                                                error={errors.slug}
                                            />
                                        </FormGrid>
                                        <TimezoneSelect
                                            label="Timezone"
                                            value={data.timezone}
                                            onChange={(event) =>
                                                setData(
                                                    'timezone',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <FormCheckbox
                                            label="Active"
                                            checked={data.is_active}
                                            onCheckedChange={(checked) =>
                                                setData('is_active', checked)
                                            }
                                            className="w-fit"
                                        />
                                        <FormActions
                                            submitLabel="Create workspace"
                                            processing={processing}
                                        />
                                    </form>
                                </SheetContent>
                            </Sheet>
                        )}
                    </div>
                </div>

                <div className="dp-panel">
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">
                                Workspace directory
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {canManageWorkspaces
                                    ? 'Edit workspace details or remove unused workspaces.'
                                    : 'Workspaces are read-only for your role.'}
                            </p>
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
                            { label: 'Name A-Z', value: 'name' },
                            {
                                label: 'Newest first',
                                value: 'created_at',
                                direction: 'desc',
                            },
                            {
                                label: 'Most members',
                                value: 'users_count',
                                direction: 'desc',
                            },
                            {
                                label: 'Most templates',
                                value: 'templates_count',
                                direction: 'desc',
                            },
                        ]}
                    >
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
                    {workspaces.map((workspace) => (
                        <WorkspaceRow
                            key={workspace.id}
                            workspace={workspace}
                            canManageWorkspaces={canManageWorkspaces}
                        />
                    ))}
                    {!workspaces.length && (
                        <IndexDataState
                            loading={loading}
                            error={error}
                            empty="No workspaces match these filters."
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
