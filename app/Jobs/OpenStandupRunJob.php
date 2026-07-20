<?php

namespace App\Jobs;

use App\Models\StandupRun;
use App\Services\StandupMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OpenStandupRunJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $standupRunId) {}

    public function handle(StandupMessageService $messages): void
    {
        Cache::lock('standup-open:'.$this->standupRunId, 120)->block(5, function () use ($messages): void {
            $run = StandupRun::query()->with('schedule')->findOrFail($this->standupRunId);

            if (
                $run->status !== 'scheduled'
                || ($run->schedule && ! $run->schedule->is_active)
                || ($run->scheduled_open_at && Carbon::parse($run->scheduled_open_at)->isFuture())
            ) {
                return;
            }

            $messages->openRun($run);
        });
    }
}
