<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'integration_id',
    'user_id',
    'provider',
    'external_user_id',
    'external_username',
    'external_email',
    'display_name',
    'avatar_url',
    'raw_json',
])]
class IntegrationUser extends Model
{
    protected function casts(): array
    {
        return ['raw_json' => 'array'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
