<?php

namespace App\Services;

use App\Models\StandupRun;
use App\Models\StandupSchedule;
use Illuminate\Support\Carbon;

class StandupSchedulerService
{
    public function __construct(
        private readonly ActivityEventService $events,
        private readonly StandupTimingService $timing,
    ) {}

    public function generateDueRuns(?Carbon $date = null): int
    {
        $created = 0;

        StandupSchedule::query()
            ->with('organization', 'workspace')
            ->where('is_active', true)
            ->each(function (StandupSchedule $schedule) use ($date, &$created): void {
                $scheduleDate = ($date ?? now($schedule->timezone))->copy()->timezone($schedule->timezone);

                if (! in_array((int) $scheduleDate->dayOfWeekIso, $this->daysOfWeek($schedule), true)) {
                    return;
                }

                $run = StandupRun::firstOrCreate(
                    [
                        'standup_schedule_id' => $schedule->id,
                        'run_date' => $scheduleDate->toDateString(),
                    ],
                    [
                        'organization_id' => $schedule->organization_id,
                        'workspace_id' => $schedule->workspace_id,
                        'standup_template_id' => $schedule->standup_template_id,
                        'status' => 'scheduled',
                    ],
                );

                if ($run->wasRecentlyCreated) {
                    $this->timing->snapshot($run, $schedule);

                    $this->events->record(
                        $schedule->organization,
                        ActivityEventService::STANDUP_RUN_CREATED,
                        source: 'system',
                        workspace: $schedule->workspace,
                        subject: $run,
                    );

                    $created++;
                } elseif (! $run->scheduled_open_at || ! $run->summary_due_at) {
                    $this->timing->snapshot($run, $schedule);
                }
            });

        return $created;
    }

    /**
     * @return array<int, int>
     */
    private function daysOfWeek(StandupSchedule $schedule): array
    {
        $days = $schedule->getAttribute('days_of_week');

        if (is_string($days)) {
            $decoded = json_decode($days, true);
            $days = is_array($decoded) ? $decoded : [];
        }

        return collect(is_array($days) ? $days : [])
            ->map(fn ($day): int => (int) $day)
            ->all();
    }
}
