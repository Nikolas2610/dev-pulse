<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'workspace_id',
    'standup_run_id',
    'summary_text',
    'posted_to_integration_id',
    'posted_to_channel_id',
    'external_message_id',
])]
class StandupSummary extends Model
{
    public function run(): BelongsTo
    {
        return $this->belongsTo(StandupRun::class, 'standup_run_id');
    }
}
