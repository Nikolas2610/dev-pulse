<?php

namespace App\Services;

use App\Jobs\PostLateStandupAddendumJob;
use App\Jobs\SendManagerBlockerAlertJob;
use App\Models\StandupResponse;
use Illuminate\Support\Carbon;

class StandupSubmissionFinalizer
{
    public function __construct(private readonly ManagerBlockerAlertService $managerBlockerAlerts) {}

    public function handle(StandupResponse $response): void
    {
        $response->loadMissing('run.schedule', 'answers.question');

        if ($response->run->schedule?->notify_manager_on_blocker
            && $this->managerBlockerAlerts->hasAffirmativeBlocker($response)) {
            SendManagerBlockerAlertJob::dispatch($response->id)->afterCommit();
        }

        if ($response->run->summary_due_at && $response->submitted_at && Carbon::parse($response->submitted_at)->greaterThan(Carbon::parse($response->run->summary_due_at))) {
            PostLateStandupAddendumJob::dispatch($response->id)->afterCommit();
        }
    }
}
