<?php

namespace App\Jobs;

use App\Services\StandupSchedulerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateStandupRunsJob implements ShouldQueue
{
    use Queueable;

    public function handle(StandupSchedulerService $scheduler): void
    {
        $scheduler->generateDueRuns();
    }
}
