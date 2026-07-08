<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'workspace_id',
    'user_id',
    'source',
    'event_type',
    'subject_type',
    'subject_id',
    'external_id',
    'occurred_at',
    'metadata_json',
])]
class ActivityEvent extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
