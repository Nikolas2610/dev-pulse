<?php

namespace App\Services;

use App\Models\StandupRun;

class StandupRunCompletionService
{
    public function completeIfAllResponsesSubmitted(StandupRun $run): void
    {
        $run->loadCount([
            'responses',
            'responses as pending_responses_count' => fn ($query) => $query->where('status', 'pending'),
        ]);

        if ($run->responses_count === 0 || (int) $run->getAttribute('pending_responses_count') > 0) {
            return;
        }

        if ($run->status === 'completed') {
            return;
        }

        $run->forceFill(['status' => 'completed'])->save();
    }

    public function completeOlderRunsForSchedule(StandupRun $run): void
    {
        if (! $run->standup_schedule_id) {
            return;
        }

        StandupRun::query()
            ->where('standup_schedule_id', $run->standup_schedule_id)
            ->whereKeyNot($run->id)
            ->whereIn('status', ['scheduled', 'active'])
            ->update(['status' => 'completed']);
    }
}
