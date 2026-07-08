<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'workspace_id',
    'standup_run_id',
    'user_id',
    'status',
    'submitted_at',
    'source',
    'external_message_id',
    'raw_json',
])]
class StandupResponse extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'raw_json' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(StandupRun::class, 'standup_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StandupAnswer::class);
    }
}
