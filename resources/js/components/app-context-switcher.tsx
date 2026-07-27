import { router, usePage } from '@inertiajs/react';
import { FormSelect } from '@/components/forms/form-components';

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
        <div className="flex w-full min-w-0 flex-col gap-2 px-2 pb-2 group-data-[collapsible=icon]:hidden">
            <FormSelect
                id="organization-context"
                label="Organization"
                className="gap-1"
                labelClassName="text-[11px] uppercase tracking-wide text-sidebar-foreground/55"
                controlClassName="border-sidebar-border bg-sidebar-accent text-sidebar-accent-foreground"
                value={currentOrganization?.id ?? ''}
                onChange={(event) =>
                    router.patch(
                        '/context',
                        {
                            organization_id: Number(event.target.value),
                            workspace_id: null,
                        },
                        {
                            preserveScroll: false,
                            preserveState: false,
                        },
                    )
                }
                options={organizations.map((organization) => ({
                    label: organization.name,
                    value: organization.id,
                }))}
            />

            <FormSelect
                id="workspace-context"
                label="Workspace"
                className="gap-1"
                labelClassName="text-[11px] uppercase tracking-wide text-sidebar-foreground/55"
                controlClassName="border-sidebar-border bg-sidebar-accent text-sidebar-accent-foreground"
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
                            preserveScroll: false,
                            preserveState: false,
                        },
                    )
                }
                options={
                    workspaces.length
                        ? workspaces.map((workspace) => ({
                              label: workspace.name,
                              value: workspace.id,
                          }))
                        : [{ label: 'No workspaces', value: '' }]
                }
            />
        </div>
    );
}
