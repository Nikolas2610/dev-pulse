<?php

namespace App\Services;

use App\Jobs\PostLateStandupAddendumJob;
use App\Models\StandupResponse;
use Illuminate\Support\Carbon;

class StandupSubmissionFinalizer
{
    public function handle(StandupResponse $response): void
    {
        $response->loadMissing('run');

        if ($response->run->summary_due_at && $response->submitted_at && Carbon::parse($response->submitted_at)->greaterThan(Carbon::parse($response->run->summary_due_at))) {
            PostLateStandupAddendumJob::dispatch($response->id)->afterCommit();
        }
    }
}
