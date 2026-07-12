<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'slug', 'timezone', 'is_active', 'stale_pr_days'])]
/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property bool $is_active
 * @property int $stale_pr_days
 */
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot(['role', 'is_active'])->withTimestamps();
    }

    /** @return HasMany<StandupTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(StandupTemplate::class);
    }

    /** @return BelongsToMany<JiraProject, $this> */
    public function jiraProjects(): BelongsToMany
    {
        return $this->belongsToMany(JiraProject::class, 'workspace_jira_project')->withTimestamps();
    }

    /** @return BelongsToMany<BitbucketRepository, $this> */
    public function bitbucketRepositories(): BelongsToMany
    {
        return $this->belongsToMany(BitbucketRepository::class, 'workspace_bitbucket_repository')->withTimestamps();
    }
}
