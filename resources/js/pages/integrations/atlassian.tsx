import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, RefreshCw, Trash2 } from 'lucide-react';
import {
    FormMultiSelect,
    FormSelect,
} from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    provider: 'jira' | 'bitbucket';
    integration: any | null;
    resources: any[];
    externalUsers: any[];
    workspaces: any[];
    availableConnections: any[];
};

export default function AtlassianIntegration({
    provider,
    integration,
    resources,
    externalUsers,
    workspaces,
    availableConnections,
}: Props) {
    const label = provider === 'jira' ? 'Jira Cloud' : 'Bitbucket Cloud';

    return (
        <>
            <Head title={label} />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">
                            Engineering integrations
                        </div>
                        <h1 className="dp-title">{label}</h1>
                        <p className="dp-subtitle">
                            Connect, scope resources to workspaces, and map
                            provider identities to DevPulse users.
                        </p>
                    </div>
                    {integration ? (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/integrations/${provider}/${integration.id}/sync`,
                                    )
                                }
                            >
                                <RefreshCw />
                                Sync now
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    router.delete(
                                        `/integrations/${provider}/${integration.id}`,
                                    )
                                }
                            >
                                <Trash2 />
                                Disconnect
                            </Button>
                        </div>
                    ) : (
                        <Button asChild>
                            <Link href={`/integrations/${provider}/connect`}>
                                <ExternalLink />
                                Connect {label}
                            </Link>
                        </Button>
                    )}
                </div>

                {!integration ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Not connected</CardTitle>
                            <CardDescription>
                                Add the OAuth credentials in the environment,
                                then authorize DevPulse.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Metric
                                label="Status"
                                value={
                                    integration.is_active
                                        ? 'Connected'
                                        : 'Attention'
                                }
                            />
                            <Metric
                                label={
                                    provider === 'jira'
                                        ? 'Projects'
                                        : 'Repositories'
                                }
                                value={String(resources.length)}
                            />
                            <Metric
                                label="Mapped users"
                                value={`${externalUsers.filter((user) => user.user_id).length}/${externalUsers.length}`}
                            />
                        </div>
                        {integration.settings?.last_error && (
                            <div className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                                {integration.settings.last_error}
                            </div>
                        )}
                        {integration.settings?.webhook_error && (
                            <div className="rounded-md border p-4 text-sm text-muted-foreground">
                                Webhook registration failed; 15-minute polling
                                remains active.{' '}
                                {integration.settings.webhook_error}
                            </div>
                        )}
                        <ConnectionScope
                            provider={provider}
                            integration={integration}
                            availableConnections={availableConnections}
                        />
                        <Card>
                            <CardHeader>
                                <CardTitle>Workspace resources</CardTitle>
                                <CardDescription>
                                    Select the Jira projects or repositories
                                    available to each DevPulse workspace.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-5">
                                {workspaces.map((workspace) => (
                                    <ResourceAssignment
                                        key={workspace.id}
                                        provider={provider}
                                        integrationId={integration.id}
                                        workspace={workspace}
                                        resources={resources}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                        {provider === 'bitbucket' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Repository ownership</CardTitle>
                                    <CardDescription>
                                        Assign repositories to the developers
                                        who should see their pull requests.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    {resources.map((repository) => (
                                        <RepositoryUsers
                                            key={repository.id}
                                            integrationId={integration.id}
                                            repository={repository}
                                            users={workspaces
                                                .flatMap(
                                                    (workspace) =>
                                                        workspace.users ?? [],
                                                )
                                                .filter(
                                                    (user, index, all) =>
                                                        all.findIndex(
                                                            (candidate) =>
                                                                candidate.id ===
                                                                user.id,
                                                        ) === index,
                                                )}
                                        />
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                        <Card>
                            <CardHeader>
                                <CardTitle>Identity mapping</CardTitle>
                                <CardDescription>
                                    Email matches are automatic. Use this list
                                    when Jira hides emails or Bitbucket accounts
                                    differ.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                {externalUsers.map((externalUser) => (
                                    <UserMapping
                                        key={externalUser.id}
                                        provider={provider}
                                        integrationId={integration.id}
                                        externalUser={externalUser}
                                        users={workspaces
                                            .flatMap(
                                                (workspace) =>
                                                    workspace.users ?? [],
                                            )
                                            .filter(
                                                (user, index, all) =>
                                                    all.findIndex(
                                                        (candidate) =>
                                                            candidate.id ===
                                                            user.id,
                                                    ) === index,
                                            )}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

function ConnectionScope({ provider, integration, availableConnections }: any) {
    const form = useForm(
        provider === 'jira'
            ? { cloud_id: integration.settings?.cloud_id ?? '' }
            : { workspace_slugs: integration.settings?.workspace_slugs ?? [] },
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    {provider === 'jira' ? 'Jira site' : 'Bitbucket workspaces'}
                </CardTitle>
                <CardDescription>
                    Choose which provider account DevPulse is allowed to
                    synchronize.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(
                            `/integrations/${provider}/${integration.id}/connection`,
                        );
                    }}
                >
                    {provider === 'jira' ? (
                        <FormSelect
                            label="Jira site"
                            value={(form.data as any).cloud_id}
                            onChange={(event) =>
                                form.setData(
                                    'cloud_id' as never,
                                    event.target.value as never,
                                )
                            }
                            options={availableConnections.map((site: any) => ({
                                value: site.id,
                                label: `${site.name} — ${site.url}`,
                            }))}
                        />
                    ) : (
                        <FormMultiSelect
                            label="Bitbucket workspaces"
                            value={(form.data as any).workspace_slugs}
                            onValueChange={(value) =>
                                form.setData(
                                    'workspace_slugs' as never,
                                    value as never,
                                )
                            }
                            options={availableConnections.map(
                                (workspace: any) => ({
                                    value: workspace.slug,
                                    label: workspace.name ?? workspace.slug,
                                }),
                            )}
                        />
                    )}
                    <Button className="self-start" disabled={form.processing}>
                        Save connection scope
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function ResourceAssignment({
    provider,
    integrationId,
    workspace,
    resources,
}: any) {
    const selected = resources
        .filter((resource: any) =>
            resource.workspaces?.some(
                (assigned: any) => assigned.id === workspace.id,
            ),
        )
        .map((resource: any) => resource.id);
    const initialStatusFilters = Object.fromEntries(
        resources.map((resource: any) => {
            const assignment = resource.workspaces?.find(
                (assigned: any) => assigned.id === workspace.id,
            );
            const raw = assignment?.pivot?.included_status_ids;
            let statusIds: string[] | null = null;

            if (Array.isArray(raw)) {
                statusIds = raw.map(String);
            } else if (typeof raw === 'string') {
                try {
                    const parsed = JSON.parse(raw);
                    statusIds = Array.isArray(parsed)
                        ? parsed.map(String)
                        : null;
                } catch {
                    statusIds = null;
                }
            }

            return [String(resource.id), statusIds];
        }),
    );
    const form = useForm({
        workspace_id: workspace.id,
        resource_ids: selected,
        status_filters: initialStatusFilters as Record<string, string[] | null>,
    });

    return (
        <form
            className="flex flex-col gap-3 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    `/integrations/${provider}/${integrationId}/resources`,
                );
            }}
        >
            <FormMultiSelect
                label={workspace.name}
                value={form.data.resource_ids}
                onValueChange={(value) =>
                    form.setData('resource_ids', value.map(Number))
                }
                options={resources.map((resource: any) => ({
                    value: resource.id,
                    label:
                        provider === 'jira'
                            ? `${resource.project_key} — ${resource.name}`
                            : resource.full_name,
                }))}
            />
            {provider === 'jira' &&
                resources
                    .filter((resource: any) =>
                        form.data.resource_ids.includes(resource.id),
                    )
                    .map((resource: any) => {
                        const projectId = String(resource.id);
                        const selectedStatuses =
                            form.data.status_filters[projectId];

                        return (
                            <div
                                key={resource.id}
                                className="rounded-md border bg-muted/20 p-3"
                            >
                                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div className="text-sm font-medium">
                                            {resource.project_key} statuses
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {selectedStatuses === null
                                                ? 'Default: every status except Done'
                                                : 'Only the selected statuses will appear in standups'}
                                        </div>
                                    </div>
                                    {selectedStatuses !== null && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                form.setData('status_filters', {
                                                    ...form.data.status_filters,
                                                    [projectId]: null,
                                                })
                                            }
                                        >
                                            Use default
                                        </Button>
                                    )}
                                </div>
                                <FormMultiSelect
                                    label="Included Jira statuses"
                                    value={selectedStatuses ?? []}
                                    onValueChange={(value) =>
                                        form.setData('status_filters', {
                                            ...form.data.status_filters,
                                            [projectId]: value.length
                                                ? value.map(String)
                                                : null,
                                        })
                                    }
                                    options={(resource.statuses_json ?? []).map(
                                        (status: any) => ({
                                            value: String(status.id),
                                            label: `${status.name} · ${status.category_key || 'uncategorized'}`,
                                        }),
                                    )}
                                    description="Leave empty to include every non-Done status."
                                />
                            </div>
                        );
                    })}
            <Button className="self-start" disabled={form.processing}>
                Save assignment
            </Button>
        </form>
    );
}

function RepositoryUsers({ integrationId, repository, users }: any) {
    const form = useForm({
        user_ids: (repository.users ?? []).map((user: any) => user.id),
    });

    return (
        <form
            className="flex flex-col gap-3 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    `/integrations/bitbucket/${integrationId}/repositories/${repository.id}/users`,
                );
            }}
        >
            <FormMultiSelect
                label={repository.full_name}
                value={form.data.user_ids}
                onValueChange={(value) =>
                    form.setData('user_ids', value.map(Number))
                }
                options={users.map((user: any) => ({
                    value: user.id,
                    label: user.name,
                }))}
            />
            <Button
                className="self-start"
                variant="outline"
                disabled={form.processing}
            >
                Save users
            </Button>
        </form>
    );
}

function UserMapping({ provider, integrationId, externalUser, users }: any) {
    const form = useForm({ user_id: externalUser.user_id ?? '' });

    return (
        <form
            className="grid items-end gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1fr_auto]"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    `/integrations/${provider}/${integrationId}/users/${externalUser.id}`,
                );
            }}
        >
            <div>
                <div className="font-medium">
                    {externalUser.display_name ??
                        externalUser.external_username}
                </div>
                <div className="text-sm text-muted-foreground">
                    {externalUser.external_email ?? 'Email hidden by provider'}
                </div>
            </div>
            <FormSelect
                label="DevPulse user"
                value={form.data.user_id}
                onChange={(event) =>
                    form.setData('user_id', event.target.value)
                }
                options={[
                    { value: '', label: 'Unmapped' },
                    ...users.map((user: any) => ({
                        value: user.id,
                        label: `${user.name} — ${user.email}`,
                    })),
                ]}
            />
            <Button variant="outline" disabled={form.processing}>
                Map user
            </Button>
        </form>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}
