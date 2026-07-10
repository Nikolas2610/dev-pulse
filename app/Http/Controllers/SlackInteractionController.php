<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\StandupAnswer;
use App\Models\StandupQuestion;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Services\ActivityEventService;
use App\Services\SlackIntegrationService;
use App\Services\StandupQuestionFlowService;
use App\Services\StandupRunCompletionService;
use App\Services\StandupSubmissionFinalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SlackInteractionController extends Controller
{
    public function __construct(private readonly StandupSubmissionFinalizer $submissionFinalizer) {}

    public function __invoke(
        Request $request,
        SlackIntegrationService $slack,
        ActivityEventService $events,
        StandupQuestionFlowService $questionFlow,
        StandupRunCompletionService $completion,
    ): JsonResponse|Response {
        $payloadJson = $request->string('payload')->toString();

        if ($payloadJson === '') {
            parse_str($request->getContent(), $parsedBody);
            $payloadJson = (string) ($parsedBody['payload'] ?? '');
        }

        $payload = json_decode($payloadJson, true);

        abort_unless(is_array($payload), 400);

        if (($payload['type'] ?? null) === 'block_actions') {
            $action = $payload['actions'][0] ?? [];
            $actionId = $action['action_id'] ?? null;

            if ($actionId === 'submit_standup_answer') {
                return $this->storeMessageAnswer($request, $payload, $action, $slack, $events, $questionFlow, $completion);
            }

            abort_unless($actionId === 'submit_standup', 400);

            $run = StandupRun::query()
                ->with('schedule.targetIntegration', 'template.questions', 'workspace')
                ->findOrFail((int) ($action['value'] ?? 0));
            $integration = $run->schedule?->targetIntegration;

            abort_unless($integration instanceof Integration && $integration->provider === 'slack', 404);
            $this->verifySignature($request, $integration);

            $slack->openStandupModal($integration, (string) ($payload['trigger_id'] ?? ''), $run);

            return response()->json();
        }

        if (($payload['type'] ?? null) === 'view_submission') {
            $metadata = json_decode($payload['view']['private_metadata'] ?? '{}', true);
            $integration = Integration::query()
                ->whereKey((int) ($metadata['integration_id'] ?? 0))
                ->where('provider', 'slack')
                ->firstOrFail();
            $this->verifySignature($request, $integration);

            $run = StandupRun::query()
                ->with('organization', 'workspace', 'template.questions')
                ->findOrFail((int) ($metadata['run_id'] ?? 0));
            if ($run->status === 'completed') {
                return $this->modalErrors($run, __('This standup is closed. Please answer the latest standup prompt.'));
            }
            $slackUserId = $payload['user']['id'] ?? null;
            $integrationUser = $slackUserId
                ? IntegrationUser::query()
                    ->where('integration_id', $integration->id)
                    ->where('external_user_id', $slackUserId)
                    ->whereNotNull('user_id')
                    ->first()
                : null;

            if (! $integrationUser) {
                return $this->modalErrors($run, __('Your Slack account is not linked to a DevPulse user. Ask an admin to sync Slack users.'));
            }

            $response = $run->responses()->updateOrCreate(
                ['user_id' => $integrationUser->user_id],
                [
                    'organization_id' => $run->organization_id,
                    'workspace_id' => $run->workspace_id,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                    'source' => 'slack',
                    'external_message_id' => $payload['view']['id'] ?? null,
                    'raw_json' => $payload,
                ],
            );

            $values = $payload['view']['state']['values'] ?? [];

            foreach ($run->template->questions as $question) {
                $answer = $this->extractSlackAnswerValue($values['question_'.$question->id]['answer'] ?? []);

                $response->answers()->updateOrCreate(
                    ['standup_question_id' => $question->id],
                    ['answer_text' => $answer],
                );
            }

            $events->record(
                $run->organization,
                ActivityEventService::STANDUP_RESPONSE_SUBMITTED,
                source: 'slack',
                workspace: $run->workspace,
                user: $integrationUser->user,
                subject: $response,
                externalId: $payload['view']['id'] ?? null,
            );

            $completion->completeIfAllResponsesSubmitted($run);
            $this->submissionFinalizer->handle($response);

            return response()->json(['response_action' => 'clear']);
        }

        return response()->json();
    }

    public function events(
        Request $request,
        SlackIntegrationService $slack,
        ActivityEventService $events,
        StandupQuestionFlowService $questionFlow,
        StandupRunCompletionService $completion,
    ): JsonResponse {
        $payload = $request->all();
        $integration = $this->integrationForSlackPayload($payload);

        abort_unless($integration instanceof Integration, 404);
        $this->verifySignature($request, $integration);

        if (($payload['type'] ?? null) === 'url_verification') {
            return response()->json([
                'challenge' => $payload['challenge'] ?? '',
            ]);
        }

        if (($payload['type'] ?? null) !== 'event_callback') {
            return response()->json();
        }

        $event = $payload['event'] ?? [];

        if (($event['type'] ?? null) !== 'message' || ($event['channel_type'] ?? null) !== 'im') {
            return response()->json();
        }

        if (isset($event['bot_id'])) {
            return response()->json();
        }

        if (($event['subtype'] ?? null) === 'message_changed') {
            $this->updateEditedSlackAnswer($integration, $event);

            return response()->json();
        }

        if (isset($event['subtype'])) {
            return response()->json();
        }

        $this->storeTextMessageAnswer($integration, $event, $slack, $events, $questionFlow, $completion);

        return response()->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $action
     */
    private function storeMessageAnswer(
        Request $request,
        array $payload,
        array $action,
        SlackIntegrationService $slack,
        ActivityEventService $events,
        StandupQuestionFlowService $questionFlow,
        StandupRunCompletionService $completion,
    ): JsonResponse {
        $actionValue = json_decode((string) ($action['value'] ?? '{}'), true);

        abort_unless(is_array($actionValue), 400);

        $run = StandupRun::query()
            ->with('organization', 'workspace', 'template.questions', 'schedule.targetIntegration')
            ->findOrFail((int) ($actionValue['run_id'] ?? 0));
        $integration = $run->schedule?->targetIntegration;

        abort_unless($integration instanceof Integration && $integration->provider === 'slack', 404);
        $this->verifySignature($request, $integration);

        if ($run->status === 'completed') {
            return $this->closedStandupMessage($payload, $slack, $integration);
        }

        $question = $run->template->questions->firstWhere('id', (int) ($actionValue['question_id'] ?? 0));

        abort_unless($question, 404);

        $slackUserId = $payload['user']['id'] ?? null;
        $integrationUser = $slackUserId
            ? IntegrationUser::query()
                ->where('integration_id', $integration->id)
                ->where('external_user_id', $slackUserId)
                ->whereNotNull('user_id')
                ->first()
            : null;

        abort_unless($integrationUser, 403);

        $answer = $this->extractSlackAnswerValue($payload['state']['values']['question_'.$question->id]['answer'] ?? []);

        if ($question->is_required && blank($answer)) {
            return response()->json([
                'response_type' => 'ephemeral',
                'replace_original' => false,
                'text' => 'Please answer this required standup question.',
            ]);
        }

        $response = $run->responses()->firstOrCreate(
            ['user_id' => $integrationUser->user_id],
            [
                'organization_id' => $run->organization_id,
                'workspace_id' => $run->workspace_id,
                'status' => 'pending',
                'source' => 'slack',
            ],
        );

        if ($response->status !== 'pending') {
            return $this->closedStandupMessage($payload, $slack, $integration);
        }

        $response->load('answers');
        $currentQuestionId = $response->raw_json['current_question_id'] ?? null;
        $shouldAdvance = ! $currentQuestionId || (int) $currentQuestionId === $question->id;

        $response->answers()->updateOrCreate(
            ['standup_question_id' => $question->id],
            [
                'answer_text' => $answer,
                'answer_json' => [
                    'slack_message_ts' => $payload['message']['ts'] ?? null,
                    'slack_channel_id' => $payload['channel']['id'] ?? null,
                ],
            ],
        );

        $questions = $questionFlow->orderedQuestions($run);
        $this->afterResponse(fn () => $slack->updateStandupQuestionAsAnswered(
            $integration,
            (string) ($payload['channel']['id'] ?? ''),
            (string) ($payload['message']['ts'] ?? ''),
            $question,
            $answer,
            $questions->search(fn ($candidate) => $candidate->id === $question->id) + 1,
            $questions->count(),
        ));

        if (! $shouldAdvance) {
            return response()->json([
                'replace_original' => true,
                'text' => 'Answer updated.',
            ]);
        }

        $response->load('answers');
        $nextQuestion = $questionFlow->nextQuestionForResponse($run, $response);

        if ($nextQuestion) {
            $response->forceFill([
                'raw_json' => [
                    ...($response->raw_json ?? []),
                    'current_question_id' => $nextQuestion->id,
                ],
            ])->save();

            $this->afterResponse(fn () => $slack->postStandupQuestion(
                $integration,
                (string) ($payload['channel']['id'] ?? ''),
                $run,
                $nextQuestion,
                $questions->search(fn ($candidate) => $candidate->id === $nextQuestion->id) + 1,
                $questions->count(),
            ));

            return response()->json([
                'replace_original' => true,
                'text' => 'Answer saved.',
            ]);
        }

        $response->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'slack',
            'external_message_id' => $payload['message']['ts'] ?? null,
            'raw_json' => [
                ...($response->raw_json ?? []),
                'current_question_id' => null,
                'completed_via' => 'slack_message',
                'last_payload' => $payload,
            ],
        ])->save();

        $events->record(
            $run->organization,
            ActivityEventService::STANDUP_RESPONSE_SUBMITTED,
            source: 'slack',
            workspace: $run->workspace,
            user: $integrationUser->user,
            subject: $response,
            externalId: $payload['message']['ts'] ?? null,
        );

        $completion->completeIfAllResponsesSubmitted($run);
        $this->submissionFinalizer->handle($response);

        Log::info('Slack standup message flow completed', [
            'standup_run_id' => $run->id,
            'user_id' => $integrationUser->user_id,
            'integration_id' => $integration->id,
        ]);

        return response()->json([
            'response_type' => 'ephemeral',
            'replace_original' => false,
            'text' => 'Standup submitted. Thank you.',
        ]);
    }

    private function verifySignature(Request $request, Integration $integration): void
    {
        $secret = $integration->signing_secret ?: config('services.slack.signing_secret');

        abort_unless(filled($secret), 403);

        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        abort_unless($timestamp && abs(time() - (int) $timestamp) <= 300, 403);

        $expected = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$request->getContent(), $secret);

        abort_unless(hash_equals($expected, $signature), 403);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function integrationForSlackPayload(array $payload): ?Integration
    {
        $teamId = $payload['team_id']
            ?? $payload['team']['id']
            ?? $payload['authorizations'][0]['team_id']
            ?? null;

        if (! $teamId) {
            return null;
        }

        return Integration::query()
            ->where('provider', 'slack')
            ->where('external_id', $teamId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function storeTextMessageAnswer(
        Integration $integration,
        array $event,
        SlackIntegrationService $slack,
        ActivityEventService $events,
        StandupQuestionFlowService $questionFlow,
        StandupRunCompletionService $completion,
    ): void {
        $integrationUser = IntegrationUser::query()
            ->where('integration_id', $integration->id)
            ->where('external_user_id', $event['user'] ?? null)
            ->whereNotNull('user_id')
            ->first();

        if (! $integrationUser) {
            return;
        }

        $response = $this->activeSlackResponseForUser($integration, $integrationUser);

        if (! $response) {
            return;
        }

        $response->loadMissing('run.organization', 'run.workspace', 'run.template.questions', 'answers');

        if ($response->run->status === 'completed') {
            return;
        }

        $questions = $questionFlow->orderedQuestions($response->run);
        $question = $this->currentQuestionForResponse($response, $questions);

        if (! $question) {
            return;
        }

        $response->answers()->updateOrCreate(
            ['standup_question_id' => $question->id],
            [
                'answer_text' => $event['text'] ?? '',
                'answer_json' => [
                    'slack_message_ts' => $event['ts'] ?? null,
                    'slack_channel_id' => $event['channel'] ?? null,
                ],
            ],
        );

        $response->load('answers');
        $nextQuestion = $questionFlow->nextQuestionForResponse($response->run, $response);

        if ($nextQuestion) {
            $slack->postStandupQuestion(
                $integration,
                (string) ($event['channel'] ?? ''),
                $response->run,
                $nextQuestion,
                $questions->search(fn ($candidate) => $candidate->id === $nextQuestion->id) + 1,
                $questions->count(),
            );

            $response->forceFill([
                'raw_json' => [
                    ...($response->raw_json ?? []),
                    'current_question_id' => $nextQuestion->id,
                ],
            ])->save();

            return;
        }

        $response->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'slack',
            'external_message_id' => $event['ts'] ?? null,
            'raw_json' => [
                ...($response->raw_json ?? []),
                'current_question_id' => null,
                'completed_via' => 'slack_message',
            ],
        ])->save();

        $events->record(
            $response->run->organization,
            ActivityEventService::STANDUP_RESPONSE_SUBMITTED,
            source: 'slack',
            workspace: $response->run->workspace,
            user: $integrationUser->user,
            subject: $response,
            externalId: $event['ts'] ?? null,
        );

        $completion->completeIfAllResponsesSubmitted($response->run);
        $this->submissionFinalizer->handle($response);

        $slack->postMessage(
            $integration,
            (string) ($event['channel'] ?? ''),
            'Thank you, your standup has been submitted.',
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function updateEditedSlackAnswer(Integration $integration, array $event): void
    {
        $message = $event['message'] ?? [];
        $previousMessage = $event['previous_message'] ?? [];
        $slackMessageTs = $previousMessage['ts'] ?? $message['ts'] ?? null;

        if (! $slackMessageTs) {
            return;
        }

        $integrationUser = IntegrationUser::query()
            ->where('integration_id', $integration->id)
            ->where('external_user_id', $message['user'] ?? null)
            ->whereNotNull('user_id')
            ->first();

        if (! $integrationUser) {
            return;
        }

        StandupAnswer::query()
            ->where('answer_json->slack_message_ts', $slackMessageTs)
            ->whereHas('response', fn ($query) => $query
                ->where('user_id', $integrationUser->user_id)
                ->whereHas('run.schedule', fn ($scheduleQuery) => $scheduleQuery
                    ->where('target_integration_id', $integration->id)))
            ->first()
            ?->update([
                'answer_text' => $message['text'] ?? '',
                'answer_json' => [
                    'slack_message_ts' => $slackMessageTs,
                    'slack_channel_id' => $event['channel'] ?? null,
                    'edited_at' => $event['event_ts'] ?? null,
                ],
            ]);
    }

    private function activeSlackResponseForUser(Integration $integration, IntegrationUser $integrationUser): ?StandupResponse
    {
        return StandupResponse::query()
            ->with('run.schedule', 'answers')
            ->where('user_id', $integrationUser->user_id)
            ->where('status', 'pending')
            ->whereHas('run', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('schedule', fn ($scheduleQuery) => $scheduleQuery->where('target_integration_id', $integration->id)))
            ->latest('id')
            ->first();
    }

    /**
     * @param  Collection<int, StandupQuestion>  $questions
     */
    private function currentQuestionForResponse(StandupResponse $response, $questions): ?StandupQuestion
    {
        $currentQuestionId = $response->raw_json['current_question_id'] ?? null;

        if ($currentQuestionId) {
            $question = $questions->firstWhere('id', $currentQuestionId);

            if ($question instanceof StandupQuestion) {
                return $question;
            }
        }

        $answeredQuestionIds = $response->answers->pluck('standup_question_id');

        return $questions->first(fn ($question) => ! $answeredQuestionIds->contains($question->id));
    }

    private function modalErrors(StandupRun $run, string $message): JsonResponse
    {
        $firstQuestion = $run->template->questions->sortBy('sort_order')->first();

        return response()->json([
            'response_action' => 'errors',
            'errors' => $firstQuestion
                ? ['question_'.$firstQuestion->id => $message]
                : [],
        ]);
    }

    private function closedStandupMessage(
        ?array $payload = null,
        ?SlackIntegrationService $slack = null,
        ?Integration $integration = null,
    ): JsonResponse {
        return response()->json([
            'replace_original' => true,
            'text' => 'This standup is closed. Please answer the latest standup prompt.',
        ]);
    }

    private function afterResponse(callable $callback): void
    {
        app()->terminating(function () use ($callback): void {
            try {
                $callback();
            } catch (\Throwable $exception) {
                Log::error('Slack standup follow-up send failed after interaction response', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function extractSlackAnswerValue(array $state): ?string
    {
        if (isset($state['selected_options']) && is_array($state['selected_options'])) {
            return collect($state['selected_options'])
                ->pluck('value')
                ->filter()
                ->implode(', ');
        }

        if (isset($state['selected_option']) && is_array($state['selected_option'])) {
            return $state['selected_option']['value'] ?? null;
        }

        return $state['value'] ?? null;
    }
}
