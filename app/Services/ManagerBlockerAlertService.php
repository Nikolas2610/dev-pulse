<?php

namespace App\Services;

use App\Models\IntegrationUser;
use App\Models\ManagerBlockerNotification;
use App\Models\StandupResponse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ManagerBlockerAlertService
{
    public function __construct(private readonly SlackIntegrationService $slack) {}

    public function hasAffirmativeBlocker(StandupResponse $response): bool
    {
        $response->loadMissing('answers.question');

        return $response->answers->contains(function ($answer): bool {
            if ($answer->question?->question_key !== 'blocker') {
                return false;
            }

            return in_array(Str::lower(trim((string) $answer->answer_text)), ['yes', 'true', '1'], true);
        });
    }

    public function sendForResponse(int $standupResponseId): void
    {
        $response = StandupResponse::query()
            ->with([
                'user',
                'answers.question',
                'run.workspace',
                'run.schedule.targetIntegration',
            ])
            ->findOrFail($standupResponseId);
        $schedule = $response->run->schedule;
        $integration = $schedule?->targetIntegration;

        if (! $schedule?->notify_manager_on_blocker || ! $this->hasAffirmativeBlocker($response)) {
            return;
        }

        if (! $integration || $integration->provider !== 'slack' || ! $integration->is_active) {
            $this->logSkip($response, 'No active Slack integration is configured.');

            return;
        }

        $manager = $this->assignedManager($response);

        if (! $manager) {
            $this->logSkip($response, 'No active workspace manager is assigned.');

            return;
        }

        $notification = ManagerBlockerNotification::firstOrCreate(
            [
                'standup_response_id' => $response->id,
                'manager_user_id' => $manager->id,
                'integration_id' => $integration->id,
            ],
            [
                'client_message_id' => (string) Str::uuid(),
                'status' => 'pending',
            ],
        );

        if ($notification->status === 'sent') {
            return;
        }

        $integrationUser = IntegrationUser::query()
            ->where('integration_id', $integration->id)
            ->where('user_id', $manager->id)
            ->first();

        if (! $integrationUser?->external_user_id) {
            $notification->update([
                'status' => 'skipped',
                'attempted_at' => now(),
                'failure_reason' => 'The assigned manager is not linked to this Slack integration.',
            ]);
            $this->logSkip($response, $notification->failure_reason, $manager->id);

            return;
        }

        $notification->update([
            'status' => 'sending',
            'attempted_at' => now(),
            'failure_reason' => null,
        ]);

        try {
            $externalMessageId = $this->slack->sendDirectMessage(
                $integration,
                $integrationUser->external_user_id,
                $this->renderMessage($response),
                $notification->client_message_id,
            );

            $notification->update([
                'status' => 'sent',
                'external_message_id' => $externalMessageId,
                'sent_at' => now(),
                'failure_reason' => null,
            ]);
        } catch (Throwable $exception) {
            $notification->update([
                'status' => 'failed',
                'failure_reason' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            Log::error('Manager blocker Slack alert failed', [
                'standup_response_id' => $response->id,
                'manager_user_id' => $manager->id,
                'integration_id' => $integration->id,
                'message' => $exception->getMessage(),
            ]);

        }
    }

    private function assignedManager(StandupResponse $response): ?User
    {
        $managerUserId = DB::table('workspace_user')
            ->where('workspace_id', $response->workspace_id)
            ->where('user_id', $response->user_id)
            ->value('manager_user_id');

        if (! $managerUserId || (int) $managerUserId === $response->user_id) {
            return null;
        }

        return User::query()
            ->join('workspace_user', 'workspace_user.user_id', '=', 'users.id')
            ->where('users.id', $managerUserId)
            ->where('users.is_active', true)
            ->where('workspace_user.workspace_id', $response->workspace_id)
            ->where('workspace_user.role', 'manager')
            ->where('workspace_user.is_active', true)
            ->select('users.*')
            ->first();
    }

    private function renderMessage(StandupResponse $response): string
    {
        /** @var Collection<int, mixed> $blockerAnswers */
        $blockerAnswers = $response->answers
            ->filter(fn ($answer): bool => in_array($answer->question?->question_key, ['blocker', 'blocker_detail'], true))
            ->filter(fn ($answer): bool => filled($answer->answer_text))
            ->sortBy(fn ($answer) => $answer->question?->sort_order);
        $lines = [
            ':rotating_light: *'.$response->user->name.' reported a blocker*',
            '',
        ];

        foreach ($blockerAnswers as $answer) {
            $label = $answer->question->question_key === 'blocker' ? 'Blocker' : 'Blocker details';
            $lines[] = '*'.$label.':* '.$answer->answer_text;
        }

        $lines[] = '';
        $lines[] = '*Workspace:* '.$response->run->workspace->name;
        $lines[] = '*Standup:* '.$response->run->schedule->name;
        $lines[] = '*Submitted:* '.$response->submitted_at?->toIso8601String();

        return trim(implode(PHP_EOL, $lines));
    }

    private function logSkip(StandupResponse $response, string $reason, ?int $managerUserId = null): void
    {
        Log::warning('Manager blocker Slack alert skipped', [
            'standup_response_id' => $response->id,
            'manager_user_id' => $managerUserId,
            'reason' => $reason,
        ]);
    }
}
