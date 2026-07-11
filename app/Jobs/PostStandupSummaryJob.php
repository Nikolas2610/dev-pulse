<?php

namespace App\Jobs;

use App\Models\StandupRun;
use App\Services\StandupSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PostStandupSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $standupRunId) {}

    public function handle(StandupSummaryService $summaries): void
    {
        Cache::lock('standup-summary:'.$this->standupRunId, 300)->block(5, function () use ($summaries): void {
            $run = StandupRun::findOrFail($this->standupRunId);

            if ($run->summary_posted_at || ($run->summary_due_at && Carbon::parse($run->summary_due_at)->isFuture())) {
                return;
            }

            $summaries->postSummary($run);
        });
    }
}
