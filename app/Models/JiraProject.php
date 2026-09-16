<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['integration_id', 'external_id', 'project_key', 'name', 'avatar_url', 'statuses_json', 'is_active', 'last_synced_at'])]
/**
 * @property array<int, array{id: string, name: string, category_key: string}>|null $statuses_json
 */
class JiraProject extends Model
{
    protected function casts(): array
    {
        return ['statuses_json' => 'array', 'is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    }

    /** @return array<int, string> */
    public function statusIds(): array
    {
        $statuses = $this->getAttribute('statuses_json');

        if (! is_array($statuses)) {
            return [];
        }

        $ids = [];
        foreach ($statuses as $status) {
            if (is_array($status) && filled($status['id'] ?? null)) {
                $ids[] = (string) $status['id'];
            }
        }

        return $ids;
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
