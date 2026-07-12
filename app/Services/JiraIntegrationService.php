<?php

namespace App\Services;

use App\Models\ExternalWorkItem;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\JiraProject;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class JiraIntegrationService
{
    public function __construct(private readonly IntegrationTokenService $tokens) {}

    public function sync(Integration $integration): void
    {
        $this->syncUsers($integration);
        $this->syncProjects($integration);
        foreach ($integration->jiraProjects()->whereHas('workspaces')->get() as $project) {
            $this->syncIssues($integration, $project);
        }
        $this->ensureWebhook($integration);
        $this->healthy($integration);
    }

    public function syncUsers(Integration $integration): void
    {
        foreach ($this->pages($integration, '/rest/api/3/users/search', 'values') as $member) {
            if (($member['active'] ?? true) !== true) {
                continue;
            }
            $email = $member['emailAddress'] ?? null;
            $user = $email ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->whereHas('organizations', fn ($q) => $q->whereKey($integration->organization_id))->first() : null;
            IntegrationUser::updateOrCreate(
                ['integration_id' => $integration->id, 'external_user_id' => $member['accountId']],
                ['user_id' => $user?->id, 'provider' => 'jira', 'external_email' => $email, 'display_name' => $member['displayName'] ?? null, 'avatar_url' => data_get($member, 'avatarUrls.48x48'), 'raw_json' => $member],
            );
        }
    }

    public function syncProjects(Integration $integration): void
    {
        $seen = [];
        foreach ($this->pages($integration, '/rest/api/3/project/search') as $project) {
            $seen[] = (string) $project['id'];
            JiraProject::updateOrCreate(
                ['integration_id' => $integration->id, 'external_id' => (string) $project['id']],
                ['project_key' => $project['key'], 'name' => $project['name'], 'avatar_url' => data_get($project, 'avatarUrls.48x48'), 'is_active' => true, 'last_synced_at' => now()],
            );
        }
        $integration->jiraProjects()->when($seen, fn ($q) => $q->whereNotIn('external_id', $seen))->update(['is_active' => false]);
    }

    public function syncIssues(Integration $integration, JiraProject $project): void
    {
        $workspaceIds = $project->workspaces()->pluck('workspaces.id');
        foreach ($workspaceIds as $workspaceId) {
            $seen = [];
            $startAt = 0;
            do {
                $payload = $this->request($integration)->get('/rest/api/3/search/jql', [
                    'jql' => 'project = "'.$project->project_key.'" AND statusCategory != Done AND assignee IS NOT EMPTY',
                    'fields' => 'summary,status,priority,assignee,duedate,updated,labels,issuelinks',
                    'startAt' => $startAt,
                    'maxResults' => 100,
                ])->throw()->json();
                foreach ($payload['issues'] ?? [] as $issue) {
                    $fields = $issue['fields'] ?? [];
                    $accountId = data_get($fields, 'assignee.accountId');
                    $assignee = $accountId ? $integration->users()->where('external_user_id', $accountId)->first() : null;
                    $seen[] = (string) $issue['id'];
                    $isBlocked = false;
                    foreach (($fields['labels'] ?? []) as $label) {
                        if (is_string($label) && str_contains(mb_strtolower($label), 'block')) {
                            $isBlocked = true;
                            break;
                        }
                    }
                    ExternalWorkItem::updateOrCreate(
                        ['provider' => 'jira', 'integration_id' => $integration->id, 'external_id' => (string) $issue['id'], 'workspace_id' => $workspaceId],
                        ['workspace_id' => $workspaceId, 'jira_project_id' => $project->id, 'assignee_integration_user_id' => $assignee?->id, 'external_key' => $issue['key'], 'type' => 'jira_issue', 'title' => $fields['summary'] ?? $issue['key'], 'url' => rtrim((string) data_get($integration->settings(), 'site_url'), '/').'/browse/'.$issue['key'], 'status' => data_get($fields, 'status.name', 'Open'), 'priority' => data_get($fields, 'priority.name'), 'is_blocked' => $isBlocked, 'provider_updated_at' => $fields['updated'] ?? null, 'due_date' => $fields['duedate'] ?? null, 'closed_at' => null, 'last_synced_at' => now(), 'raw_json' => ['statusCategory' => data_get($fields, 'status.statusCategory.key')]],
                    );
                }
                $startAt += count($payload['issues'] ?? []);
            } while ($startAt < (int) ($payload['total'] ?? 0));
            ExternalWorkItem::query()->where('provider', 'jira')->where('integration_id', $integration->id)->where('workspace_id', $workspaceId)->where('jira_project_id', $project->id)->whereNull('closed_at')->when($seen, fn ($q) => $q->whereNotIn('external_id', $seen))->update(['closed_at' => now(), 'status' => 'Done']);
        }
    }

    private function request(Integration $integration): PendingRequest
    {
        $cloudId = data_get($integration->settings(), 'cloud_id');

        return Http::baseUrl("https://api.atlassian.com/ex/jira/{$cloudId}")->withToken($this->tokens->accessToken($integration))->acceptJson()->retry(3, 500, throw: false);
    }

    /** @return array<int, array<string, mixed>> */
    private function pages(Integration $integration, string $path, string $itemsKey = 'values'): array
    {
        $items = [];
        $startAt = 0;
        do {
            $payload = $this->request($integration)->get($path, ['startAt' => $startAt, 'maxResults' => 100])->throw()->json();
            $page = array_is_list($payload) ? $payload : ($payload[$itemsKey] ?? []);
            $items = [...$items, ...$page];
            $startAt += count($page);
        } while (! array_is_list($payload) && $startAt < (int) ($payload['total'] ?? 0));

        return $items;
    }

    private function healthy(Integration $integration): void
    {
        $settings = $integration->settings();
        $settings['last_successful_sync_at'] = now()->toIso8601String();
        unset($settings['last_error']);
        $integration->update(['settings_json' => $settings, 'is_active' => true]);
    }

    private function ensureWebhook(Integration $integration): void
    {
        $keys = $integration->jiraProjects()->whereHas('workspaces')->pluck('project_key')->sort()->values()->all();
        $settings = $integration->settings();
        $registeredAt = data_get($settings, 'webhook_registered_at');
        if ($keys === [] || ($keys === data_get($settings, 'webhook_project_keys', []) && $registeredAt && now()->subDays(25)->lt($registeredAt))) {
            return;
        }

        try {
            $secret = (string) data_get($settings, 'webhook_secret');
            $url = route('atlassian.webhooks', ['provider' => 'jira', 'integration' => $integration, 'secret' => $secret]);
            $payload = $this->request($integration)->post('/rest/api/3/webhook', [
                'url' => $url,
                'webhooks' => [[
                    'events' => ['jira:issue_created', 'jira:issue_updated', 'jira:issue_deleted'],
                    'jqlFilter' => 'project IN ('.implode(', ', $keys).')',
                ]],
            ])->throw()->json();
            $settings['webhook_project_keys'] = $keys;
            $webhookIds = [];
            foreach (($payload['webhookRegistrationResult'] ?? []) as $result) {
                if (is_array($result) && is_numeric($result['createdWebhookId'] ?? null)) {
                    $webhookIds[] = (int) $result['createdWebhookId'];
                }
            }
            $settings['webhook_ids'] = $webhookIds;
            $settings['webhook_registered_at'] = now()->toIso8601String();
            unset($settings['webhook_error']);
            $integration->update(['settings_json' => $settings]);
        } catch (Throwable $exception) {
            $settings['webhook_error'] = $exception->getMessage();
            $integration->update(['settings_json' => $settings]);
            Log::warning('Jira webhook registration failed; polling remains active', ['integration_id' => $integration->id, 'message' => $exception->getMessage()]);
        }
    }
}
