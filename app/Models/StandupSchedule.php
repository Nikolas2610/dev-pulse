<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $workspace_id
 * @property int $standup_template_id
 * @property string $name
 * @property string $timezone
 * @property string $time_of_day
 * @property string|null $end_time_of_day
 * @property array<int, int> $days_of_week
 * @property string $recipient_mode
 * @property string $delivery_method
 * @property int|null $reminder_minutes_after
 * @property int|null $summary_minutes_after
 * @property array<int, int>|null $reminder_minutes_before_end
 * @property string|null $reminder_message_template
 * @property int|null $target_integration_id
 * @property int|null $prompt_channel_id
 * @property int|null $target_channel_id
 * @property bool $is_active
 */
#[Fillable([
    'organization_id',
    'workspace_id',
    'standup_template_id',
    'name',
    'timezone',
    'time_of_day',
    'end_time_of_day',
    'days_of_week',
    'recipient_mode',
    'delivery_method',
    'reminder_minutes_after',
    'summary_minutes_after',
    'reminder_minutes_before_end',
    'reminder_message_template',
    'target_integration_id',
    'prompt_channel_id',
    'target_channel_id',
    'is_active',
    'created_by_user_id',
])]
class StandupSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'reminder_minutes_before_end' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<StandupTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(StandupTemplate::class, 'standup_template_id');
    }

    /** @return BelongsTo<Integration, $this> */
    public function targetIntegration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'target_integration_id');
    }

    /** @return BelongsTo<IntegrationChannel, $this> */
    public function targetChannel(): BelongsTo
    {
        return $this->belongsTo(IntegrationChannel::class, 'target_channel_id');
    }

    /** @return BelongsTo<IntegrationChannel, $this> */
    public function promptChannel(): BelongsTo
    {
        return $this->belongsTo(IntegrationChannel::class, 'prompt_channel_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'standup_schedule_user')->withTimestamps();
    }
}
