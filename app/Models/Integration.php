<?php

namespace App\Models;

use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'workspace_id',
    'provider',
    'name',
    'external_id',
    'client_id',
    'client_secret',
    'signing_secret',
    'access_token',
    'refresh_token',
    'bot_token',
    'webhook_url',
    'settings_json',
    'scopes_json',
    'connected_by_user_id',
    'is_active',
    'connected_at',
])]
#[Hidden(['access_token', 'refresh_token', 'bot_token', 'webhook_url', 'client_secret', 'signing_secret'])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'signing_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'bot_token' => 'encrypted',
            'webhook_url' => 'encrypted',
            'settings_json' => 'array',
            'scopes_json' => 'array',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
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

    public function users(): HasMany
    {
        return $this->hasMany(IntegrationUser::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(IntegrationChannel::class);
    }
}
