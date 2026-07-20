<?php

namespace App\Jobs;

use App\Models\StandupRun;
use App\Models\StandupRunReminder;
use App\Services\AutomationAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDueStandupRunsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $audit = app(AutomationAuditService::class);

        $audit->run('standups.process-due-runs', function (): array {
            $now = now();
            $opened = 0;
            $reminders = 0;
            $summaries = 0;

            StandupRun::query()
                ->where('status', 'scheduled')
                ->where(fn ($query) => $query
                    ->whereNull('standup_schedule_id')
                    ->orWhereHas('schedule', fn ($query) => $query->where('is_active', true)))
                ->whereNotNull('scheduled_open_at')
                ->where('scheduled_open_at', '<=', $now)
                ->pluck('id')
                ->each(function (int $id) use (&$opened): void {
                    OpenStandupRunJob::dispatch($id);
                    $opened++;
                });

            StandupRunReminder::query()
                ->whereIn('status', ['pending', 'failed'])
                ->where('scheduled_for', '<=', $now)
                ->whereHas('run', fn ($query) => $query
                    ->where('status', 'active')
                    ->whereColumn('standup_run_reminders.scheduled_for', '<', 'standup_runs.summary_due_at'))
                ->pluck('id')
                ->each(function (int $id) use (&$reminders): void {
                    SendStandupRunReminderJob::dispatch($id);
                    $reminders++;
                });

            StandupRun::query()
                ->whereIn('status', ['active', 'completed'])
                ->whereNull('summary_posted_at')
                ->whereNotNull('summary_due_at')
                ->where('summary_due_at', '<=', $now)
                ->pluck('id')
                ->each(function (int $id) use (&$summaries): void {
                    PostStandupSummaryJob::dispatch($id);
                    $summaries++;
                });

            return compact('opened', 'reminders', 'summaries');
        });
    }
}
