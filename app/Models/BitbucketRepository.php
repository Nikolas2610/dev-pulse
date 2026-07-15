<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['integration_id', 'workspace_uuid', 'workspace_slug', 'repository_uuid', 'repository_slug', 'full_name', 'project_name', 'main_branch', 'is_private', 'is_active', 'last_synced_at'])]
class BitbucketRepository extends Model
{
    protected function casts(): array
    {
        return ['is_private' => 'boolean', 'is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_bitbucket_repository')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bitbucket_repository_user')->withTimestamps();
    }
}
