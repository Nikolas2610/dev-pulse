<?php

namespace App\Jobs;

use App\Models\StandupRun;
use App\Services\StandupMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendStandupReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $standupRunId) {}

    public function handle(StandupMessageService $messages): void
    {
        $messages->sendReminder(StandupRun::findOrFail($this->standupRunId));
    }
}
