<?php

namespace App\Jobs;

use App\Models\StandupRun;
use App\Services\StandupMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OpenStandupRunJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $standupRunId) {}

    public function handle(StandupMessageService $messages): void
    {
        $run = StandupRun::findOrFail($this->standupRunId);

        $messages->openRun($run);
    }
}
