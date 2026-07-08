import { Users } from 'lucide-react';
import { FormSelect } from '@/components/forms/form-components';

type Workspace = {
    id: number;
    name: string;
};

type Member = {
    id: number;
    name: string;
    email: string;
    workspaces?: Workspace[];
    integration_users?: Array<{
        integration_id: number;
        provider: string;
    }>;
};

type Props = {
    workspaceId: string | number;
    members: Member[];
    recipientMode: string;
    participantUserIds: Array<number | string>;
    requiresSlackRecipients?: boolean;
    slackIntegrationId?: number | string | null;
    error?: string;
    onRecipientModeChange: (mode: string) => void;
    onParticipantUserIdsChange: (userIds: number[]) => void;
};

export function RecipientPicker({
    workspaceId,
    members,
    recipientMode,
    participantUserIds,
    requiresSlackRecipients = false,
    slackIntegrationId = null,
    error,
    onRecipientModeChange,
    onParticipantUserIdsChange,
}: Props) {
    const workspaceMemberIds = new Set(
        members
            .filter((member) =>
                member.workspaces?.some(
                    (workspace) => String(workspace.id) === String(workspaceId),
                ),
            )
            .map((member) => member.id),
    );
    const selectedIds = new Set(participantUserIds.map((id) => Number(id)));
    const hasSlackLink = (member: Member) =>
        !requiresSlackRecipients ||
        member.integration_users?.some(
            (integrationUser) =>
                integrationUser.provider === 'slack' &&
                String(integrationUser.integration_id) ===
                    String(slackIntegrationId),
        );
    const workspaceMembers = members.filter((member) =>
        workspaceMemberIds.has(member.id),
    );
    const availableMembers = workspaceMembers.filter(hasSlackLink);
    const hiddenSlackMemberCount =
        requiresSlackRecipients && slackIntegrationId
            ? workspaceMembers.length - availableMembers.length
            : 0;

    const toggleMember = (userId: number, checked: boolean) => {
        const next = new Set(selectedIds);

        if (checked) {
            next.add(userId);
        } else {
            next.delete(userId);
        }

        onParticipantUserIdsChange(Array.from(next));
    };

    return (
        <div className="rounded-md border bg-card/50 p-3">
            <div className="mb-3 flex items-center gap-2">
                <div className="dp-icon-tile">
                    <Users className="size-5" />
                </div>
                <div>
                    <div className="font-medium">Recipients</div>
                    <div className="text-sm text-muted-foreground">
                        {requiresSlackRecipients
                            ? 'Choose Slack-linked users for this standup. Import Slack users before selecting them here.'
                            : 'Choose who receives this standup for the selected workspace.'}
                    </div>
                </div>
            </div>

            <FormSelect
                label="Send to"
                value={recipientMode}
                onChange={(event) => onRecipientModeChange(event.target.value)}
                options={[
                    {
                        label: requiresSlackRecipients
                            ? 'All active Slack-linked members'
                            : 'All active workspace members',
                        value: 'all_workspace_members',
                    },
                    {
                        label: 'Selected members only',
                        value: 'selected_members',
                    },
                ]}
            />

            {recipientMode === 'selected_members' && (
                <div className="mt-3 space-y-2">
                    {availableMembers.length === 0 ? (
                        <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                            {requiresSlackRecipients
                                ? 'No active Slack-linked members are assigned to this workspace for the selected Slack integration.'
                                : 'No active members are assigned to this workspace.'}
                        </div>
                    ) : (
                        availableMembers.map((member) => (
                            <label
                                key={member.id}
                                className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
                            >
                                <span>
                                    <span className="font-medium">
                                        {member.name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {member.email}
                                    </span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={selectedIds.has(member.id)}
                                    onChange={(event) =>
                                        toggleMember(
                                            member.id,
                                            event.target.checked,
                                        )
                                    }
                                />
                            </label>
                        ))
                    )}
                    {hiddenSlackMemberCount > 0 && (
                        <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                            {hiddenSlackMemberCount} workspace member
                            {hiddenSlackMemberCount === 1 ? '' : 's'} hidden
                            because they are not linked to this Slack
                            integration.
                        </div>
                    )}
                </div>
            )}
            {error && <p className="mt-3 text-sm text-destructive">{error}</p>}
        </div>
    );
}
