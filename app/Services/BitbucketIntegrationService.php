<?php

namespace App\Services;

use App\Models\BitbucketRepository;
use App\Models\ExternalWorkItem;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BitbucketIntegrationService
{
    public function __construct(private readonly IntegrationTokenService $tokens) {}

    public function sync(Integration $integration): void
    {
        $this->syncRepositories($integration);
        $this->ensureWebhooks($integration);
        foreach ($integration->bitbucketRepositories()->whereHas('workspaces')->get() as $repository) {
            $this->syncPullRequests($integration, $repository);
        }
        $settings = $integration->settings();
        $settings['last_successful_sync_at'] = now()->toIso8601String();
        unset($settings['last_error']);
        $integration->update(['settings_json' => $settings, 'is_active' => true]);
    }

    public function syncRepositories(Integration $integration): void
    {
        $selected = data_get($integration->settings(), 'workspace_slugs', []);
        $selected = is_array($selected) ? $selected : [];
        $seen = [];
        foreach ($selected as $workspace) {
            if (! is_string($workspace)) {
                continue;
            }
            foreach ($this->pages($integration, "/repositories/{$workspace}") as $repo) {
                $seen[] = $repo['uuid'];
                BitbucketRepository::updateOrCreate(
                    ['integration_id' => $integration->id, 'repository_uuid' => $repo['uuid']],
                    ['workspace_uuid' => data_get($repo, 'workspace.uuid', ''), 'workspace_slug' => data_get($repo, 'workspace.slug', $workspace), 'repository_slug' => $repo['slug'], 'full_name' => $repo['full_name'], 'project_name' => data_get($repo, 'project.name'), 'main_branch' => data_get($repo, 'mainbranch.name'), 'is_private' => $repo['is_private'] ?? true, 'is_active' => true, 'last_synced_at' => now()],
                );
            }
        }
        $integration->bitbucketRepositories()->when($seen, fn ($q) => $q->whereNotIn('repository_uuid', $seen))->update(['is_active' => false]);
    }

    public function syncPullRequests(Integration $integration, BitbucketRepository $repository): void
    {
        $workspaceIds = $repository->workspaces()->pluck('workspaces.id');
        $seen = [];
        foreach ($this->pages($integration, "/repositories/{$repository->workspace_slug}/{$repository->repository_slug}/pullrequests", ['state' => 'OPEN', 'pagelen' => 50]) as $pr) {
            $author = $this->upsertUser($integration, $pr['author'] ?? []);
            $reviewerIds = [];
            foreach (($pr['reviewers'] ?? []) as $reviewer) {
                if (! is_array($reviewer)) {
                    continue;
                }
                $this->upsertUser($integration, $reviewer);
                if (is_string($reviewer['uuid'] ?? null)) {
                    $reviewerIds[] = $reviewer['uuid'];
                }
            }
            $seen[] = (string) $pr['id'];
            foreach ($workspaceIds as $workspaceId) {
                ExternalWorkItem::updateOrCreate(
                    ['provider' => 'bitbucket', 'integration_id' => $integration->id, 'external_id' => (string) $pr['id'].'@'.$repository->repository_uuid, 'workspace_id' => $workspaceId],
                    ['workspace_id' => $workspaceId, 'bitbucket_repository_id' => $repository->id, 'author_integration_user_id' => $author?->id, 'external_key' => '#'.$pr['id'], 'type' => 'pull_request', 'title' => $pr['title'] ?? 'Pull request #'.$pr['id'], 'url' => data_get($pr, 'links.html.href'), 'status' => $pr['state'] ?? 'OPEN', 'is_draft' => $pr['draft'] ?? false, 'task_count' => (int) ($pr['task_count'] ?? 0), 'reviewer_external_ids' => $reviewerIds, 'provider_updated_at' => $pr['updated_on'] ?? null, 'closed_at' => null, 'last_synced_at' => now(), 'raw_json' => ['repository' => $repository->full_name]],
                );
            }
        }
        $seenExternalIds = array_map(fn ($id) => $id.'@'.$repository->repository_uuid, $seen);
        ExternalWorkItem::query()->where('provider', 'bitbucket')->where('integration_id', $integration->id)->where('bitbucket_repository_id', $repository->id)->whereNull('closed_at')->when($seenExternalIds, fn ($q) => $q->whereNotIn('external_id', $seenExternalIds))->update(['closed_at' => now(), 'status' => 'CLOSED']);
    }

    /** @param array<string, mixed> $member */
    private function upsertUser(Integration $integration, array $member): ?IntegrationUser
    {
        $externalId = $member['uuid'] ?? null;
        if (! $externalId) {
            return null;
        }
        $email = $member['email'] ?? null;
        $user = $email ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->whereHas('organizations', fn ($q) => $q->whereKey($integration->organization_id))->first() : null;

        return IntegrationUser::updateOrCreate(
            ['integration_id' => $integration->id, 'external_user_id' => $externalId],
            ['user_id' => $user?->id, 'provider' => 'bitbucket', 'external_username' => $member['nickname'] ?? $member['username'] ?? null, 'external_email' => $email, 'display_name' => $member['display_name'] ?? null, 'avatar_url' => data_get($member, 'links.avatar.href'), 'raw_json' => $member],
        );
    }

    private function request(Integration $integration): PendingRequest
    {
        return Http::baseUrl('https://api.bitbucket.org/2.0')->withToken($this->tokens->accessToken($integration))->acceptJson()->retry(3, 500, throw: false);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function pages(Integration $integration, string $path, array $query = []): array
    {
        $items = [];
        $url = $path;
        do {
            $payload = $this->request($integration)->get($url, $query)->throw()->json();
            $items = [...$items, ...($payload['values'] ?? [])];
            $url = $payload['next'] ?? null;
            $query = [];
        } while ($url);

        return $items;
    }

    private function ensureWebhooks(Integration $integration): void
    {
        $settings = $integration->settings();
        $registered = data_get($settings, 'repository_webhooks', []);
        $registered = is_array($registered) ? $registered : [];
        $secret = (string) data_get($settings, 'webhook_secret');

        foreach ($integration->bitbucketRepositories()->whereHas('workspaces')->get() as $repository) {
            if (isset($registered[$repository->repository_uuid])) {
                continue;
            }
            try {
                $payload = $this->request($integration)->post("/repositories/{$repository->workspace_slug}/{$repository->repository_slug}/hooks", [
                    'description' => 'DevPulse engineering summaries',
                    'url' => route('atlassian.webhooks', ['provider' => 'bitbucket', 'integration' => $integration]),
                    'active' => true,
                    'secret' => $secret,
                    'events' => ['pullrequest:created', 'pullrequest:updated', 'pullrequest:approved', 'pullrequest:fulfilled', 'pullrequest:rejected'],
                ])->throw()->json();
                if (is_string($payload['uuid'] ?? null)) {
                    $registered[$repository->repository_uuid] = $payload['uuid'];
                }
            } catch (Throwable $exception) {
                $settings['webhook_error'] = $exception->getMessage();
                Log::warning('Bitbucket webhook registration failed; polling remains active', ['integration_id' => $integration->id, 'repository_id' => $repository->id, 'message' => $exception->getMessage()]);
            }
        }

        $settings['repository_webhooks'] = $registered;
        $integration->update(['settings_json' => $settings]);
    }
}
