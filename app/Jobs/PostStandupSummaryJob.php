<?php

namespace App\Jobs;

use App\Models\StandupRun;
use App\Services\StandupSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PostStandupSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $standupRunId) {}

    public function handle(StandupSummaryService $summaries): void
    {
        $run = StandupRun::findOrFail($this->standupRunId);

        $summaries->postSummary($run);
    }
}
