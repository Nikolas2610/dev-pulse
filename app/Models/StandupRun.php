<?php

namespace App\Models;

use Database\Factories\StandupRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable([
    'organization_id',
    'workspace_id',
    'standup_schedule_id',
    'standup_template_id',
    'run_date',
    'status',
    'scheduled_open_at',
    'opened_at',
    'summary_due_at',
    'summary_posted_at',
])]
/**
 * @property int $id
 * @property int $organization_id
 * @property int $workspace_id
 * @property int $standup_schedule_id
 * @property int $standup_template_id
 * @property Carbon $run_date
 * @property string $status
 * @property Carbon|null $scheduled_open_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $summary_due_at
 * @property Carbon|null $summary_posted_at
 */
class StandupRun extends Model
{
    /** @use HasFactory<StandupRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'scheduled_open_at' => 'datetime',
            'opened_at' => 'datetime',
            'summary_due_at' => 'datetime',
            'summary_posted_at' => 'datetime',
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

    /** @return BelongsTo<StandupTemplate, $this> */
    public function standupTemplate(): BelongsTo
    {
        return $this->template();
    }

    /** @return BelongsTo<StandupSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(StandupSchedule::class, 'standup_schedule_id');
    }

    /** @return HasMany<StandupResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(StandupResponse::class);
    }

    /** @return HasMany<StandupRunReminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(StandupRunReminder::class);
    }

    /** @return HasOne<StandupSummary, $this> */
    public function summary(): HasOne
    {
        return $this->hasOne(StandupSummary::class);
    }
}
