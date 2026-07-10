<?php

namespace App\Services;

use App\Models\IntegrationUser;
use App\Models\StandupRun;
use App\Models\User;
use App\Notifications\StandupPromptNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class StandupMessageService
{
    public function __construct(
        private readonly SlackIntegrationService $slack,
        private readonly DiscordIntegrationService $discord,
        private readonly StandupQuestionFlowService $questionFlow,
        private readonly StandupRunCompletionService $completion,
    ) {}

    public function openRun(StandupRun $run): StandupRun
    {
        $run->loadMissing('workspace.users', 'template.questions', 'schedule.targetIntegration', 'schedule.promptChannel', 'schedule.participants');

        $this->completion->completeOlderRunsForSchedule($run);

        $run->forceFill([
            'status' => 'active',
            'opened_at' => now(),
        ])->save();

        foreach ($this->recipientsForRun($run) as $member) {
            $run->responses()->firstOrCreate(
                ['user_id' => $member->id],
                [
                    'organization_id' => $run->organization_id,
                    'workspace_id' => $run->workspace_id,
                    'status' => 'pending',
                    'source' => $this->responseSource($run),
                ],
            );
        }

        $this->sendMessagesToPendingMembers($run, reminder: false);

        return $run;
    }

    public function sendReminder(StandupRun $run, ?string $message = null): void
    {
        $run->loadMissing('workspace.users', 'template.questions', 'schedule.targetIntegration', 'schedule.promptChannel', 'schedule.participants', 'responses');

        $this->sendPrivateReminder($run, $message ?? $this->messageText($run, true));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsForRun(StandupRun $run): Collection
    {
        $query = $run->workspace->users()
            ->wherePivot('is_active', true);

        if ($run->schedule?->recipient_mode === 'selected_members') {
            $selectedIds = $run->schedule->participants->pluck('id');

            return $query
                ->whereIn('users.id', $selectedIds)
                ->orderBy('name')
                ->get();
        }

        return $query
            ->orderBy('name')
            ->get();
    }

    private function sendMessagesToPendingMembers(StandupRun $run, bool $reminder): void
    {
        $pendingCount = $run->responses()->where('status', 'pending')->count();

        Log::info('Standup pending notification dispatch started', [
            'standup_run_id' => $run->id,
            'standup_schedule_id' => $run->standup_schedule_id,
            'delivery_method' => $run->schedule?->delivery_method ?? 'email',
            'pending_response_count' => $pendingCount,
            'reminder' => $reminder,
        ]);

        if ($pendingCount === 0) {
            Log::info('Standup pending notification dispatch skipped: no pending responses', [
                'standup_run_id' => $run->id,
                'standup_schedule_id' => $run->standup_schedule_id,
            ]);

            return;
        }

        match ($run->schedule?->delivery_method ?? 'email') {
            'email' => $this->sendEmailMessages($run, $reminder),
            'slack_dm' => $this->sendSlackDirectMessages($run, $reminder),
            'slack_channel' => $this->sendSlackChannelMessage($run, $reminder),
            'discord_channel' => $this->sendDiscordChannelMessage($run, $reminder),
            default => null,
        };
    }

    private function sendPrivateReminder(StandupRun $run, string $message): void
    {
        $pendingUsers = $this->pendingUsers($run);

        if ($pendingUsers->isEmpty()) {
            return;
        }

        $deliveryMethod = $run->schedule?->delivery_method ?? 'email';
        $integration = $run->schedule?->targetIntegration;

        if (! in_array($deliveryMethod, ['slack_dm', 'slack_channel'], true) || ! $integration || $integration->provider !== 'slack') {
            foreach ($pendingUsers as $member) {
                $member->notify(new StandupPromptNotification($run, true, $message));
            }

            return;
        }

        $integrationUsers = IntegrationUser::query()
            ->where('integration_id', $integration->id)
            ->whereIn('user_id', $pendingUsers->pluck('id'))
            ->get()
            ->keyBy('user_id');

        foreach ($pendingUsers as $member) {
            $integrationUser = $integrationUsers->get($member->id);

            if ($integrationUser?->external_user_id) {
                $this->slack->sendDirectMessage($integration, $integrationUser->external_user_id, $message);
            } else {
                $member->notify(new StandupPromptNotification($run, true, $message));
            }
        }
    }

    private function sendEmailMessages(StandupRun $run, bool $reminder): void
    {
        foreach ($this->pendingUsers($run) as $member) {
            $member->notify(new StandupPromptNotification($run, $reminder));
        }
    }

    private function sendDiscordChannelMessage(StandupRun $run, bool $reminder): void
    {
        $integration = $run->schedule?->targetIntegration;

        if (! $integration || $integration->provider !== 'discord') {
            return;
        }

        $this->discord->postWebhook($integration, $this->messageText($run, $reminder));
    }

    private function sendSlackChannelMessage(StandupRun $run, bool $reminder): void
    {
        $integration = $run->schedule?->targetIntegration;

        if (! $integration || $integration->provider !== 'slack' || ! $run->schedule?->promptChannel) {
            return;
        }

        $this->slack->postStandupPrompt(
            $integration,
            $run->schedule->promptChannel->external_channel_id,
            $run,
            $this->messageText($run, $reminder),
        );
    }

    private function sendSlackDirectMessages(StandupRun $run, bool $reminder): void
    {
        $integration = $run->schedule?->targetIntegration;

        if (! $integration || $integration->provider !== 'slack') {
            Log::warning('Slack DM standup dispatch skipped: missing Slack integration', [
                'standup_run_id' => $run->id,
                'standup_schedule_id' => $run->standup_schedule_id,
                'target_integration_id' => $run->schedule?->target_integration_id,
            ]);

            return;
        }

        $pendingUserIds = $run->responses()
            ->where('status', 'pending')
            ->pluck('user_id');

        $integrationUsers = IntegrationUser::query()
            ->where('integration_id', $integration->id)
            ->whereIn('user_id', $pendingUserIds)
            ->get();

        Log::info('Slack DM standup dispatch recipients resolved', [
            'standup_run_id' => $run->id,
            'standup_schedule_id' => $run->standup_schedule_id,
            'integration_id' => $integration->id,
            'pending_response_count' => $pendingUserIds->count(),
            'mapped_slack_user_count' => $integrationUsers->count(),
            'unmapped_user_ids' => $pendingUserIds
                ->diff($integrationUsers->pluck('user_id'))
                ->values()
                ->all(),
        ]);

        foreach ($integrationUsers as $integrationUser) {
            $response = $run->responses()
                ->with('answers')
                ->where('user_id', $integrationUser->user_id)
                ->first();
            $question = $response ? $this->questionFlow->nextQuestionForResponse($run, $response) : null;

            if (! $question) {
                Log::info('Slack DM standup question skipped: no unanswered questions', [
                    'standup_run_id' => $run->id,
                    'integration_id' => $integration->id,
                    'user_id' => $integrationUser->user_id,
                ]);

                continue;
            }

            $questions = $this->questionFlow->orderedQuestions($run);

            Log::info('Slack DM standup prompt sending', [
                'standup_run_id' => $run->id,
                'integration_id' => $integration->id,
                'user_id' => $integrationUser->user_id,
                'external_user_id' => $integrationUser->external_user_id,
                'standup_question_id' => $question->id,
                'reminder' => $reminder,
            ]);

            $this->slack->sendDirectStandupQuestion(
                $integration,
                $integrationUser->external_user_id,
                $run,
                $question,
                $questions->search(fn ($candidate) => $candidate->id === $question->id) + 1,
                $questions->count(),
            );

            $response->forceFill([
                'raw_json' => [
                    ...($response->raw_json ?? []),
                    'current_question_id' => $question->id,
                ],
            ])->save();
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function pendingUsers(StandupRun $run): Collection
    {
        $pendingUserIds = $run->responses()
            ->where('status', 'pending')
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $pendingUserIds)
            ->orderBy('name')
            ->get();
    }

    private function responseSource(StandupRun $run): string
    {
        return match ($run->schedule?->delivery_method ?? 'email') {
            'slack_dm', 'slack_channel' => 'slack',
            'discord_channel' => 'discord',
            default => 'email',
        };
    }

    private function messageText(StandupRun $run, bool $reminder): string
    {
        $prefix = $reminder ? 'Reminder: ' : '';
        $url = route('standup-runs.submit', $run);

        return "{$prefix}Submit today's standup for {$run->workspace->name}: {$url}";
    }

    private function slackPromptText(StandupRun $run, bool $reminder): string
    {
        $prefix = $reminder ? 'Reminder: ' : '';

        return "{$prefix}Submit today's standup for {$run->workspace->name}.";
    }
}
