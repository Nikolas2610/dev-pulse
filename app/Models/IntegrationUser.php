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
/**
 * @property int $id
 * @property int $integration_id
 * @property int|null $user_id
 * @property string $provider
 * @property string $external_user_id
 * @property string|null $external_username
 * @property string|null $external_email
 * @property string|null $display_name
 * @property string|null $avatar_url
 */
class IntegrationUser extends Model
{
    protected function casts(): array
    {
        return ['raw_json' => 'array'];
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
