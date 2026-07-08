import { Head, router, useForm } from '@inertiajs/react';
import { MailPlus, Trash2, Users } from 'lucide-react';
import {
    FormActions,
    FormField,
    FormGrid,
    FormSelect,
    InlineEditForm,
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
    members: Member[];
    workspaces: Workspace[];
    canManageMembers: boolean;
};

function MemberRow({
    member,
    workspaces,
    canManageMembers,
}: {
    member: Member;
    workspaces: Workspace[];
    canManageMembers: boolean;
}) {
    const firstWorkspace = member.workspaces?.[0];
    const { data, setData, patch, processing } = useForm({
        organization_role: member.pivot?.role ?? 'member',
        workspace_id: String(firstWorkspace?.id ?? workspaces[0]?.id ?? ''),
        workspace_role: firstWorkspace?.pivot?.role ?? 'member',
    });

    return (
        <div className="dp-table-row grid-cols-[auto_1fr] gap-4 xl:grid-cols-[auto_1fr_420px]">
            <div className="dp-icon-tile mt-1">
                <Users className="size-5" />
            </div>
            <div className="min-w-0">
                <div className="font-medium">{member.name}</div>
                <div className="text-sm text-muted-foreground">
                    {member.email}
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                    {(member.workspaces ?? []).map((workspace) => (
                        <span
                            key={workspace.id}
                            className="inline-flex items-center gap-2 rounded-md border px-2.5 py-1 text-xs"
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
                                        router.delete(
                                            `/team-members/${member.id}/workspaces/${workspace.id}`,
                                        )
                                    }
                                    aria-label={`Remove ${member.name} from ${workspace.name}`}
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}
                        </span>
                    ))}
                    {member.workspaces?.length === 0 && (
                        <span className="text-sm text-muted-foreground">
                            No workspace assigned
                        </span>
                    )}
                </div>
            </div>

            {canManageMembers && (
                <InlineEditForm
                    onSubmit={(event) => {
                        event.preventDefault();
                        patch(`/team-members/${member.id}`);
                    }}
                >
                    <FormSelect
                        label="Organization role"
                        labelClassName="text-xs"
                        value={data.organization_role}
                        onChange={(event) =>
                            setData('organization_role', event.target.value)
                        }
                        options={[
                            { label: 'Admin', value: 'admin' },
                            { label: 'Manager', value: 'manager' },
                            { label: 'Member', value: 'member' },
                        ]}
                    />
                    <FormSelect
                        label="Workspace"
                        labelClassName="text-xs"
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
                        labelClassName="text-xs"
                        value={data.workspace_role}
                        onChange={(event) =>
                            setData('workspace_role', event.target.value)
                        }
                        options={[
                            { label: 'Manager', value: 'manager' },
                            { label: 'Member', value: 'member' },
                        ]}
                    />
                    <FormActions
                        submitLabel="Save"
                        processing={processing}
                        disabled={workspaces.length === 0}
                        className="items-end [&>button]:w-full"
                    />
                </InlineEditForm>
            )}
        </div>
    );
}

export default function TeamMembersIndex({
    members,
    workspaces,
    canManageMembers,
}: Props) {
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
                            Invite users, assign workspace access, and keep
                            standup participation aligned with the team.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="secondary">
                            {members.length} members
                        </Badge>
                        {canManageMembers && (
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button>
                                        <MailPlus />
                                        Invite user
                                    </Button>
                                </SheetTrigger>
                                <SheetContent className="w-full sm:max-w-xl">
                                    <SheetHeader>
                                        <SheetTitle>Invite user</SheetTitle>
                                        <SheetDescription>
                                            Add a user to this organization and
                                            assign their first workspace.
                                        </SheetDescription>
                                    </SheetHeader>
                                    <form
                                        className="space-y-4 px-4 pb-6"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            post('/team-members', {
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
                                                options={[
                                                    {
                                                        label: 'Admin',
                                                        value: 'admin',
                                                    },
                                                    {
                                                        label: 'Manager',
                                                        value: 'manager',
                                                    },
                                                    {
                                                        label: 'Member',
                                                        value: 'member',
                                                    },
                                                ]}
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
                                            options={[
                                                {
                                                    label: 'Manager',
                                                    value: 'manager',
                                                },
                                                {
                                                    label: 'Member',
                                                    value: 'member',
                                                },
                                            ]}
                                        />
                                        <FormActions
                                            submitLabel="Invite user"
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
                                    ? 'Edit roles or remove workspace access from each row.'
                                    : 'Team membership is read-only for your role.'}
                            </p>
                        </div>
                    </div>
                    {members.map((member) => (
                        <MemberRow
                            key={member.id}
                            member={member}
                            workspaces={workspaces}
                            canManageMembers={canManageMembers}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}
