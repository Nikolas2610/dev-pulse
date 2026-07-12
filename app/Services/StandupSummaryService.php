<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\IntegrationChannel;
use App\Models\IntegrationUser;
use App\Models\StandupQuestion;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\StandupSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StandupSummaryService
{
    public function __construct(
        private readonly SlackIntegrationService $slack,
        private readonly DiscordIntegrationService $discord,
        private readonly ActivityEventService $events,
    ) {}

    public function postSummary(StandupRun $run, bool $requireExternalDelivery = false): StandupSummary
    {
        $run->loadMissing([
            'organization',
            'workspace',
            'schedule',
            'schedule.targetIntegration',
            'schedule.targetChannel',
            'responses.user',
            'responses.answers.question',
        ]);

        $summaryText = $this->renderSummary($run);
        $externalMessageId = null;
        $integration = $run->schedule?->targetIntegration;
        $channel = $run->schedule?->targetChannel;

        Log::info('Standup summary post started', [
            'standup_run_id' => $run->id,
            'standup_schedule_id' => $run->standup_schedule_id,
            'integration_id' => $integration?->id,
            'integration_provider' => $integration?->provider,
            'channel_id' => $channel?->id,
            'external_channel_id' => $channel?->external_channel_id,
            'require_external_delivery' => $requireExternalDelivery,
        ]);

        if ($requireExternalDelivery && ! $integration) {
            throw new RuntimeException('No summary integration is configured for this schedule.');
        }

        if ($integration?->provider === 'slack') {
            if (! $channel) {
                if ($requireExternalDelivery) {
                    throw new RuntimeException('No Slack summary channel is configured for this schedule.');
                }

                Log::warning('Standup summary generated without Slack delivery: missing summary channel', [
                    'standup_run_id' => $run->id,
                    'standup_schedule_id' => $run->standup_schedule_id,
                    'integration_id' => $integration->id,
                ]);
            } else {
                $externalMessageId = $this->postSlackSummaryMessages($run, $integration, $channel);
            }
        } elseif ($integration?->provider === 'discord') {
            $externalMessageId = $this->discord->postWebhook(
                $integration,
                $summaryText,
            );
        } elseif ($requireExternalDelivery) {
            throw new RuntimeException('The configured summary integration is not supported.');
        }

        $summary = StandupSummary::updateOrCreate(
            ['standup_run_id' => $run->id],
            [
                'organization_id' => $run->organization_id,
                'workspace_id' => $run->workspace_id,
                'summary_text' => $summaryText,
                'posted_to_integration_id' => $run->schedule?->target_integration_id,
                'posted_to_channel_id' => $run->schedule?->target_channel_id,
                'external_message_id' => $externalMessageId,
            ],
        );

        $hasPendingResponses = $run->responses->contains(fn ($response): bool => $response->status === 'pending');
        $run->forceFill([
            'status' => $hasPendingResponses ? 'active' : 'completed',
            'summary_posted_at' => now(),
        ])->save();

        $this->events->record(
            $run->organization,
            ActivityEventService::STANDUP_SUMMARY_POSTED,
            source: $integration instanceof Integration ? $integration->provider : 'system',
            workspace: $run->workspace,
            subject: $summary,
            externalId: $externalMessageId,
        );

        Log::info('Standup summary post completed', [
            'standup_run_id' => $run->id,
            'standup_schedule_id' => $run->standup_schedule_id,
            'external_message_id' => $externalMessageId,
        ]);

        return $summary;
    }

    public function renderSummary(StandupRun $run): string
    {
        $recipients = $run->responses->map(fn ($response) => $response->user)->filter();
        $responses = $this->responsesAtCheckpoint($run);
        $skipped = $run->responses->where('status', 'skipped')->sortBy(fn ($response) => $response->user?->name);
        $completedCount = $responses->count() + $skipped->count();
        $pending = $run->responses->where('status', 'pending')
            ->sortBy(fn ($response) => $response->user?->name);
        $lines = [
            "*Daily standup - {$run->workspace->name}*",
            Carbon::parse($run->run_date)->toDateString(),
            "Completed: *{$completedCount}/{$recipients->count()}*",
            '',
        ];

        foreach ($responses as $response) {
            $lines[] = "*{$response->user->name} submitted their standup*";

            foreach ($response->answers->sortBy(fn ($answer) => $answer->question->sort_order) as $answer) {
                $lines[] = '- *'.$this->summaryQuestionLabel($answer->question).':* '.($answer->answer_text ?: '-');
            }

            $lines[] = '';
        }

        if ($skipped->isNotEmpty()) {
            $lines[] = '*Skipped responses*';

            foreach ($skipped as $response) {
                $lines[] = '- '.$response->user?->name;
            }

            $lines[] = '';
        }

        if ($pending->isNotEmpty()) {
            $lines[] = '*Pending responses*';

            foreach ($pending as $response) {
                $lines[] = '- '.$response->user?->name;
            }
        }

        return trim(implode("\n", $lines));
    }

    private function postSlackSummaryMessages(
        StandupRun $run,
        Integration $integration,
        IntegrationChannel $channel,
    ): ?string {
        $responses = $this->responsesAtCheckpoint($run);
        $skipped = $run->responses->where('status', 'skipped')->sortBy(fn ($response) => $response->user?->name);
        $pending = $run->responses->where('status', 'pending')
            ->sortBy(fn ($response) => $response->user?->name);
        $integrationUsers = $integration->users()
            ->whereNotNull('user_id')
            ->get()
            ->keyBy('user_id');
        $messageIds = [];

        foreach ($responses as $response) {
            $messageIds[] = $this->slack->postMessage(
                $integration,
                $channel->external_channel_id,
                $this->renderSlackSubmittedResponse($response, $integrationUsers),
            );
        }

        if ($skipped->isNotEmpty()) {
            $messageIds[] = $this->slack->postMessage(
                $integration,
                $channel->external_channel_id,
                $this->renderSlackSkippedResponses($skipped, $integrationUsers),
            );
        }

        if ($pending->isNotEmpty()) {
            $messageIds[] = $this->slack->postMessage(
                $integration,
                $channel->external_channel_id,
                $this->renderSlackPendingResponses($pending, $integrationUsers),
            );
        }

        return collect($messageIds)->filter()->first();
    }

    public function postLateAddendum(StandupResponse $response): void
    {
        $response->loadMissing('user', 'answers.question', 'run.schedule.targetIntegration', 'run.schedule.targetChannel');
        $run = $response->run;
        $integration = $run->schedule?->targetIntegration;
        $channel = $run->schedule?->targetChannel;

        if ($integration?->provider === 'slack' && $channel) {
            $integrationUsers = $integration->users()->whereNotNull('user_id')->get()->keyBy('user_id');
            $this->slack->postMessage(
                $integration,
                $channel->external_channel_id,
                '*Late update*'.PHP_EOL.$this->renderSlackSubmittedResponse($response, $integrationUsers),
            );
        } elseif ($integration?->provider === 'discord') {
            $this->discord->postWebhook($integration, $this->renderLateAddendum($response));
        } else {
            Log::info('Late standup addendum skipped: no external summary destination', [
                'standup_run_id' => $run->id,
                'standup_response_id' => $response->id,
            ]);
        }

        $response->forceFill(['late_summary_posted_at' => now()])->save();
    }

    /**
     * @return Collection<int, StandupResponse>
     */
    private function responsesAtCheckpoint(StandupRun $run): Collection
    {
        return $run->responses
            ->filter(fn ($response): bool => $response->status === 'submitted'
                && (! $run->summary_due_at || ($response->submitted_at && Carbon::parse($response->submitted_at)->lessThanOrEqualTo(Carbon::parse($run->summary_due_at)))))
            ->sortBy('submitted_at');
    }

    private function renderLateAddendum(StandupResponse $response): string
    {
        $lines = ['Late update from '.$response->user->name, ''];

        foreach ($response->answers->sortBy(fn ($answer) => $answer->question->sort_order) as $answer) {
            $lines[] = $this->summaryQuestionLabel($answer->question).': '.($answer->answer_text ?: '-');
        }

        return trim(implode(PHP_EOL, $lines));
    }

    /**
     * @param  Collection<int, IntegrationUser>  $integrationUsers
     */
    private function renderSlackSubmittedResponse(StandupResponse $response, Collection $integrationUsers): string
    {
        $slackUser = $integrationUsers->get($response->user_id);
        $memberLabel = $slackUser?->external_user_id
            ? '<@'.$slackUser->external_user_id.'>'
            : $response->user->name;
        $lines = [
            'Here is the report from '.$memberLabel,
            '',
        ];

        foreach ($response->answers->sortBy(fn ($answer) => $answer->question->sort_order) as $answer) {
            $lines[] = '*'.$this->slackQuestionLabel($answer->question).'*';
            $lines[] = $answer->answer_text ?: '-';
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param  Collection<int, StandupResponse>  $pending
     * @param  Collection<int, IntegrationUser>  $integrationUsers
     */
    private function renderSlackPendingResponses(Collection $pending, Collection $integrationUsers): string
    {
        $lines = ['*Pending standup responses*'];

        foreach ($pending as $response) {
            $slackUser = $integrationUsers->get($response->user_id);
            $memberLabel = $slackUser?->external_user_id
                ? '<@'.$slackUser->external_user_id.'>'
                : $response->user?->name;
            $lines[] = '- '.$memberLabel;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, StandupResponse>  $skipped
     * @param  Collection<int, IntegrationUser>  $integrationUsers
     */
    private function renderSlackSkippedResponses(Collection $skipped, Collection $integrationUsers): string
    {
        $lines = ['*Skipped standup responses*'];

        foreach ($skipped as $response) {
            $slackUser = $integrationUsers->get($response->user_id);
            $memberLabel = $slackUser?->external_user_id
                ? '<@'.$slackUser->external_user_id.'>'
                : $response->user?->name;
            $lines[] = '- '.$memberLabel;
        }

        return implode("\n", $lines);
    }

    private function slackQuestionLabel(StandupQuestion $question): string
    {
        return rtrim($question->question_text, " \t\n\r\0\x0B?");
    }

    private function summaryQuestionLabel(StandupQuestion $question): string
    {
        return match ($question->question_key) {
            'yesterday' => 'Yesterday',
            'today' => 'Today',
            'blocker' => 'Blockers',
            'blocker_detail' => 'Blocker details',
            'ticket' => 'Ticket',
            'mood' => 'Mood',
            default => str($question->question_text)
                ->replaceMatches('/\?$/', '')
                ->ucfirst()
                ->toString(),
        };
    }
}
