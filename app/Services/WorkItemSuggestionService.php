<?php

namespace App\Services;

use App\Models\BitbucketRepository;
use App\Models\ExternalWorkItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class WorkItemSuggestionService
{
    /** @return Collection<int, ExternalWorkItem> */
    public function jiraForUser(User $user, Workspace $workspace): Collection
    {
        if (! config('features.atlassian_integrations')) {
            return new Collection;
        }

        $jiraIntegrationUserIds = $user->integrationUsers()
            ->where('provider', 'jira')
            ->pluck('id');

        return ExternalWorkItem::query()
            ->with('jiraProject:id,project_key,name')
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'jira')
            ->whereIn('assignee_integration_user_id', $jiraIntegrationUserIds)
            ->open()
            ->orderByDesc('is_blocked')
            ->orderByDesc('provider_updated_at')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, ExternalWorkItem> */
    public function forUser(User $user, Workspace $workspace): Collection
    {
        if (! config('features.atlassian_integrations')) {
            return new Collection;
        }

        $externalIds = $user->integrationUsers()->pluck('external_user_id');
        $integrationUserIds = $user->integrationUsers()->pluck('id');
        $repositoryIds = $user->belongsToMany(BitbucketRepository::class, 'bitbucket_repository_user')->pluck('bitbucket_repositories.id');

        return ExternalWorkItem::query()
            ->with(['jiraProject:id,project_key,name', 'bitbucketRepository:id,full_name'])
            ->where('workspace_id', $workspace->id)
            ->open()
            ->where(function ($query) use ($integrationUserIds, $externalIds, $repositoryIds) {
                $query->whereIn('assignee_integration_user_id', $integrationUserIds)
                    ->orWhereIn('author_integration_user_id', $integrationUserIds)
                    ->orWhereIn('bitbucket_repository_id', $repositoryIds);
                foreach ($externalIds as $externalId) {
                    $query->orWhereJsonContains('reviewer_external_ids', $externalId);
                }
            })
            ->orderByDesc('is_blocked')
            ->orderByDesc('provider_updated_at')
            ->limit(100)
            ->get();
    }
}
