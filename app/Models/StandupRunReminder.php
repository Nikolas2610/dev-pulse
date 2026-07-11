<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'standup_run_id',
    'minutes_before_end',
    'scheduled_for',
    'status',
    'attempts',
    'claimed_at',
    'sent_at',
    'last_error',
])]
/**
 * @property int $id
 * @property int $standup_run_id
 * @property int $minutes_before_end
 * @property Carbon $scheduled_for
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $claimed_at
 * @property Carbon|null $sent_at
 */
class StandupRunReminder extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StandupRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(StandupRun::class, 'standup_run_id');
    }
}
