<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

class ActivityEventService
{
    public const STANDUP_RUN_CREATED = 'standup.run.created';

    public const STANDUP_RESPONSE_SUBMITTED = 'standup.response.submitted';

    public const STANDUP_SUMMARY_POSTED = 'standup.summary.posted';

    public const SLACK_USER_SYNCED = 'slack.user.synced';

    public const SLACK_CHANNEL_SYNCED = 'slack.channel.synced';

    public function record(
        Organization $organization,
        string $eventType,
        string $source = 'app',
        ?Workspace $workspace = null,
        ?User $user = null,
        ?Model $subject = null,
        ?string $externalId = null,
        array $metadata = [],
    ): ActivityEvent {
        return ActivityEvent::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace?->id,
            'user_id' => $user?->id,
            'source' => $source,
            'event_type' => $eventType,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'external_id' => $externalId,
            'occurred_at' => now(),
            'metadata_json' => $metadata === [] ? null : $metadata,
        ]);
    }
}
