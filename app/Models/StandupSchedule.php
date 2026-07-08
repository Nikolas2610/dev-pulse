<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'organization_id',
    'workspace_id',
    'standup_template_id',
    'name',
    'timezone',
    'time_of_day',
    'days_of_week',
    'recipient_mode',
    'delivery_method',
    'reminder_minutes_after',
    'summary_minutes_after',
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
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StandupTemplate::class, 'standup_template_id');
    }

    public function targetIntegration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'target_integration_id');
    }

    public function targetChannel(): BelongsTo
    {
        return $this->belongsTo(IntegrationChannel::class, 'target_channel_id');
    }

    public function promptChannel(): BelongsTo
    {
        return $this->belongsTo(IntegrationChannel::class, 'prompt_channel_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'standup_schedule_user')->withTimestamps();
    }
}
