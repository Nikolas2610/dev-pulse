import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CirclePlus,
    MessageSquareText,
    PlugZap,
    Trash2,
    Users,
} from 'lucide-react';
import {
    FormActions,
    FormField,
    FormGrid,
    FormMessage,
    FormSelect,
    TimezoneSelect,
} from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    defaultReminderTemplate,
    ReminderSettings,
} from '@/pages/schedules/reminder-settings';
import { QuestionEditor } from '@/pages/templates/question-editor';
import type { TemplateQuestionForm } from '@/pages/templates/question-editor';

type Organization = { id: number; name: string; timezone: string };
type Workspace = { id: number; name: string; timezone: string };
type Channel = { id: number; name: string; type: string };
type Integration = {
    id: number;
    provider: 'slack' | 'discord';
    name: string;
    client_id?: string | null;
    is_connected: boolean;
    has_credentials: boolean;
    channels: Channel[];
};
type Template = {
    id: number;
    name: string;
    description: string | null;
    questions: TemplateQuestionForm[];
};
type Props = {
    onboarding: { currentStep: string; furthestStep: string };
    organization: Organization | null;
    workspace: Workspace | null;
    template: Template | null;
    integrations: Integration[];
    availableWorkItemProviders: Array<'jira' | 'bitbucket'>;
    membersCount: number;
    slackConfiguredGlobally: boolean;
};

function StepHeader({
    eyebrow,
    title,
    description,
}: {
    eyebrow: string;
    title: string;
    description: string;
}) {
    return (
        <div className="mb-6 sm:mb-7">
            <div className="font-mono text-[10px] font-semibold tracking-[0.16em] text-primary uppercase sm:text-[11px] sm:tracking-[0.18em]">
                {eyebrow}
            </div>
            <h2 className="mt-3 max-w-3xl text-2xl font-semibold tracking-tight sm:text-4xl sm:tracking-[-0.04em]">
                {title}
            </h2>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function Panel({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'min-w-0 rounded-lg border bg-card p-4 shadow-sm sm:rounded-xl sm:p-7',
                className,
            )}
        >
            {children}
        </div>
    );
}

function OrganizationStep({
    organization,
    workspace,
}: Pick<Props, 'organization' | 'workspace'>) {
    const timezone =
        organization?.timezone ??
        Intl.DateTimeFormat().resolvedOptions().timeZone ??
        'Asia/Nicosia';
    const { data, setData, post, processing, errors } = useForm({
        organization_name: organization?.name ?? '',
        workspace_name: workspace?.name ?? '',
        timezone,
    });

    return (
        <>
            <Head title="Set up your organization" />
            <StepHeader
                eyebrow="Step 2 · Organization"
                title="Give the pulse a home."
                description="Set the organization your team belongs to and the first workspace where standups will run."
            />
            <Panel>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/onboarding/organization');
                    }}
                    className="space-y-5"
                >
                    <FormGrid>
                        <FormField
                            required
                            label="Organization name"
                            value={data.organization_name}
                            onChange={(event) =>
                                setData('organization_name', event.target.value)
                            }
                            placeholder="Acme Engineering"
                            error={errors.organization_name}
                        />
                        <FormField
                            required
                            label="Initial workspace"
                            value={data.workspace_name}
                            onChange={(event) =>
                                setData('workspace_name', event.target.value)
                            }
                            placeholder="Product team"
                            error={errors.workspace_name}
                        />
                    </FormGrid>
                    <TimezoneSelect
                        required
                        label="Team timezone"
                        value={data.timezone}
                        onChange={(event) =>
                            setData('timezone', event.target.value)
                        }
                        error={errors.timezone}
                        description="Used for schedules, reminders, and daily summaries."
                    />
                    <FormActions
                        submitLabel={
                            organization
                                ? 'Save and continue'
                                : 'Create organization'
                        }
                        processing={processing}
                    />
                </form>
            </Panel>
        </>
    );
}

function ConnectionStatus({
    connected,
    label,
}: {
    connected: boolean;
    label: string;
}) {
    return (
        <div
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                connected
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'bg-muted text-muted-foreground',
            )}
        >
            {connected ? (
                <CheckCircle2 className="size-3.5" />
            ) : (
                <PlugZap className="size-3.5" />
            )}
            {connected ? `${label} connected` : 'Not connected'}
        </div>
    );
}

function IntegrationsStep({
    integrations,
    slackConfiguredGlobally,
}: Pick<Props, 'integrations' | 'slackConfiguredGlobally'>) {
    const slack = integrations.find(
        (integration) => integration.provider === 'slack',
    );
    const discord = integrations.find(
        (integration) => integration.provider === 'discord',
    );
    const slackForm = useForm({
        client_id: slack?.client_id ?? '',
        client_secret: '',
        signing_secret: '',
    });
    const discordForm = useForm({
        name: discord?.name ?? 'Discord',
        webhook_url: '',
    });

    return (
        <>
            <Head title="Connect integrations" />
            <StepHeader
                eyebrow="Step 3 · Integrations · Optional"
                title="Choose where the pulse travels."
                description="Connect Slack for direct prompts and channels, Discord for webhook delivery, or continue with email only."
            />
            <div className="grid gap-5 xl:grid-cols-2">
                <Panel className="flex flex-col">
                    <div className="mb-5 flex flex-col items-start gap-3 min-[440px]:flex-row min-[440px]:justify-between">
                        <div className="min-w-0">
                            <h3 className="font-semibold">Slack</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Prompts, responses, and summaries.
                            </p>
                        </div>
                        <ConnectionStatus
                            connected={Boolean(slack?.is_connected)}
                            label="Slack"
                        />
                    </div>
                    <form
                        className="flex flex-1 flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            slackForm.post('/onboarding/integrations/slack', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        {!slackConfiguredGlobally && (
                            <>
                                <FormField
                                    required
                                    label="Client ID"
                                    value={slackForm.data.client_id}
                                    onChange={(event) =>
                                        slackForm.setData(
                                            'client_id',
                                            event.target.value,
                                        )
                                    }
                                    error={slackForm.errors.client_id}
                                />
                                <FormField
                                    required={!slack?.has_credentials}
                                    type="password"
                                    label="Client secret"
                                    value={slackForm.data.client_secret}
                                    onChange={(event) =>
                                        slackForm.setData(
                                            'client_secret',
                                            event.target.value,
                                        )
                                    }
                                    error={slackForm.errors.client_secret}
                                    placeholder={
                                        slack?.has_credentials
                                            ? 'Leave blank to keep current secret'
                                            : ''
                                    }
                                />
                                <FormField
                                    type="password"
                                    label="Signing secret"
                                    value={slackForm.data.signing_secret}
                                    onChange={(event) =>
                                        slackForm.setData(
                                            'signing_secret',
                                            event.target.value,
                                        )
                                    }
                                    error={slackForm.errors.signing_secret}
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={slackForm.processing}
                                >
                                    {slackForm.processing
                                        ? 'Saving…'
                                        : 'Save Slack credentials'}
                                </Button>
                            </>
                        )}
                        {(slackConfiguredGlobally ||
                            slack?.has_credentials) && (
                            <Button asChild className="mt-auto">
                                <Link href="/integrations/slack/connect?onboarding=1">
                                    {slack?.is_connected
                                        ? 'Reconnect Slack'
                                        : 'Connect Slack'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </form>
                </Panel>

                <Panel className="flex flex-col">
                    <div className="mb-5 flex flex-col items-start gap-3 min-[440px]:flex-row min-[440px]:justify-between">
                        <div className="min-w-0">
                            <h3 className="font-semibold">Discord</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Deliver prompts and summaries by webhook.
                            </p>
                        </div>
                        <ConnectionStatus
                            connected={Boolean(discord?.is_connected)}
                            label="Discord"
                        />
                    </div>
                    <form
                        className="flex flex-1 flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            discordForm.post(
                                '/onboarding/integrations/discord',
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <FormField
                            required
                            label="Connection name"
                            value={discordForm.data.name}
                            onChange={(event) =>
                                discordForm.setData('name', event.target.value)
                            }
                            error={discordForm.errors.name}
                        />
                        <FormField
                            required={!discord?.is_connected}
                            type="url"
                            label="Webhook URL"
                            value={discordForm.data.webhook_url}
                            onChange={(event) =>
                                discordForm.setData(
                                    'webhook_url',
                                    event.target.value,
                                )
                            }
                            error={discordForm.errors.webhook_url}
                            placeholder={
                                discord?.is_connected
                                    ? 'Leave blank to keep current webhook'
                                    : 'https://discord.com/api/webhooks/…'
                            }
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={discordForm.processing}
                        >
                            {discordForm.processing
                                ? 'Saving…'
                                : discord?.is_connected
                                  ? 'Update Discord'
                                  : 'Connect Discord'}
                        </Button>
                    </form>
                </Panel>
            </div>
            <div className="mt-5 flex flex-wrap items-center gap-3">
                <Button
                    onClick={() =>
                        router.post('/onboarding/integrations/continue')
                    }
                >
                    Continue
                    <ArrowRight />
                </Button>
                <span className="text-xs text-muted-foreground">
                    You can change integrations later.
                </span>
            </div>
        </>
    );
}

type Invitation = {
    name: string;
    email: string;
    organization_role: string;
    workspace_role: string;
};
const emptyInvitation = (): Invitation => ({
    name: '',
    email: '',
    organization_role: 'member',
    workspace_role: 'member',
});

function MembersStep({ membersCount }: Pick<Props, 'membersCount'>) {
    const { data, setData, post, processing, errors } = useForm<{
        invitations: Invitation[];
    }>({ invitations: [emptyInvitation()] });
    const update = (index: number, field: keyof Invitation, value: string) =>
        setData(
            'invitations',
            data.invitations.map((invitation, row) =>
                row === index ? { ...invitation, [field]: value } : invitation,
            ),
        );

    return (
        <>
            <Head title="Invite your team" />
            <StepHeader
                eyebrow="Step 4 · Members · Optional"
                title="Bring the team into rhythm."
                description="Invite the people who will answer standups. Each person gets access to the initial workspace."
            />
            <Panel>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/onboarding/members');
                    }}
                    className="space-y-5"
                >
                    <div className="flex flex-col items-stretch gap-4 border-b pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Users className="size-4" />
                            </span>
                            <div className="min-w-0">
                                <div className="font-medium">
                                    Invitation list
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {membersCount} member already in the
                                    organization
                                </div>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-full sm:w-auto"
                            onClick={() =>
                                setData('invitations', [
                                    ...data.invitations,
                                    emptyInvitation(),
                                ])
                            }
                        >
                            <CirclePlus />
                            Add person
                        </Button>
                    </div>
                    <div className="space-y-4">
                        {data.invitations.map((invitation, index) => (
                            <div
                                key={index}
                                className="rounded-lg border bg-background/60 p-4"
                            >
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <span className="font-mono text-xs text-muted-foreground">
                                        INVITE{' '}
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    {data.invitations.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                setData(
                                                    'invitations',
                                                    data.invitations.filter(
                                                        (_, row) =>
                                                            row !== index,
                                                    ),
                                                )
                                            }
                                            aria-label="Remove invitation"
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                                <FormGrid>
                                    <FormField
                                        required
                                        label="Name"
                                        value={invitation.name}
                                        onChange={(event) =>
                                            update(
                                                index,
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        error={
                                            errors[`invitations.${index}.name`]
                                        }
                                    />
                                    <FormField
                                        required
                                        type="email"
                                        label="Email"
                                        value={invitation.email}
                                        onChange={(event) =>
                                            update(
                                                index,
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        error={
                                            errors[`invitations.${index}.email`]
                                        }
                                    />
                                    <FormSelect
                                        label="Organization role"
                                        value={invitation.organization_role}
                                        onChange={(event) =>
                                            update(
                                                index,
                                                'organization_role',
                                                event.target.value,
                                            )
                                        }
                                        options={[
                                            {
                                                label: 'Member',
                                                value: 'member',
                                            },
                                            {
                                                label: 'Manager',
                                                value: 'manager',
                                            },
                                            { label: 'Admin', value: 'admin' },
                                        ]}
                                    />
                                    <FormSelect
                                        label="Workspace role"
                                        value={invitation.workspace_role}
                                        onChange={(event) =>
                                            update(
                                                index,
                                                'workspace_role',
                                                event.target.value,
                                            )
                                        }
                                        options={[
                                            {
                                                label: 'Member',
                                                value: 'member',
                                            },
                                            {
                                                label: 'Manager',
                                                value: 'manager',
                                            },
                                        ]}
                                    />
                                </FormGrid>
                            </div>
                        ))}
                    </div>
                    <FormMessage>{errors.invitations}</FormMessage>
                    <FormActions
                        submitLabel="Send invitations"
                        processing={processing}
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                                router.post('/onboarding/members/skip')
                            }
                        >
                            Skip for now
                        </Button>
                    </FormActions>
                </form>
            </Panel>
        </>
    );
}

function TemplateStep({
    template,
    availableWorkItemProviders,
}: {
    template: Template;
    availableWorkItemProviders: Array<'jira' | 'bitbucket'>;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        name: template.name,
        description: template.description ?? '',
        questions: template.questions,
    });

    return (
        <>
            <Head title="Review your standup template" />
            <StepHeader
                eyebrow="Step 5 · Template"
                title="Decide what the team answers."
                description="We created a focused daily standup. Review the wording, order, and blocker follow-up before scheduling it."
            />
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    patch('/onboarding/template');
                }}
                className="grid gap-5 xl:grid-cols-[300px_minmax(0,1fr)]"
            >
                <Panel className="h-fit space-y-4">
                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <MessageSquareText className="size-5" />
                    </div>
                    <FormField
                        required
                        label="Template name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        error={errors.name}
                    />
                    <FormField
                        label="Description"
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        error={errors.description}
                    />
                    <div className="rounded-lg bg-accent/55 p-3 text-xs leading-5 text-accent-foreground">
                        This remains the default template for the initial
                        workspace.
                    </div>
                </Panel>
                <Panel className="overflow-hidden">
                    <QuestionEditor
                        questions={data.questions}
                        availableWorkItemProviders={availableWorkItemProviders}
                        onChange={(questions) =>
                            setData('questions', questions)
                        }
                    />
                    <FormActions
                        className="mt-6 border-t pt-5"
                        submitLabel="Save template and continue"
                        processing={processing}
                    />
                </Panel>
            </form>
        </>
    );
}

const weekdays = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

function ScheduleStep({
    workspace,
    integrations,
}: Pick<Props, 'workspace' | 'integrations'>) {
    const slack = integrations.find(
        (integration) =>
            integration.provider === 'slack' && integration.is_connected,
    );
    const discord = integrations.find(
        (integration) =>
            integration.provider === 'discord' && integration.is_connected,
    );
    const { data, setData, post, processing, errors } = useForm({
        name: 'Daily Focus',
        timezone: workspace?.timezone ?? 'Asia/Nicosia',
        time_of_day: '08:30',
        end_time_of_day: '10:00',
        days_of_week: [1, 2, 3, 4, 5],
        delivery_method: 'email',
        target_integration_id: null as number | null,
        prompt_channel_id: null as number | null,
        target_channel_id: null as number | null,
        reminder_minutes_before_end: [60, 30, 15, 5] as number[],
        reminder_message_template: defaultReminderTemplate,
    });
    const selectedIntegration = integrations.find(
        (integration) => integration.id === data.target_integration_id,
    );
    const selectDelivery = (method: string) =>
        setData((current) => ({
            ...current,
            delivery_method: method,
            target_integration_id:
                method === 'email'
                    ? null
                    : method === 'discord_channel'
                      ? (discord?.id ?? null)
                      : (slack?.id ?? null),
            prompt_channel_id: null,
            target_channel_id: null,
        }));

    return (
        <>
            <Head title="Schedule your first standup" />
            <StepHeader
                eyebrow="Step 6 · Schedule"
                title="Set the first pulse in motion."
                description="Choose when the team checks in and how prompts are delivered. Saving this schedule completes setup."
            />
            <Panel>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/onboarding/schedule');
                    }}
                    className="space-y-6"
                >
                    <FormGrid>
                        <FormField
                            required
                            label="Schedule name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            error={errors.name}
                        />
                        <FormField
                            required
                            type="time"
                            label="Start time"
                            value={data.time_of_day}
                            onChange={(event) =>
                                setData('time_of_day', event.target.value)
                            }
                            error={errors.time_of_day}
                        />
                        <FormField
                            required
                            type="time"
                            label="End / summary time"
                            value={data.end_time_of_day}
                            onChange={(event) =>
                                setData('end_time_of_day', event.target.value)
                            }
                            error={errors.end_time_of_day}
                        />
                        <TimezoneSelect
                            required
                            label="Timezone"
                            value={data.timezone}
                            onChange={(event) =>
                                setData('timezone', event.target.value)
                            }
                            error={errors.timezone}
                        />
                        <FormSelect
                            label="Delivery"
                            value={data.delivery_method}
                            onChange={(event) =>
                                selectDelivery(event.target.value)
                            }
                            options={[
                                { label: 'Email', value: 'email' },
                                {
                                    label: 'Slack direct messages',
                                    value: 'slack_dm',
                                    disabled: !slack,
                                },
                                {
                                    label: 'Slack channel',
                                    value: 'slack_channel',
                                    disabled: !slack?.channels.length,
                                },
                                {
                                    label: 'Discord channel',
                                    value: 'discord_channel',
                                    disabled: !discord,
                                },
                            ]}
                            error={errors.delivery_method}
                        />
                    </FormGrid>

                    {data.delivery_method === 'slack_channel' && (
                        <FormSelect
                            label="Prompt channel"
                            value={data.prompt_channel_id ?? ''}
                            onChange={(event) =>
                                setData(
                                    'prompt_channel_id',
                                    Number(event.target.value),
                                )
                            }
                            options={(selectedIntegration?.channels ?? []).map(
                                (channel) => ({
                                    label: `# ${channel.name}`,
                                    value: channel.id,
                                }),
                            )}
                            error={errors.prompt_channel_id}
                        />
                    )}

                    <div>
                        <div className="mb-2 text-sm font-medium">Run on</div>
                        <div className="flex flex-wrap gap-2">
                            {weekdays.map((day) => {
                                const active = data.days_of_week.includes(
                                    day.value,
                                );

                                return (
                                    <button
                                        key={day.value}
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'days_of_week',
                                                active
                                                    ? data.days_of_week.filter(
                                                          (value) =>
                                                              value !==
                                                              day.value,
                                                      )
                                                    : [
                                                          ...data.days_of_week,
                                                          day.value,
                                                      ].sort(),
                                            )
                                        }
                                        className={cn(
                                            'h-9 min-w-12 rounded-md border px-3 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'bg-background hover:bg-accent',
                                        )}
                                    >
                                        {day.label}
                                    </button>
                                );
                            })}
                        </div>
                        <FormMessage className="mt-2">
                            {errors.days_of_week}
                        </FormMessage>
                    </div>

                    <ReminderSettings
                        offsets={data.reminder_minutes_before_end}
                        template={data.reminder_message_template}
                        endTime={data.end_time_of_day}
                        offsetsError={errors.reminder_minutes_before_end}
                        templateError={errors.reminder_message_template}
                        onOffsetsChange={(offsets) =>
                            setData('reminder_minutes_before_end', offsets)
                        }
                        onTemplateChange={(template) =>
                            setData('reminder_message_template', template)
                        }
                    />
                    <div className="flex flex-col gap-4 rounded-lg border-l-4 border-l-primary bg-primary/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="font-medium">
                                Ready for the first check-in
                            </div>
                            <div className="mt-1 text-xs text-muted-foreground">
                                All workspace members will receive this standup.
                            </div>
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            {processing
                                ? 'Creating schedule…'
                                : 'Complete setup'}
                            <ArrowRight />
                        </Button>
                    </div>
                </form>
            </Panel>
        </>
    );
}

export default function OnboardingIndex(props: Props) {
    switch (props.onboarding.currentStep) {
        case 'integrations':
            return (
                <IntegrationsStep
                    integrations={props.integrations}
                    slackConfiguredGlobally={props.slackConfiguredGlobally}
                />
            );
        case 'members':
            return <MembersStep membersCount={props.membersCount} />;
        case 'template':
            return props.template ? (
                <TemplateStep
                    template={props.template}
                    availableWorkItemProviders={
                        props.availableWorkItemProviders
                    }
                />
            ) : (
                <OrganizationStep
                    organization={props.organization}
                    workspace={props.workspace}
                />
            );
        case 'schedule':
            return (
                <ScheduleStep
                    workspace={props.workspace}
                    integrations={props.integrations}
                />
            );
        default:
            return (
                <OrganizationStep
                    organization={props.organization}
                    workspace={props.workspace}
                />
            );
    }
}
