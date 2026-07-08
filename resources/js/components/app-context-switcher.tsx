import { router, usePage } from '@inertiajs/react';

type ContextOption = {
    id: number;
    name: string;
    slug: string;
    timezone: string;
};

type ContextProps = {
    organizations?: ContextOption[];
    currentOrganization?: ContextOption | null;
    workspaces?: ContextOption[];
    currentWorkspace?: ContextOption | null;
};

export function AppContextSwitcher() {
    const {
        organizations = [],
        currentOrganization,
        workspaces = [],
        currentWorkspace,
    } = usePage<ContextProps>().props;

    if (organizations.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2 px-2 pb-2 group-data-[collapsible=icon]:hidden">
            <div className="space-y-1">
                <label
                    htmlFor="organization-context"
                    className="text-[11px] font-medium uppercase tracking-wide text-sidebar-foreground/55"
                >
                    Organization
                </label>
                <select
                    id="organization-context"
                    className="h-9 w-full rounded-md border border-sidebar-border bg-sidebar-accent px-2 text-sm text-sidebar-accent-foreground outline-none transition focus:border-primary"
                    value={currentOrganization?.id ?? ''}
                    onChange={(event) =>
                        router.patch(
                            '/context',
                            {
                                organization_id: Number(event.target.value),
                                workspace_id: null,
                            },
                            {
                                preserveScroll: true,
                            },
                        )
                    }
                >
                    {organizations.map((organization) => (
                        <option key={organization.id} value={organization.id}>
                            {organization.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="space-y-1">
                <label
                    htmlFor="workspace-context"
                    className="text-[11px] font-medium uppercase tracking-wide text-sidebar-foreground/55"
                >
                    Workspace
                </label>
                <select
                    id="workspace-context"
                    className="h-9 w-full rounded-md border border-sidebar-border bg-sidebar-accent px-2 text-sm text-sidebar-accent-foreground outline-none transition focus:border-primary"
                    value={currentWorkspace?.id ?? ''}
                    disabled={workspaces.length === 0 || !currentOrganization}
                    onChange={(event) =>
                        router.patch(
                            '/context',
                            {
                                organization_id: currentOrganization?.id,
                                workspace_id: Number(event.target.value),
                            },
                            {
                                preserveScroll: true,
                            },
                        )
                    }
                >
                    {workspaces.length === 0 ? (
                        <option value="">No workspaces</option>
                    ) : (
                        workspaces.map((workspace) => (
                            <option key={workspace.id} value={workspace.id}>
                                {workspace.name}
                            </option>
                        ))
                    )}
                </select>
            </div>
        </div>
    );
}
