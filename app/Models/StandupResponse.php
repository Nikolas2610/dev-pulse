<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'organization_id',
    'workspace_id',
    'standup_run_id',
    'user_id',
    'status',
    'submitted_at',
    'late_summary_posted_at',
    'source',
    'external_message_id',
    'raw_json',
])]
/**
 * @property int $id
 * @property int $organization_id
 * @property int $workspace_id
 * @property int $standup_run_id
 * @property int $user_id
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $late_summary_posted_at
 * @property string|null $source
 * @property string|null $external_message_id
 * @property array<string, mixed>|null $raw_json
 */
class StandupResponse extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'late_summary_posted_at' => 'datetime',
            'raw_json' => 'array',
        ];
    }

    /** @return BelongsTo<StandupRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(StandupRun::class, 'standup_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StandupAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(StandupAnswer::class);
    }

    /** @return HasMany<StandupResponseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StandupResponseItem::class);
    }
}
