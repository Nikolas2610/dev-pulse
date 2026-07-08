import { Head, router, useForm } from '@inertiajs/react';
import { BriefcaseBusiness, Plus, Trash2 } from 'lucide-react';
import {
    FormActions,
    FormCheckbox,
    FormField,
    FormGrid,
    InlineEditForm,
    TimezoneSelect,
} from '@/components/forms/form-components';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    workspaces: Workspace[];
    canManageWorkspaces: boolean;
};

function WorkspaceRow({
    workspace,
    canManageWorkspaces,
}: {
    workspace: Workspace;
    canManageWorkspaces: boolean;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        name: workspace.name,
        slug: workspace.slug,
        timezone: workspace.timezone,
        is_active: workspace.is_active,
    });

    return (
        <div className="dp-table-row grid-cols-[auto_1fr] gap-4 xl:grid-cols-[auto_1fr_520px]">
            <div className="dp-icon-tile mt-1">
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
                <InlineEditForm
                    onSubmit={(event) => {
                        event.preventDefault();
                        patch(`/workspaces/${workspace.id}`);
                    }}
                >
                    <FormField
                        label="Name"
                        labelClassName="text-xs"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        error={errors.name}
                    />
                    <FormField
                        label="Slug"
                        labelClassName="text-xs"
                        value={data.slug}
                        onChange={(event) =>
                            setData('slug', event.target.value)
                        }
                        error={errors.slug}
                    />
                    <TimezoneSelect
                        label="Timezone"
                        labelClassName="text-xs"
                        value={data.timezone}
                        onChange={(event) =>
                            setData('timezone', event.target.value)
                        }
                    />
                    <FormActions submitLabel="Save" processing={processing}>
                        <FormCheckbox
                            label="Active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked)
                            }
                        />
                        <Button
                            size="icon"
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                router.delete(`/workspaces/${workspace.id}`)
                            }
                            aria-label={`Delete ${workspace.name}`}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </FormActions>
                </InlineEditForm>
            )}
        </div>
    );
}

export default function WorkspacesIndex({
    workspaces,
    canManageWorkspaces,
}: Props) {
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
                            {workspaces.length} workspaces
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
                    {workspaces.map((workspace) => (
                        <WorkspaceRow
                            key={workspace.id}
                            workspace={workspace}
                            canManageWorkspaces={canManageWorkspaces}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}
