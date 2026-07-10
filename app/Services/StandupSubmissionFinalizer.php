<?php

namespace App\Services;

use App\Jobs\PostLateStandupAddendumJob;
use App\Models\StandupResponse;

class StandupSubmissionFinalizer
{
    public function handle(StandupResponse $response): void
    {
        $response->loadMissing('run');

        if ($response->run->summary_due_at && $response->submitted_at?->greaterThan($response->run->summary_due_at)) {
            PostLateStandupAddendumJob::dispatch($response->id)->afterCommit();
        }
    }
}
