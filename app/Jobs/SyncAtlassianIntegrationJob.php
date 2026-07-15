<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\BitbucketIntegrationService;
use App\Services\JiraIntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAtlassianIntegrationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $integrationId) {}

    public function handle(JiraIntegrationService $jira, BitbucketIntegrationService $bitbucket): void
    {
        $integration = Integration::findOrFail($this->integrationId);
        try {
            match ($integration->provider) {
                'jira' => $jira->sync($integration),
                'bitbucket' => $bitbucket->sync($integration),
                default => null,
            };
        } catch (Throwable $e) {
            $settings = $integration->settings();
            $settings['last_error'] = $e->getMessage();
            $settings['last_failed_sync_at'] = now()->toIso8601String();
            $integration->update(['settings_json' => $settings]);
            Log::error('Atlassian integration sync failed', ['integration_id' => $integration->id, 'provider' => $integration->provider, 'exception' => $e::class, 'message' => $e->getMessage()]);
            throw $e;
        }
    }
}
