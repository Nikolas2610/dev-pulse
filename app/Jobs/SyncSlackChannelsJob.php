<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\SlackIntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSlackChannelsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $integrationId) {}

    public function handle(SlackIntegrationService $slack): void
    {
        $slack->syncChannels(Integration::findOrFail($this->integrationId));
    }
}
