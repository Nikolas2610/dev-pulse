import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    CalendarClock,
    FileText,
    MoreHorizontal,
    Pencil,
    Plus,
    Power,
    Send,
    Trash2,
} from 'lucide-react';
import {
    FormActions,
    FormField,
    FormGrid,
    FormSelect,
    TimezoneSelect,
} from '@/components/forms/form-components';
import {
    IndexDataControls,
    IndexDataState,
    IndexFilterSelect,
    IndexPagination,
} from '@/components/index-data-controls';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIndexData } from '@/hooks/use-index-data';
import { RecipientPicker } from '@/pages/schedules/recipient-picker';
import {
    defaultReminderTemplate,
    ReminderSettings,
} from '@/pages/schedules/reminder-settings';
import {
    formatWeekdays,
    WeekdayPicker,
} from '@/pages/schedules/weekday-picker';

export default function SchedulesIndex({
    indexEndpoint,
    workspaces,
    templates,
    integrations,
    members,
    canManageSchedules,
}: {
    indexEndpoint: string;
    workspaces: any[];
    templates: any[];
    integrations: any[];
    members: any[];
    canManageSchedules: boolean;
}) {
    const { page, query, loading, error, updateQuery, setFilter } =
        useIndexData<any>(indexEndpoint, {
            sort: 'created_at',
            direction: 'desc',
        });
    const schedules = page.data;
    const { data, setData, post, processing, errors } = useForm({
        name: 'Daily Focus',
        workspace_id: workspaces[0]?.id ?? '',
        standup_template_id: templates[0]?.id ?? '',
        timezone: 'Asia/Nicosia',
        time_of_day: '08:30',
        end_time_of_day: '10:00',
        days_of_week: [1, 2, 3, 4, 5],
        recipient_mode: 'all_workspace_members',
        delivery_method: 'email',
        participant_user_ids: [] as number[],
        reminder_minutes_before_end: [60, 30, 15, 5] as number[],
        reminder_message_template: defaultReminderTemplate,
        target_integration_id: null as number | null,
        prompt_channel_id: null as number | null,
        target_channel_id: null as number | null,
        is_active: true,
    });
    const selectedIntegration = integrations.find(
        (integration) =>
            String(integration.id) === String(data.target_integration_id),
    );
    const deliveryRequiresIntegration = [
        'slack_dm',
        'slack_channel',
        'discord_channel',
    ].includes(data.delivery_method);
    const deliveryIntegrationProvider =
        data.delivery_method === 'discord_channel' ? 'discord' : 'slack';
    const deliveryIntegrations = integrations.filter(
        (integration) => integration.provider === deliveryIntegrationProvider,
    );
    const deliveryMethodLabels: Record<string, string> = {
        email: 'Email',
        slack_dm: 'Slack DM',
        slack_channel: 'Slack channel',
        discord_channel: 'Discord channel',
    };
    const summarySendDisabledReason = (schedule: any) => {
        const integration = integrations.find(
            (candidate) =>
                String(candidate.id) === String(schedule.target_integration_id),
        );

        if (!integration) {
            return 'Select a summary integration before sending a summary.';
        }

        if (integration.provider === 'slack' && !schedule.target_channel_id) {
            return 'Select a Slack summary channel before sending a summary.';
        }

        return '';
    };

    return (
        <>
            <Head title="Schedules" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Automation</div>
                        <h1 className="dp-title">Standup schedules</h1>
                        <p className="dp-subtitle">
                            Decide when each workspace receives questions,
                            reminders, and Slack summaries.
                        </p>
                    </div>

                    {canManageSchedules && (
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button>
                                    <Plus />
                                    New schedule
                                </Button>
                            </SheetTrigger>
                            <SheetContent className="w-full sm:max-w-xl">
                                <SheetHeader>
                                    <SheetTitle>Create schedule</SheetTitle>
                                    <SheetDescription>
                                        Choose the workspace, template, and
                                        daily timing.
                                    </SheetDescription>
                                </SheetHeader>
                                <form
                                    className="space-y-4 px-4 pb-6"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        post('/schedules');
                                    }}
                                >
                                    <FormField
                                        label="Name"
                                        value={data.name}
                                        onChange={(event) =>
                                            setData('name', event.target.value)
                                        }
                                    />
                                    <FormGrid>
                                        <FormSelect
                                            label="Workspace"
                                            value={data.workspace_id}
                                            onChange={(event) =>
                                                setData((current) => ({
                                                    ...current,
                                                    workspace_id:
                                                        event.target.value,
                                                    participant_user_ids: [],
                                                }))
                                            }
                                            options={workspaces.map(
                                                (workspace) => ({
                                                    label: workspace.name,
                                                    value: workspace.id,
                                                }),
                                            )}
                                        />
                                        <FormSelect
                                            label="Template"
                                            value={data.standup_template_id}
                                            onChange={(event) =>
                                                setData(
                                                    'standup_template_id',
                                                    event.target.value,
                                                )
                                            }
                                            options={templates.map(
                                                (template) => ({
                                                    label: template.name,
                                                    value: template.id,
                                                }),
                                            )}
                                        />
                                    </FormGrid>
                                    <RecipientPicker
                                        workspaceId={data.workspace_id}
                                        members={members}
                                        recipientMode={data.recipient_mode}
                                        participantUserIds={
                                            data.participant_user_ids
                                        }
                                        requiresSlackRecipients={false}
                                        error={errors.participant_user_ids}
                                        onRecipientModeChange={(mode) =>
                                            setData((current) => ({
                                                ...current,
                                                recipient_mode: mode,
                                                participant_user_ids:
                                                    mode === 'selected_members'
                                                        ? current.participant_user_ids
                                                        : [],
                                            }))
                                        }
                                        onParticipantUserIdsChange={(userIds) =>
                                            setData(
                                                'participant_user_ids',
                                                userIds,
                                            )
                                        }
                                    />
                                    <FormSelect
                                        label="Notify devs by"
                                        value={data.delivery_method}
                                        onChange={(event) => {
                                            const deliveryMethod =
                                                event.target.value;

                                            setData((current) => ({
                                                ...current,
                                                delivery_method: deliveryMethod,
                                                target_integration_id:
                                                    deliveryMethod === 'email'
                                                        ? null
                                                        : current.target_integration_id,
                                                prompt_channel_id: null,
                                            }));
                                        }}
                                    >
                                        <option value="email">Email</option>
                                        <option value="slack_dm">
                                            Slack DM
                                        </option>
                                        <option value="slack_channel">
                                            Slack channel
                                        </option>
                                        <option value="discord_channel">
                                            Discord channel
                                        </option>
                                    </FormSelect>
                                    <FormGrid>
                                        <FormField
                                            label="Start time"
                                            type="time"
                                            value={data.time_of_day}
                                            onChange={(event) =>
                                                setData(
                                                    'time_of_day',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <FormField
                                            label="End / summary time"
                                            type="time"
                                            value={data.end_time_of_day}
                                            onChange={(event) =>
                                                setData(
                                                    'end_time_of_day',
                                                    event.target.value,
                                                )
                                            }
                                            error={errors.end_time_of_day}
                                        />
                                    </FormGrid>
                                    <FormGrid>
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
                                    </FormGrid>
                                    <WeekdayPicker
                                        value={data.days_of_week}
                                        error={errors.days_of_week}
                                        onChange={(days) =>
                                            setData('days_of_week', days)
                                        }
                                    />
                                    <ReminderSettings
                                        offsets={
                                            data.reminder_minutes_before_end
                                        }
                                        template={
                                            data.reminder_message_template
                                        }
                                        endTime={data.end_time_of_day}
                                        offsetsError={
                                            errors.reminder_minutes_before_end
                                        }
                                        templateError={
                                            errors.reminder_message_template
                                        }
                                        onOffsetsChange={(offsets) =>
                                            setData(
                                                'reminder_minutes_before_end',
                                                offsets,
                                            )
                                        }
                                        onTemplateChange={(template) =>
                                            setData(
                                                'reminder_message_template',
                                                template,
                                            )
                                        }
                                    />
                                    {deliveryRequiresIntegration && (
                                        <FormGrid>
                                            <FormSelect
                                                label="Delivery integration"
                                                value={
                                                    data.target_integration_id ??
                                                    ''
                                                }
                                                onChange={(event) =>
                                                    setData((current) => ({
                                                        ...current,
                                                        target_integration_id:
                                                            event.target
                                                                .value === ''
                                                                ? null
                                                                : Number(
                                                                      event
                                                                          .target
                                                                          .value,
                                                                  ),
                                                        prompt_channel_id: null,
                                                        target_channel_id: null,
                                                    }))
                                                }
                                            >
                                                <option value="">
                                                    Select integration
                                                </option>
                                                {deliveryIntegrations.map(
                                                    (integration) => (
                                                        <option
                                                            key={integration.id}
                                                            value={
                                                                integration.id
                                                            }
                                                        >
                                                            {integration.name} (
                                                            {
                                                                integration.provider
                                                            }
                                                            )
                                                        </option>
                                                    ),
                                                )}
                                            </FormSelect>
                                            {data.delivery_method ===
                                                'slack_channel' && (
                                                <FormSelect
                                                    label="Prompt channel"
                                                    value={
                                                        data.prompt_channel_id ??
                                                        ''
                                                    }
                                                    disabled={
                                                        selectedIntegration?.provider !==
                                                        'slack'
                                                    }
                                                    onChange={(event) =>
                                                        setData(
                                                            'prompt_channel_id',
                                                            event.target
                                                                .value === ''
                                                                ? null
                                                                : Number(
                                                                      event
                                                                          .target
                                                                          .value,
                                                                  ),
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        No channel
                                                    </option>
                                                    {(
                                                        selectedIntegration?.channels ??
                                                        []
                                                    ).map((channel: any) => (
                                                        <option
                                                            key={channel.id}
                                                            value={channel.id}
                                                        >
                                                            #{channel.name}
                                                        </option>
                                                    ))}
                                                </FormSelect>
                                            )}
                                        </FormGrid>
                                    )}
                                    <FormGrid>
                                        <FormSelect
                                            label="Summary channel"
                                            value={data.target_channel_id ?? ''}
                                            disabled={
                                                selectedIntegration?.provider !==
                                                'slack'
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'target_channel_id',
                                                    event.target.value === ''
                                                        ? null
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                )
                                            }
                                        >
                                            <option value="">No channel</option>
                                            {(
                                                selectedIntegration?.channels ??
                                                []
                                            ).map((channel: any) => (
                                                <option
                                                    key={channel.id}
                                                    value={channel.id}
                                                >
                                                    #{channel.name}
                                                </option>
                                            ))}
                                        </FormSelect>
                                    </FormGrid>
                                    <FormActions
                                        submitLabel="Create schedule"
                                        processing={processing}
                                    />
                                </form>
                            </SheetContent>
                        </Sheet>
                    )}
                </div>

                <div className="dp-panel">
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">
                                Active schedule map
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {canManageSchedules
                                    ? 'Use the row menu to edit, send, remind, summarize, or deactivate a schedule.'
                                    : 'Schedules are read-only for your role.'}
                            </p>
                        </div>
                        <Badge variant="secondary">{page.total} total</Badge>
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
                            { label: 'Earliest time', value: 'time_of_day' },
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
                            label="All delivery methods"
                            value={query.filters.delivery_method ?? ''}
                            onChange={(value) =>
                                setFilter('delivery_method', value)
                            }
                            options={Object.entries(deliveryMethodLabels).map(
                                ([value, label]) => ({ label, value }),
                            )}
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
                    {schedules.map((schedule) => {
                        const summaryDisabledReason =
                            summarySendDisabledReason(schedule);
                        const row = (
                            <>
                                <div className="dp-icon-tile mt-1">
                                    <CalendarClock className="size-5" />
                                </div>
                                <div>
                                    <div className="font-medium">
                                        {schedule.name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {schedule.workspace?.name} -{' '}
                                        {schedule.template?.name}
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="font-medium">
                                        {String(schedule.time_of_day).slice(
                                            0,
                                            5,
                                        )}{' '}
                                        –{' '}
                                        {String(schedule.end_time_of_day).slice(
                                            0,
                                            5,
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {schedule.recipient_mode ===
                                        'selected_members'
                                            ? `${schedule.participants_count} selected`
                                            : 'All active members'}{' '}
                                        - {schedule.timezone}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatWeekdays(
                                            schedule.days_of_week,
                                        )}
                                        {' · '}
                                        {deliveryMethodLabels[
                                            schedule.delivery_method ?? 'email'
                                        ] ?? 'Email'}
                                        {' · '}
                                        {
                                            (
                                                schedule.reminder_minutes_before_end ??
                                                []
                                            ).length
                                        }{' '}
                                        reminders
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                schedule.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {schedule.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>
                                {canManageSchedules && (
                                    <div className="flex justify-end">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={`Actions for ${schedule.name}`}
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent
                                                align="end"
                                                className="w-48"
                                            >
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={`/schedules/${schedule.id}/edit`}
                                                    >
                                                        <Pencil />
                                                        Edit schedule
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.post(
                                                            `/schedules/${schedule.id}/send-now`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Send />
                                                    Send schedule now
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.post(
                                                            `/schedules/${schedule.id}/send-reminder`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <BellRing />
                                                    Send reminder
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    disabled={Boolean(
                                                        summaryDisabledReason,
                                                    )}
                                                    title={
                                                        summaryDisabledReason ||
                                                        'Send the latest run summary.'
                                                    }
                                                    onSelect={() =>
                                                        router.post(
                                                            `/schedules/${schedule.id}/send-summary`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <FileText />
                                                    Send summary
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.patch(
                                                            `/schedules/${schedule.id}/active`,
                                                            {
                                                                is_active:
                                                                    !schedule.is_active,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Power />
                                                    {schedule.is_active
                                                        ? 'Deactivate'
                                                        : 'Activate'}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        router.delete(
                                                            `/schedules/${schedule.id}`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                    Delete schedule
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                )}
                            </>
                        );

                        return canManageSchedules ? (
                            <div
                                key={schedule.id}
                                className="dp-table-row grid-cols-[auto_1fr_auto_auto] items-start"
                            >
                                {row}
                            </div>
                        ) : (
                            <div
                                key={schedule.id}
                                className="dp-table-row grid-cols-[auto_1fr_auto] items-start"
                            >
                                {row}
                            </div>
                        );
                    })}
                    {!schedules.length && (
                        <IndexDataState
                            loading={loading}
                            error={error}
                            empty="No schedules match these filters."
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
