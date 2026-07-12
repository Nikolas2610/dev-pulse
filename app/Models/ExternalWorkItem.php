<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['integration_id', 'workspace_id', 'jira_project_id', 'bitbucket_repository_id', 'assignee_integration_user_id', 'author_integration_user_id', 'provider', 'external_id', 'external_key', 'type', 'title', 'url', 'status', 'priority', 'is_draft', 'is_blocked', 'task_count', 'reviewer_external_ids', 'raw_json', 'provider_updated_at', 'due_date', 'closed_at', 'last_synced_at'])]
class ExternalWorkItem extends Model
{
    protected function casts(): array
    {
        return ['is_draft' => 'boolean', 'is_blocked' => 'boolean', 'reviewer_external_ids' => 'array', 'raw_json' => 'array', 'provider_updated_at' => 'datetime', 'due_date' => 'date', 'closed_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsTo<JiraProject, $this> */
    public function jiraProject(): BelongsTo
    {
        return $this->belongsTo(JiraProject::class);
    }

    /** @return BelongsTo<BitbucketRepository, $this> */
    public function bitbucketRepository(): BelongsTo
    {
        return $this->belongsTo(BitbucketRepository::class);
    }

    /** @return BelongsTo<IntegrationUser, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(IntegrationUser::class, 'assignee_integration_user_id');
    }

    /** @return BelongsTo<IntegrationUser, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(IntegrationUser::class, 'author_integration_user_id');
    }

    /** @param Builder<ExternalWorkItem> $query
     * @return Builder<ExternalWorkItem>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }
}
