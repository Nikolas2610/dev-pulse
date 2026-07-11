<?php

namespace App\Services;

use App\Models\StandupRun;
use App\Models\StandupSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class StandupTimingService
{
    /**
     * @return array{open: CarbonInterface, summary: CarbonInterface}
     */
    public function forDate(StandupSchedule $schedule, string $runDate): array
    {
        $open = Carbon::parse($runDate.' '.substr((string) $schedule->time_of_day, 0, 8), $schedule->timezone);
        $endTime = $schedule->end_time_of_day
            ?: $open->copy()->addMinutes((int) ($schedule->summary_minutes_after ?: 90))->format('H:i:s');
        $summary = Carbon::parse($runDate.' '.substr((string) $endTime, 0, 8), $schedule->timezone);

        if ($summary->lessThanOrEqualTo($open)) {
            $summary->addDay();
        }

        return [
            'open' => $open->utc(),
            'summary' => $summary->utc(),
        ];
    }

    public function snapshot(StandupRun $run, StandupSchedule $schedule): void
    {
        $timing = $this->forDate($schedule, Carbon::parse($run->run_date)->toDateString());

        $this->persistSnapshot($run, $schedule, $timing['open'], $timing['summary']);
    }

    public function snapshotManual(StandupRun $run, StandupSchedule $schedule): void
    {
        $scheduledTiming = $this->forDate($schedule, Carbon::parse($run->run_date)->toDateString());
        $duration = (int) $scheduledTiming['open']->diffInMinutes($scheduledTiming['summary']);
        $open = now()->utc();

        $this->persistSnapshot($run, $schedule, $open, $open->copy()->addMinutes($duration));
    }

    private function persistSnapshot(StandupRun $run, StandupSchedule $schedule, CarbonInterface $open, CarbonInterface $summary): void
    {

        $run->forceFill([
            'scheduled_open_at' => $open,
            'summary_due_at' => $summary,
        ])->save();

        foreach ($this->offsets($schedule) as $offset) {
            $scheduledFor = $summary->copy()->subMinutes($offset);

            if ($scheduledFor->lessThanOrEqualTo($open)) {
                continue;
            }

            $run->reminders()->firstOrCreate(
                ['minutes_before_end' => $offset],
                ['scheduled_for' => $scheduledFor, 'status' => 'pending'],
            );
        }
    }

    /** @return array<int, int> */
    public function offsets(StandupSchedule $schedule): array
    {
        $offsets = $schedule->getAttribute('reminder_minutes_before_end');

        if (is_string($offsets)) {
            $decoded = json_decode($offsets, true);
            $offsets = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($offsets)) {
            $duration = max(1, (int) ($schedule->summary_minutes_after ?: 90));
            $legacyReminder = (int) ($schedule->reminder_minutes_after ?: 0);
            $offsets = $legacyReminder > 0 && $legacyReminder < $duration
                ? [$duration - $legacyReminder]
                : [60, 30, 15, 5];
        }

        return collect($offsets)
            ->map(fn ($offset): int => (int) $offset)
            ->filter(fn (int $offset): bool => $offset > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
