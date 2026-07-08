import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Cable,
    Hash,
    RefreshCw,
    UserPlus,
    Users,
} from 'lucide-react';
import {
    FormActions,
    FormField,
    FormSelect,
} from '@/components/forms/form-components';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export default function SlackIntegration({
    integration,
    slackUsers,
    isConfigured,
    hasWorkspaceCredentials,
    hasSigningSecret,
    redirectUri,
    interactionsUrl,
    eventsUrl,
}: {
    integration: any | null;
    slackUsers: SlackUser[];
    isConfigured: boolean;
    hasWorkspaceCredentials: boolean;
    hasSigningSecret: boolean;
    redirectUri: string;
    interactionsUrl: string;
    eventsUrl: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        client_id: integration?.client_id ?? '',
        client_secret: '',
        signing_secret: '',
    });
    const importForm = useForm({
        integration_user_ids: [] as number[],
        organization_role: 'member',
        workspace_role: 'member',
    });
    const selectedImportIds = new Set(importForm.data.integration_user_ids);
    const importableUsers = slackUsers.filter(
        (slackUser) => !slackUser.is_linked && slackUser.external_email,
    );

    const submitCredentials = (event: React.FormEvent) => {
        event.preventDefault();
        post('/integrations/slack/credentials', {
            preserveScroll: true,
            onSuccess: () => {
                setData('client_secret', '');
                setData('signing_secret', '');
            },
        });
    };
    const toggleImportUser = (integrationUserId: number, checked: boolean) => {
        const next = new Set(selectedImportIds);

        if (checked) {
            next.add(integrationUserId);
        } else {
            next.delete(integrationUserId);
        }

        importForm.setData('integration_user_ids', Array.from(next));
    };

    return (
        <>
            <Head title="Slack Integration" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Integration</div>
                        <h1 className="dp-title">Slack workspace</h1>
                        <p className="dp-subtitle">
                            Connect Slack to deliver standup prompts, sync
                            people and channels, and publish the daily summary.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/integrations/slack/connect">
                            <Cable />
                            {integration ? 'Reconnect Slack' : 'Connect Slack'}
                        </Link>
                    </Button>
                </div>

                {!isConfigured && (
                    <div className="dp-panel border-amber-300/50 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                        <div className="flex gap-3 p-4">
                            <AlertTriangle className="mt-0.5 size-5 shrink-0" />
                            <div>
                                <h2 className="font-semibold">
                                    Slack OAuth is not configured
                                </h2>
                                <p className="mt-1 text-sm opacity-85">
                                    Save a Slack client ID and client secret for
                                    this workspace before connecting Slack.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid gap-5 lg:grid-cols-[1fr_360px]">
                    <div className="dp-panel">
                        <div className="dp-panel-header">
                            <div>
                                <h2 className="font-semibold">
                                    Workspace Slack app
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {hasWorkspaceCredentials
                                        ? 'Credentials are saved for the current workspace.'
                                        : 'Credentials are not saved for the current workspace.'}
                                    {hasSigningSecret
                                        ? ' Slack interactions can be verified.'
                                        : ' Add a signing secret to enable Slack modals.'}
                                </p>
                            </div>
                            <Badge
                                variant={
                                    hasWorkspaceCredentials
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {hasWorkspaceCredentials ? 'Saved' : 'Missing'}
                            </Badge>
                        </div>
                        <form
                            className="grid gap-4 p-5 md:grid-cols-2"
                            onSubmit={submitCredentials}
                        >
                            <FormField
                                label="Client ID"
                                value={data.client_id}
                                onChange={(event) =>
                                    setData('client_id', event.target.value)
                                }
                                error={errors.client_id}
                            />
                            <FormField
                                label="Client secret"
                                type="password"
                                value={data.client_secret}
                                placeholder={
                                    hasWorkspaceCredentials
                                        ? 'Enter a new secret to replace it'
                                        : ''
                                }
                                onChange={(event) =>
                                    setData('client_secret', event.target.value)
                                }
                                error={errors.client_secret}
                            />
                            <FormField
                                label="Signing secret"
                                type="password"
                                value={data.signing_secret}
                                placeholder={
                                    hasSigningSecret
                                        ? 'Enter a new signing secret to replace it'
                                        : ''
                                }
                                onChange={(event) =>
                                    setData(
                                        'signing_secret',
                                        event.target.value,
                                    )
                                }
                                error={errors.signing_secret}
                            />
                            <FormActions
                                className="md:col-span-2"
                                submitLabel="Save credentials"
                                processing={processing}
                            />
                        </form>
                    </div>

                    <div className="dp-panel h-fit">
                        <div className="dp-panel-body">
                            <div className="dp-kicker">OAuth callback URL</div>
                            <div className="mt-2 overflow-x-auto rounded-md border bg-background px-3 py-2 font-mono text-sm">
                                {redirectUri}
                            </div>
                            <div className="dp-kicker mt-4">
                                Interactivity request URL
                            </div>
                            <div className="mt-2 overflow-x-auto rounded-md border bg-background px-3 py-2 font-mono text-sm">
                                {interactionsUrl}
                            </div>
                            <div className="dp-kicker mt-4">
                                Event request URL
                            </div>
                            <div className="mt-2 overflow-x-auto rounded-md border bg-background px-3 py-2 font-mono text-sm">
                                {eventsUrl}
                            </div>
                        </div>
                    </div>
                </div>

                {integration ? (
                    <div className="grid gap-5 lg:grid-cols-[1fr_340px]">
                        <div className="dp-panel dp-signal">
                            <div className="dp-panel-header">
                                <div>
                                    <h2 className="font-semibold">
                                        {integration.name}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Bot connection is stored and ready for
                                        standup automation.
                                    </p>
                                </div>
                                <Badge>Connected</Badge>
                            </div>
                            <div className="grid gap-4 p-5 md:grid-cols-2">
                                <IntegrationMetric
                                    icon={<Users className="size-4" />}
                                    label="Synced users"
                                    value={integration.users_count}
                                />
                                <IntegrationMetric
                                    icon={<Hash className="size-4" />}
                                    label="Synced channels"
                                    value={integration.channels_count}
                                />
                            </div>
                        </div>

                        <div className="dp-panel h-fit">
                            <div className="border-b px-4 py-3 font-semibold">
                                Sync actions
                            </div>
                            <div className="space-y-3 p-4">
                                <Button
                                    className="w-full justify-start"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            `/integrations/slack/${integration.id}/sync-users`,
                                        )
                                    }
                                >
                                    <RefreshCw />
                                    Sync users
                                </Button>
                                <Button
                                    className="w-full justify-start"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            `/integrations/slack/${integration.id}/sync-channels`,
                                        )
                                    }
                                >
                                    <RefreshCw />
                                    Sync channels
                                </Button>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="dp-panel dp-signal">
                        <div className="dp-panel-body">
                            <h2 className="font-semibold">
                                Slack is not connected
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Connect a workspace before scheduling Slack DMs
                                or posting summaries.
                            </p>
                        </div>
                    </div>
                )}

                {integration && (
                    <div className="dp-panel">
                        <div className="dp-panel-header">
                            <div>
                                <h2 className="font-semibold">
                                    Synced Slack users
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Import selected Slack users into DevPulse so
                                    they can be chosen as schedule recipients.
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {slackUsers.length} synced
                            </Badge>
                        </div>
                        {slackUsers.length === 0 ? (
                            <div className="p-5 text-sm text-muted-foreground">
                                No Slack users are synced yet. Run Sync users
                                after connecting Slack.
                            </div>
                        ) : (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    importForm.post(
                                        `/integrations/slack/${integration.id}/import-users`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                importForm.setData(
                                                    'integration_user_ids',
                                                    [],
                                                ),
                                        },
                                    );
                                }}
                            >
                                <div className="divide-y">
                                    {slackUsers.map((slackUser) => {
                                        const canImport =
                                            !slackUser.is_linked &&
                                            Boolean(slackUser.external_email);

                                        return (
                                            <label
                                                key={slackUser.id}
                                                className="grid gap-3 px-5 py-3 text-sm md:grid-cols-[auto_1fr_auto]"
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="mt-1"
                                                    disabled={!canImport}
                                                    checked={selectedImportIds.has(
                                                        slackUser.id,
                                                    )}
                                                    onChange={(event) =>
                                                        toggleImportUser(
                                                            slackUser.id,
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                />
                                                <span className="min-w-0">
                                                    <span className="block font-medium">
                                                        {slackUser.display_name ??
                                                            slackUser.external_username ??
                                                            slackUser.external_user_id}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {slackUser.external_email ??
                                                            'No email from Slack'}
                                                    </span>
                                                    {slackUser.linked_user && (
                                                        <span className="mt-1 block text-xs text-muted-foreground">
                                                            Linked to{' '}
                                                            {
                                                                slackUser
                                                                    .linked_user
                                                                    .name
                                                            }
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="flex items-center justify-start md:justify-end">
                                                    <Badge
                                                        variant={
                                                            slackUser.is_linked
                                                                ? 'default'
                                                                : canImport
                                                                  ? 'secondary'
                                                                  : 'outline'
                                                        }
                                                    >
                                                        {slackUser.is_linked
                                                            ? 'Linked'
                                                            : canImport
                                                              ? 'Ready'
                                                              : 'No email'}
                                                    </Badge>
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                                {importForm.errors.integration_user_ids && (
                                    <p className="px-5 pt-3 text-sm text-destructive">
                                        {importForm.errors.integration_user_ids}
                                    </p>
                                )}
                                <div className="flex flex-wrap items-end gap-3 border-t p-5">
                                    <FormSelect
                                        label="Organization role"
                                        className="min-w-48"
                                        value={
                                            importForm.data.organization_role
                                        }
                                        onChange={(event) =>
                                            importForm.setData(
                                                'organization_role',
                                                event.target.value,
                                            )
                                        }
                                        options={[
                                            { label: 'Admin', value: 'admin' },
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
                                        label="Workspace role"
                                        className="min-w-48"
                                        value={importForm.data.workspace_role}
                                        onChange={(event) =>
                                            importForm.setData(
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
                                    <Button
                                        disabled={
                                            importForm.processing ||
                                            importForm.data.integration_user_ids
                                                .length === 0 ||
                                            importableUsers.length === 0
                                        }
                                    >
                                        <UserPlus />
                                        Import selected
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

type SlackUser = {
    id: number;
    external_user_id: string;
    external_username?: string | null;
    external_email?: string | null;
    display_name?: string | null;
    avatar_url?: string | null;
    is_linked: boolean;
    linked_user?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

function IntegrationMetric({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: number;
}) {
    return (
        <div className="rounded-lg border bg-background p-4">
            <div className="mb-3 flex items-center justify-between text-muted-foreground">
                <span className="text-xs font-semibold tracking-[0.16em] uppercase">
                    {label}
                </span>
                {icon}
            </div>
            <div className="text-2xl font-semibold">{value}</div>
        </div>
    );
}
