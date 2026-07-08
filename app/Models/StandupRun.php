<?php

namespace App\Models;

use Database\Factories\StandupRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'workspace_id',
    'standup_schedule_id',
    'standup_template_id',
    'run_date',
    'status',
    'opened_at',
    'summary_posted_at',
])]
class StandupRun extends Model
{
    /** @use HasFactory<StandupRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'opened_at' => 'datetime',
            'summary_posted_at' => 'datetime',
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

    public function standupTemplate(): BelongsTo
    {
        return $this->template();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(StandupSchedule::class, 'standup_schedule_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(StandupResponse::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(StandupSummary::class);
    }
}
