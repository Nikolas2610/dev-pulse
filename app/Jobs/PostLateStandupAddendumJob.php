<?php

namespace App\Jobs;

use App\Models\StandupResponse;
use App\Services\StandupSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PostLateStandupAddendumJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public int $standupResponseId) {}

    public function handle(StandupSummaryService $summaries): void
    {
        Cache::lock('standup-late-summary:'.$this->standupResponseId, 300)->block(5, function () use ($summaries): void {
            $response = StandupResponse::query()->with('run')->findOrFail($this->standupResponseId);

            if ($response->late_summary_posted_at) {
                return;
            }

            if (! $response->run->summary_posted_at) {
                $this->release(60);

                return;
            }

            $summaries->postLateAddendum($response);
        });
    }
}
