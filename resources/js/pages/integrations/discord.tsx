import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, Send } from 'lucide-react';
import { FormActions, FormField } from '@/components/forms/form-components';
import { Badge } from '@/components/ui/badge';

export default function DiscordIntegration({
    integration,
    isConfigured,
    canManageIntegration,
}: {
    integration: any | null;
    isConfigured: boolean;
    canManageIntegration: boolean;
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: integration?.name ?? 'Discord',
        webhook_url: '',
    });

    return (
        <>
            <Head title="Discord Integration" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Integration</div>
                        <h1 className="dp-title">Discord channel</h1>
                        <p className="dp-subtitle">
                            Post standup prompts, reminders, and summaries to a
                            Discord channel through a workspace webhook.
                        </p>
                    </div>
                    <Badge variant={isConfigured ? 'default' : 'secondary'}>
                        {isConfigured ? 'Configured' : 'Missing webhook'}
                    </Badge>
                </div>

                {!isConfigured && (
                    <div className="dp-panel border-amber-300/50 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                        <div className="flex gap-3 p-4">
                            <AlertTriangle className="mt-0.5 size-5 shrink-0" />
                            <div>
                                <h2 className="font-semibold">
                                    Discord is not configured
                                </h2>
                                <p className="mt-1 text-sm opacity-85">
                                    Create a Discord channel webhook and save it
                                    here before selecting Discord on a schedule.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <form
                    className="dp-panel max-w-3xl"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/integrations/discord', {
                            preserveScroll: true,
                            onSuccess: () => setData('webhook_url', ''),
                        });
                    }}
                >
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">Workspace webhook</h2>
                            <p className="text-sm text-muted-foreground">
                                {isConfigured
                                    ? 'A webhook is saved for the current workspace.'
                                    : 'Save the webhook for the current workspace.'}
                            </p>
                        </div>
                        {isConfigured && <Badge>Saved</Badge>}
                    </div>
                    <div className="dp-panel-body space-y-4">
                        <FormField
                            label="Name"
                            value={data.name}
                            disabled={!canManageIntegration}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            error={errors.name}
                        />
                        <FormField
                            label="Webhook URL"
                            type="password"
                            value={data.webhook_url}
                            disabled={!canManageIntegration}
                            placeholder={
                                isConfigured
                                    ? 'Enter a new webhook URL to replace it'
                                    : 'https://discord.com/api/webhooks/...'
                            }
                            onChange={(event) =>
                                setData('webhook_url', event.target.value)
                            }
                            error={errors.webhook_url}
                        />
                        {canManageIntegration && (
                            <FormActions
                                submitLabel="Save webhook"
                                processing={processing}
                            >
                                <Send className="size-4 text-muted-foreground" />
                            </FormActions>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
