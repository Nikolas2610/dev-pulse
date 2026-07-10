import { Head, router, useForm } from '@inertiajs/react';
import { MailPlus, Pencil, Trash2, Users } from 'lucide-react';
import {
    FormActions,
    FormField,
    FormGrid,
    FormSelect,
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
    pivot?: {
        role?: string;
        is_active?: boolean;
    };
};

type Member = {
    id: number;
    name: string;
    email: string;
    pivot?: {
        role?: string;
    };
    workspaces?: Workspace[];
};

type Props = {
    indexEndpoint: string;
    workspaces: Workspace[];
    canManageMembers: boolean;
};

const organizationRoleOptions = [
    { label: 'Admin', value: 'admin' },
    { label: 'Manager', value: 'manager' },
    { label: 'Member', value: 'member' },
];

const workspaceRoleOptions = [
    { label: 'Manager', value: 'manager' },
    { label: 'Member', value: 'member' },
];

function removeWorkspace(member: Member, workspace: Workspace) {
    router.delete(`/team-members/${member.id}/workspaces/${workspace.id}`, {
        preserveScroll: true,
    });
}

function MemberEditSheet({
    member,
    workspaces,
}: {
    member: Member;
    workspaces: Workspace[];
}) {
    const firstWorkspace = member.workspaces?.[0];
    const { data, setData, patch, processing } = useForm({
        organization_role: member.pivot?.role ?? 'member',
        workspace_id: String(firstWorkspace?.id ?? workspaces[0]?.id ?? ''),
        workspace_role: firstWorkspace?.pivot?.role ?? 'member',
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
                    <SheetTitle>{member.name}</SheetTitle>
                    <SheetDescription>
                        Update organization role, add workspace access, or
                        remove this member from a workspace.
                    </SheetDescription>
                </SheetHeader>

                <form
                    className="space-y-5 px-4 pb-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        patch(`/team-members/${member.id}`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="rounded-md border bg-card/50 p-4">
                        <div className="text-sm font-medium">
                            {member.email}
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            Organization role: {member.pivot?.role ?? 'member'}
                        </div>
                    </div>

                    <FormSelect
                        label="Organization role"
                        value={data.organization_role}
                        onChange={(event) =>
                            setData('organization_role', event.target.value)
                        }
                        options={organizationRoleOptions}
                    />

                    <FormGrid>
                        <FormSelect
                            label="Workspace"
                            value={data.workspace_id}
                            onChange={(event) =>
                                setData('workspace_id', event.target.value)
                            }
                            options={workspaces.map((workspace) => ({
                                label: workspace.name,
                                value: workspace.id,
                            }))}
                        />
                        <FormSelect
                            label="Workspace role"
                            value={data.workspace_role}
                            onChange={(event) =>
                                setData('workspace_role', event.target.value)
                            }
                            options={workspaceRoleOptions}
                        />
                    </FormGrid>

                    <FormActions
                        submitLabel="Save member"
                        processing={processing}
                        disabled={workspaces.length === 0}
                    />
                </form>

                <div className="border-t px-4 py-5">
                    <div className="mb-3 text-sm font-medium">
                        Workspace access
                    </div>
                    <div className="space-y-2">
                        {(member.workspaces ?? []).map((workspace) => (
                            <div
                                key={workspace.id}
                                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                            >
                                <div>
                                    <div className="font-medium">
                                        {workspace.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {workspace.pivot?.role ?? 'member'}
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        removeWorkspace(member, workspace)
                                    }
                                >
                                    <Trash2 />
                                    Remove
                                </Button>
                            </div>
                        ))}
                        {(member.workspaces ?? []).length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                This member has no workspace access.
                            </p>
                        )}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function MemberRow({
    member,
    workspaces,
    canManageMembers,
}: {
    member: Member;
    workspaces: Workspace[];
    canManageMembers: boolean;
}) {
    return (
        <div className="dp-table-row grid-cols-[auto_1fr_auto] items-center gap-4">
            <div className="dp-icon-tile">
                <Users className="size-5" />
            </div>
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="font-medium">{member.name}</div>
                    <Badge variant="secondary">
                        {member.pivot?.role ?? 'member'}
                    </Badge>
                </div>
                <div className="text-sm text-muted-foreground">
                    {member.email}
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                    {(member.workspaces ?? []).map((workspace) => (
                        <span
                            key={workspace.id}
                            className="inline-flex items-center gap-2 rounded-md border bg-background px-2.5 py-1 text-xs"
                        >
                            {workspace.name}
                            <span className="text-muted-foreground">
                                {workspace.pivot?.role ?? 'member'}
                            </span>
                            {canManageMembers && (
                                <button
                                    type="button"
                                    className="text-muted-foreground transition hover:text-destructive"
                                    onClick={() =>
                                        removeWorkspace(member, workspace)
                                    }
                                    aria-label={`Remove ${member.name} from ${workspace.name}`}
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}
                        </span>
                    ))}
                    {(member.workspaces ?? []).length === 0 && (
                        <span className="text-sm text-muted-foreground">
                            No workspace access
                        </span>
                    )}
                </div>
            </div>

            {canManageMembers && (
                <MemberEditSheet member={member} workspaces={workspaces} />
            )}
        </div>
    );
}

export default function TeamMembersIndex({
    indexEndpoint,
    workspaces,
    canManageMembers,
}: Props) {
    const { page, query, loading, error, updateQuery, setFilter } =
        useIndexData<Member>(indexEndpoint, { sort: 'name', direction: 'asc' });
    const members = page.data;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        organization_role: 'member',
        workspace_id: String(workspaces[0]?.id ?? ''),
        workspace_role: 'member',
    });

    return (
        <>
            <Head title="Team Members" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">People</div>
                        <h1 className="dp-title">Team members</h1>
                        <p className="dp-subtitle">
                            Invite by email, assign workspace access, and keep
                            standup recipients aligned with the active team.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="secondary">{page.total} members</Badge>
                        {canManageMembers && (
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button>
                                        <MailPlus />
                                        Invite by email
                                    </Button>
                                </SheetTrigger>
                                <SheetContent className="w-full sm:max-w-xl">
                                    <SheetHeader>
                                        <SheetTitle>Invite user</SheetTitle>
                                        <SheetDescription>
                                            Send a setup email and assign the
                                            first workspace.
                                        </SheetDescription>
                                    </SheetHeader>
                                    <form
                                        className="space-y-4 px-4 pb-6"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            post('/team-members', {
                                                preserveScroll: true,
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
                                                placeholder="Ada Lovelace"
                                                error={errors.name}
                                            />
                                            <FormField
                                                label="Email"
                                                type="email"
                                                value={data.email}
                                                onChange={(event) =>
                                                    setData(
                                                        'email',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="ada@example.com"
                                                error={errors.email}
                                            />
                                        </FormGrid>
                                        <FormGrid>
                                            <FormSelect
                                                label="Organization role"
                                                value={data.organization_role}
                                                onChange={(event) =>
                                                    setData(
                                                        'organization_role',
                                                        event.target.value,
                                                    )
                                                }
                                                options={
                                                    organizationRoleOptions
                                                }
                                            />
                                            <FormSelect
                                                label="Workspace"
                                                value={data.workspace_id}
                                                onChange={(event) =>
                                                    setData(
                                                        'workspace_id',
                                                        event.target.value,
                                                    )
                                                }
                                                options={workspaces.map(
                                                    (workspace) => ({
                                                        label: workspace.name,
                                                        value: workspace.id,
                                                    }),
                                                )}
                                            />
                                        </FormGrid>
                                        <FormSelect
                                            label="Workspace role"
                                            value={data.workspace_role}
                                            onChange={(event) =>
                                                setData(
                                                    'workspace_role',
                                                    event.target.value,
                                                )
                                            }
                                            options={workspaceRoleOptions}
                                        />
                                        <FormActions
                                            submitLabel="Send invite"
                                            processing={processing}
                                            disabled={workspaces.length === 0}
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
                                Organization roster
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {canManageMembers
                                    ? 'Open a member drawer to edit access. Remove workspace access directly from a workspace chip.'
                                    : 'Team membership is read-only for your role.'}
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
                            { label: 'Email A-Z', value: 'email' },
                            { label: 'Role A-Z', value: 'role' },
                            {
                                label: 'Newest first',
                                value: 'created_at',
                                direction: 'desc',
                            },
                        ]}
                    >
                        <IndexFilterSelect
                            label="All roles"
                            value={query.filters.role ?? ''}
                            onChange={(value) => setFilter('role', value)}
                            options={organizationRoleOptions}
                        />
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
                    </IndexDataControls>
                    {members.map((member) => (
                        <MemberRow
                            key={member.id}
                            member={member}
                            workspaces={workspaces}
                            canManageMembers={canManageMembers}
                        />
                    ))}
                    {!members.length && (
                        <IndexDataState
                            loading={loading}
                            error={error}
                            empty="No team members match these filters."
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
