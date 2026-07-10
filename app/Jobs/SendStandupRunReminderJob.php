<?php

namespace App\Jobs;

use App\Models\StandupRunReminder;
use App\Services\StandupMessageService;
use App\Services\StandupReminderMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendStandupRunReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $standupRunReminderId) {}

    public function handle(StandupMessageService $messages, StandupReminderMessageService $renderer): void
    {
        $reminder = StandupRunReminder::query()->with('run.schedule', 'run.workspace')->findOrFail($this->standupRunReminderId);

        if ($reminder->sent_at || ! in_array($reminder->status, ['pending', 'failed'], true)) {
            return;
        }

        if ($reminder->scheduled_for->isFuture()) {
            return;
        }

        if ($reminder->run->status !== 'active' || ! $reminder->run->summary_due_at || $reminder->scheduled_for->greaterThanOrEqualTo($reminder->run->summary_due_at)) {
            $reminder->update(['status' => 'skipped']);

            return;
        }

        $claimed = StandupRunReminder::query()
            ->whereKey($reminder->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claimed_at' => now(),
                'attempts' => $reminder->attempts + 1,
                'last_error' => null,
            ]);

        if ($claimed !== 1) {
            return;
        }

        try {
            $messages->sendReminder($reminder->run, $renderer->render($reminder));
            $reminder->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $reminder->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
