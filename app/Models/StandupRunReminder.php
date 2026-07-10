<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function run(): BelongsTo
    {
        return $this->belongsTo(StandupRun::class, 'standup_run_id');
    }
}
