<?php

namespace App\Services;

use App\Models\StandupRunReminder;
use Illuminate\Support\Carbon;

class StandupReminderMessageService
{
    public const DEFAULT_TEMPLATE = 'Reminder: {title} ends in {time_remaining} at {end_time}. {url}';

    public function render(StandupRunReminder $reminder): string
    {
        $run = $reminder->run;
        $run->loadMissing('schedule', 'workspace');
        $schedule = $run->schedule;
        $minutes = $reminder->minutes_before_end;
        $remaining = $minutes === 1 ? '1 minute' : $minutes.' minutes';
        $endTime = $run->summary_due_at
            ? Carbon::parse($run->summary_due_at)->timezone($schedule->timezone ?? config('app.timezone'))->format('H:i')
            : '';

        return strtr($schedule?->reminder_message_template ?: self::DEFAULT_TEMPLATE, [
            '{title}' => $schedule?->name ?: $run->workspace->name,
            '{time_remaining}' => $remaining,
            '{end_time}' => $endTime,
            '{url}' => route('standup-runs.submit', $run),
        ]);
    }
}
