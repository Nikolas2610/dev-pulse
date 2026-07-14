<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'standup_response_id',
    'manager_user_id',
    'integration_id',
    'client_message_id',
    'status',
    'external_message_id',
    'attempted_at',
    'sent_at',
    'failure_reason',
])]
class ManagerBlockerNotification extends Model
{
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StandupResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(StandupResponse::class, 'standup_response_id');
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
