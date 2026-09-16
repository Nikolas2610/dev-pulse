<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['integration_id', 'external_id', 'project_key', 'name', 'avatar_url', 'statuses_json', 'is_active', 'last_synced_at'])]
class JiraProject extends Model
{
    protected function casts(): array
    {
        return ['statuses_json' => 'array', 'is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_jira_project')
            ->withPivot('included_status_ids')
            ->withTimestamps();
    }
}
