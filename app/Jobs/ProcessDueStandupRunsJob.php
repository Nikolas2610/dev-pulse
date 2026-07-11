<?php

namespace App\Jobs;

use App\Models\StandupRun;
use App\Models\StandupRunReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDueStandupRunsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $now = now();

        StandupRun::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_open_at')
            ->where('scheduled_open_at', '<=', $now)
            ->pluck('id')
            ->each(fn (int $id) => OpenStandupRunJob::dispatch($id));

        StandupRunReminder::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('scheduled_for', '<=', $now)
            ->whereHas('run', fn ($query) => $query
                ->where('status', 'active')
                ->whereColumn('standup_run_reminders.scheduled_for', '<', 'standup_runs.summary_due_at'))
            ->pluck('id')
            ->each(fn (int $id) => SendStandupRunReminderJob::dispatch($id));

        StandupRun::query()
            ->whereIn('status', ['active', 'completed'])
            ->whereNull('summary_posted_at')
            ->whereNotNull('summary_due_at')
            ->where('summary_due_at', '<=', $now)
            ->pluck('id')
            ->each(fn (int $id) => PostStandupSummaryJob::dispatch($id));
    }
}
