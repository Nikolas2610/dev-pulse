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
/**
 * @property int $id
 * @property int $organization_id
 * @property int $workspace_id
 * @property string $provider
 * @property string $name
 * @property string|null $external_id
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $signing_secret
 * @property string|null $access_token
 * @property string|null $bot_token
 * @property string|null $webhook_url
 * @property array<int, string>|null $scopes_json
 * @property array<string, mixed>|null $settings_json
 */
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

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $settings = $this->getAttribute('settings_json');

        return is_array($settings) ? $settings : [];
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

    /** @return HasMany<IntegrationUser, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(IntegrationUser::class);
    }

    /** @return HasMany<IntegrationChannel, $this> */
    public function channels(): HasMany
    {
        return $this->hasMany(IntegrationChannel::class);
    }

    /** @return HasMany<JiraProject, $this> */
    public function jiraProjects(): HasMany
    {
        return $this->hasMany(JiraProject::class);
    }

    /** @return HasMany<BitbucketRepository, $this> */
    public function bitbucketRepositories(): HasMany
    {
        return $this->hasMany(BitbucketRepository::class);
    }

    /** @return HasMany<ExternalWorkItem, $this> */
    public function workItems(): HasMany
    {
        return $this->hasMany(ExternalWorkItem::class);
    }
}
