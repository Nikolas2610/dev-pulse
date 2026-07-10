import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    FormCheckbox,
    FormField,
    FormGrid,
    FormSelect,
    TimezoneSelect,
} from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import { RecipientPicker } from '@/pages/schedules/recipient-picker';
import {
    defaultReminderTemplate,
    ReminderSettings,
} from '@/pages/schedules/reminder-settings';

export default function SchedulesEdit({
    schedule,
    integrations,
    members,
}: {
    schedule: any;
    integrations: any[];
    members: any[];
}) {
    const { data, setData, patch, processing, errors } = useForm({
        name: schedule.name,
        timezone: schedule.timezone,
        time_of_day: String(schedule.time_of_day).slice(0, 5),
        end_time_of_day: String(schedule.end_time_of_day).slice(0, 5),
        reminder_minutes_before_end: schedule.reminder_minutes_before_end ?? [
            60, 30, 15, 5,
        ],
        reminder_message_template:
            schedule.reminder_message_template ?? defaultReminderTemplate,
        recipient_mode: schedule.recipient_mode ?? 'all_workspace_members',
        delivery_method: schedule.delivery_method ?? 'email',
        participant_user_ids: (schedule.participants ?? []).map(
            (participant: any) => participant.id,
        ) as number[],
        target_integration_id: schedule.target_integration_id,
        prompt_channel_id: schedule.prompt_channel_id,
        target_channel_id: schedule.target_channel_id,
        is_active: schedule.is_active,
    });
    const selectedIntegration = integrations.find(
        (integration) =>
            String(integration.id) === String(data.target_integration_id),
    );
    const summaryIntegrations = integrations.filter((integration) =>
        ['slack', 'discord'].includes(integration.provider),
    );
    const isSummaryIntegrationId = (integrationId: unknown) =>
        summaryIntegrations.some(
            (integration) => String(integration.id) === String(integrationId),
        );
    const selectedSummaryIntegration = ['slack', 'discord'].includes(
        selectedIntegration?.provider,
    )
        ? selectedIntegration
        : null;
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
    const promptChannelDisabledReason =
        data.delivery_method !== 'slack_channel'
            ? 'Choose "Slack channel" under "Notify devs by" first.'
            : selectedIntegration?.provider !== 'slack'
              ? 'Select a Slack delivery integration first.'
              : '';
    const summaryChannelDisabledReason = !selectedSummaryIntegration
        ? 'Select a summary integration first.'
        : selectedSummaryIntegration.provider === 'discord'
          ? 'Discord summaries use the integration webhook, so no channel is needed here.'
          : selectedSummaryIntegration.provider !== 'slack'
            ? 'Select a Slack summary integration to choose a channel.'
            : '';

    return (
        <>
            <Head title={`Edit ${schedule.name}`} />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                        >
                            <Link href="/schedules">
                                <ArrowLeft />
                                Schedules
                            </Link>
                        </Button>
                        <div className="dp-kicker mt-3">Schedule editor</div>
                        <h1 className="dp-title">{schedule.name}</h1>
                        <p className="dp-subtitle">
                            Tune when this standup opens, when reminders are
                            sent, and when the summary is posted.
                        </p>
                    </div>
                </div>

                <form
                    className="dp-panel max-w-3xl"
                    onSubmit={(event) => {
                        event.preventDefault();
                        patch(`/schedules/${schedule.id}`);
                    }}
                >
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">Timing</h2>
                            <p className="text-sm text-muted-foreground">
                                {schedule.workspace?.name} ·{' '}
                                {schedule.template?.name}
                            </p>
                        </div>
                        <Button disabled={processing}>Save changes</Button>
                    </div>
                    <div className="dp-panel-body space-y-4">
                        <FormField
                            label="Name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                        <FormGrid>
                            <FormField
                                label="Start time"
                                type="time"
                                value={data.time_of_day}
                                onChange={(event) =>
                                    setData('time_of_day', event.target.value)
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
                                    setData('timezone', event.target.value)
                                }
                            />
                        </FormGrid>
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
                        <FormSelect
                            label="Notify devs by"
                            value={data.delivery_method}
                            onChange={(event) => {
                                const deliveryMethod = event.target.value;

                                setData((current) => ({
                                    ...current,
                                    delivery_method: deliveryMethod,
                                    target_integration_id:
                                        deliveryMethod === 'email'
                                            ? isSummaryIntegrationId(
                                                  current.target_integration_id,
                                              )
                                                ? current.target_integration_id
                                                : null
                                            : current.target_integration_id,
                                    prompt_channel_id: null,
                                }));
                            }}
                        >
                            <option value="email">Email</option>
                            <option value="slack_dm">Slack DM</option>
                            <option value="slack_channel">Slack channel</option>
                            <option value="discord_channel">
                                Discord channel
                            </option>
                        </FormSelect>
                        {deliveryRequiresIntegration && (
                            <FormGrid>
                                <FormSelect
                                    label="Delivery integration"
                                    value={data.target_integration_id ?? ''}
                                    onChange={(event) =>
                                        setData((current) => ({
                                            ...current,
                                            target_integration_id:
                                                event.target.value === ''
                                                    ? null
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                            prompt_channel_id: null,
                                            target_channel_id: null,
                                        }))
                                    }
                                >
                                    <option value="">Select integration</option>
                                    {deliveryIntegrations.map((integration) => (
                                        <option
                                            key={integration.id}
                                            value={integration.id}
                                        >
                                            {integration.name} (
                                            {integration.provider})
                                        </option>
                                    ))}
                                </FormSelect>
                                {data.delivery_method === 'slack_channel' && (
                                    <div className="space-y-1">
                                        <FormSelect
                                            label="Prompt channel"
                                            value={data.prompt_channel_id ?? ''}
                                            disabled={Boolean(
                                                promptChannelDisabledReason,
                                            )}
                                            title={
                                                promptChannelDisabledReason ||
                                                'Select where the DevPulse bot posts the standup prompt.'
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'prompt_channel_id',
                                                    event.target.value === ''
                                                        ? null
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                )
                                            }
                                        >
                                            <option value="">
                                                Select prompt channel
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
                                        <p className="text-xs text-muted-foreground">
                                            {promptChannelDisabledReason ||
                                                'The DevPulse bot posts the prompt in this Slack channel. For private bot messages, use Slack DM instead.'}
                                        </p>
                                    </div>
                                )}
                            </FormGrid>
                        )}
                        {data.delivery_method === 'email' &&
                            summaryIntegrations.length > 0 && (
                                <FormGrid>
                                    <FormSelect
                                        label="Summary integration"
                                        value={data.target_integration_id ?? ''}
                                        onChange={(event) =>
                                            setData((current) => ({
                                                ...current,
                                                target_integration_id:
                                                    event.target.value === ''
                                                        ? null
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                target_channel_id: null,
                                            }))
                                        }
                                    >
                                        <option value="">
                                            No summary posting
                                        </option>
                                        {summaryIntegrations.map(
                                            (integration) => (
                                                <option
                                                    key={integration.id}
                                                    value={integration.id}
                                                >
                                                    {integration.name} (
                                                    {integration.provider})
                                                </option>
                                            ),
                                        )}
                                    </FormSelect>
                                </FormGrid>
                            )}
                        <FormGrid>
                            <div className="space-y-1">
                                <FormSelect
                                    label="Summary channel"
                                    value={data.target_channel_id ?? ''}
                                    disabled={Boolean(
                                        summaryChannelDisabledReason,
                                    )}
                                    title={
                                        summaryChannelDisabledReason ||
                                        'Select where Slack summaries are posted.'
                                    }
                                    onChange={(event) =>
                                        setData(
                                            'target_channel_id',
                                            event.target.value === ''
                                                ? null
                                                : Number(event.target.value),
                                        )
                                    }
                                >
                                    <option value="">
                                        {selectedSummaryIntegration?.provider ===
                                        'slack'
                                            ? 'Select summary channel'
                                            : 'No channel required'}
                                    </option>
                                    {(
                                        selectedSummaryIntegration?.channels ??
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
                                <p className="text-xs text-muted-foreground">
                                    {summaryChannelDisabledReason ||
                                        'Slack summaries are posted by the DevPulse bot to this channel.'}
                                </p>
                            </div>
                        </FormGrid>
                        <RecipientPicker
                            workspaceId={schedule.workspace_id}
                            members={members}
                            recipientMode={data.recipient_mode}
                            participantUserIds={data.participant_user_ids}
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
                                setData('participant_user_ids', userIds)
                            }
                        />
                        <FormCheckbox
                            label="Active schedule"
                            checked={Boolean(data.is_active)}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked)
                            }
                            className="w-fit"
                        />
                    </div>
                </form>
            </div>
        </>
    );
}
