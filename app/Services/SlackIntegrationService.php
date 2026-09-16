<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\IntegrationChannel;
use App\Models\IntegrationUser;
use App\Models\StandupQuestion;
use App\Models\StandupRun;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SlackIntegrationService
{
    public function __construct(
        private readonly ActivityEventService $events,
        private readonly WorkItemSuggestionService $workItemSuggestions,
    ) {}

    public function syncUsers(Integration $integration): int
    {
        try {
            Log::info('Slack user sync started', [
                'integration_id' => $integration->id,
                'organization_id' => $integration->organization_id,
                'workspace_id' => $integration->workspace_id,
                'has_bot_token' => filled($integration->bot_token),
                'scopes' => $integration->scopes_json ?? [],
            ]);

            $payload = Http::withToken($integration->bot_token)
                ->get('https://slack.com/api/users.list')
                ->throw()
                ->json();

            $this->ensureSlackOk($payload, 'users.list', $integration);

            $synced = 0;
            $matched = 0;
            $unmatched = 0;
            $skipped = 0;
            $members = $payload['members'] ?? [];

            foreach ($members as $member) {
                if (($member['deleted'] ?? false) || ($member['is_bot'] ?? false)) {
                    $skipped++;

                    continue;
                }

                $profile = $member['profile'] ?? [];
                $email = $profile['email'] ?? null;
                $user = $email
                    ? User::query()
                        ->where('email', $email)
                        ->whereHas('organizations', fn ($query) => $query->whereKey($integration->organization_id))
                        ->first()
                    : null;

                if ($user) {
                    $matched++;
                } else {
                    $unmatched++;
                }

                IntegrationUser::updateOrCreate(
                    ['integration_id' => $integration->id, 'external_user_id' => $member['id']],
                    [
                        'user_id' => $user?->id,
                        'provider' => 'slack',
                        'external_username' => $member['name'] ?? null,
                        'external_email' => $email,
                        'display_name' => $profile['real_name'] ?? $member['real_name'] ?? $member['name'] ?? null,
                        'avatar_url' => $profile['image_72'] ?? null,
                        'raw_json' => $member,
                    ],
                );

                $this->events->record(
                    $integration->organization,
                    ActivityEventService::SLACK_USER_SYNCED,
                    source: 'slack',
                    workspace: $integration->workspace,
                    externalId: $member['id'],
                    metadata: ['email' => $email],
                );

                $synced++;
            }

            Log::info('Slack user sync completed', [
                'integration_id' => $integration->id,
                'members_received' => count($members),
                'users_synced' => $synced,
                'users_matched' => $matched,
                'users_unmatched' => $unmatched,
                'users_skipped' => $skipped,
            ]);

            return $synced;
        } catch (Throwable $exception) {
            Log::error('Slack user sync failed', [
                'integration_id' => $integration->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function syncChannels(Integration $integration): int
    {
        Log::info('Slack channel sync started', [
            'integration_id' => $integration->id,
            'organization_id' => $integration->organization_id,
            'workspace_id' => $integration->workspace_id,
            'has_bot_token' => filled($integration->bot_token),
            'scopes' => $integration->scopes_json ?? [],
        ]);

        $payload = Http::withToken($integration->bot_token)
            ->get('https://slack.com/api/conversations.list', [
                'types' => 'public_channel,private_channel',
                'exclude_archived' => true,
            ])
            ->throw()
            ->json();

        $this->ensureSlackOk($payload, 'conversations.list', $integration);

        $synced = 0;
        $channels = $payload['channels'] ?? [];

        foreach ($channels as $channel) {
            IntegrationChannel::updateOrCreate(
                ['integration_id' => $integration->id, 'external_channel_id' => $channel['id']],
                [
                    'name' => $channel['name'] ?? $channel['id'],
                    'type' => ($channel['is_private'] ?? false) ? 'private' : 'public',
                    'raw_json' => $channel,
                    'is_active' => ! ($channel['is_archived'] ?? false),
                ],
            );

            $this->events->record(
                $integration->organization,
                ActivityEventService::SLACK_CHANNEL_SYNCED,
                source: 'slack',
                workspace: $integration->workspace,
                externalId: $channel['id'],
            );

            $synced++;
        }

        Log::info('Slack channel sync completed', [
            'integration_id' => $integration->id,
            'channels_received' => count($channels),
            'channels_synced' => $synced,
        ]);

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureSlackOk(array $payload, string $method, Integration $integration): void
    {
        if (($payload['ok'] ?? false) === true) {
            return;
        }

        $error = (string) ($payload['error'] ?? 'unknown_error');

        Log::error('Slack user sync API error', [
            'integration_id' => $integration->id,
            'error' => $error,
            'needed' => $payload['needed'] ?? null,
            'provided' => $payload['provided'] ?? null,
        ]);

        throw new RuntimeException("Slack API {$method} failed: {$error}");
    }

    public function postMessage(Integration $integration, string $channel, string $text, ?string $clientMessageId = null): ?string
    {
        $message = array_filter([
            'channel' => $channel,
            'text' => $text,
            'client_msg_id' => $clientMessageId,
        ], fn ($value): bool => $value !== null);
        $payload = $this->postSlackMessage($integration, $message);

        if (($payload['ok'] ?? false) !== true && ($payload['error'] ?? null) === 'not_in_channel') {
            $this->joinChannel($integration, $channel);

            $payload = $this->postSlackMessage($integration, $message);
        }

        $this->ensureSlackOk($payload, 'chat.postMessage', $integration);

        return $payload['ts'] ?? null;
    }

    public function postStandupPrompt(Integration $integration, string $channel, StandupRun $run, string $text): ?string
    {
        $payload = $this->postSlackApi($integration, 'chat.postMessage', [
            'channel' => $channel,
            'text' => $text,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '*Your standup is ready*'.PHP_EOL.$text.PHP_EOL.'Are you joining today, or do you need to skip?',
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Join standup',
                            ],
                            'style' => 'primary',
                            'action_id' => 'join_standup',
                            'value' => (string) $run->id,
                        ],
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'Skip'],
                            'action_id' => 'skip_standup',
                            'value' => (string) $run->id,
                        ],
                    ],
                ],
            ],
        ]);

        $this->ensureSlackOk($payload, 'chat.postMessage', $integration);

        return $payload['ts'] ?? null;
    }

    public function openStandupModal(
        Integration $integration,
        string $triggerId,
        StandupRun $run,
        ?string $channel = null,
        ?string $messageTs = null,
        ?User $user = null,
    ): void {
        $run->loadMissing('workspace', 'template.questions');
        $workItems = $user
            ? $this->workItemSuggestions->jiraForUser($user, $run->workspace)
            : collect();
        $blocks = $run->template->questions
            ->sortBy('sort_order')
            ->values()
            ->flatMap(function ($question) use ($workItems): array {
                $questionBlocks = [[
                    'type' => 'input',
                    'block_id' => 'question_'.$question->id,
                    'optional' => ! $question->is_required,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => mb_substr($question->question_text, 0, 150),
                    ],
                    'element' => $this->standupQuestionInputElement($question),
                ]];

                if (filled($question->work_item_relation) && $workItems->isNotEmpty()) {
                    $questionBlocks[] = [
                        'type' => 'input',
                        'block_id' => 'work_items_'.$question->id,
                        'optional' => true,
                        'label' => [
                            'type' => 'plain_text',
                            'text' => 'Jira tickets',
                        ],
                        'element' => [
                            'type' => 'multi_static_select',
                            'action_id' => 'selected_items',
                            'placeholder' => [
                                'type' => 'plain_text',
                                'text' => 'Select Jira tickets',
                            ],
                            'options' => $workItems->take(100)->map(fn ($item) => [
                                'text' => [
                                    'type' => 'plain_text',
                                    'text' => mb_substr(trim(($item->external_key ? $item->external_key.' · ' : '').$item->title), 0, 75),
                                ],
                                'description' => [
                                    'type' => 'plain_text',
                                    'text' => mb_substr(trim(($item->jiraProject?->name ? $item->jiraProject->name.' · ' : '').$item->status), 0, 75),
                                ],
                                'value' => (string) $item->id,
                            ])->values()->all(),
                        ],
                    ];
                }

                return $questionBlocks;
            })
            ->all();

        $payload = $this->postSlackApi($integration, 'views.open', [
            'trigger_id' => $triggerId,
            'view' => [
                'type' => 'modal',
                'callback_id' => 'devpulse_standup_submission',
                'private_metadata' => json_encode([
                    'run_id' => $run->id,
                    'integration_id' => $integration->id,
                    'mode' => 'submit',
                    'channel_id' => $channel,
                    'message_ts' => $messageTs,
                ]),
                'title' => [
                    'type' => 'plain_text',
                    'text' => 'Daily standup',
                ],
                'submit' => [
                    'type' => 'plain_text',
                    'text' => 'Submit',
                ],
                'close' => [
                    'type' => 'plain_text',
                    'text' => 'Cancel',
                ],
                'blocks' => $blocks,
            ],
        ]);

        $this->ensureSlackOk($payload, 'views.open', $integration);
    }

    public function openSkipStandupModal(
        Integration $integration,
        string $triggerId,
        StandupRun $run,
        ?string $channel = null,
        ?string $messageTs = null,
    ): void {
        $payload = $this->postSlackApi($integration, 'views.open', [
            'trigger_id' => $triggerId,
            'view' => [
                'type' => 'modal',
                'callback_id' => 'devpulse_standup_skip',
                'private_metadata' => json_encode([
                    'run_id' => $run->id,
                    'integration_id' => $integration->id,
                    'mode' => 'skip',
                    'channel_id' => $channel,
                    'message_ts' => $messageTs,
                ]),
                'title' => ['type' => 'plain_text', 'text' => 'Skip standup'],
                'submit' => ['type' => 'plain_text', 'text' => 'Confirm skip'],
                'close' => ['type' => 'plain_text', 'text' => 'Go back'],
                'blocks' => [[
                    'type' => 'input',
                    'block_id' => 'skip_reason',
                    'optional' => true,
                    'label' => ['type' => 'plain_text', 'text' => 'Reason'],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'reason',
                        'multiline' => true,
                        'placeholder' => ['type' => 'plain_text', 'text' => 'For example: out sick, on leave, or no update today'],
                    ],
                ]],
            ],
        ]);

        $this->ensureSlackOk($payload, 'views.open', $integration);
    }

    public function updateStandupPromptAsSkipped(Integration $integration, string $channel, string $messageTs): void
    {
        $this->updateStandupPrompt($integration, $channel, $messageTs, 'You chose to skip this standup.');
    }

    public function updateStandupPromptAsSubmitted(Integration $integration, string $channel, string $messageTs): void
    {
        $this->updateStandupPrompt($integration, $channel, $messageTs, 'You submitted this standup.');
    }

    private function updateStandupPrompt(Integration $integration, string $channel, string $messageTs, string $text): void
    {
        if ($channel === '' || $messageTs === '') {
            return;
        }

        $payload = $this->postSlackApi($integration, 'chat.update', [
            'channel' => $channel,
            'ts' => $messageTs,
            'text' => $text,
            'blocks' => [[
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $text,
                ],
            ]],
        ]);

        $this->ensureSlackOk($payload, 'chat.update', $integration);
    }

    public function postStandupQuestion(
        Integration $integration,
        string $channel,
        StandupRun $run,
        StandupQuestion $question,
        int $questionNumber,
        int $totalQuestions,
    ): ?string {
        $payload = $this->postSlackApi($integration, 'chat.postMessage', [
            'channel' => $channel,
            'text' => "Daily standup question {$questionNumber}/{$totalQuestions}: {$question->question_text}",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Daily standup question {$questionNumber}/{$totalQuestions}*",
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'question_'.$question->id,
                    'optional' => ! $question->is_required,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => mb_substr($question->question_text, 0, 150),
                    ],
                    'element' => $this->standupQuestionInputElement($question),
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Submit answer',
                            ],
                            'style' => 'primary',
                            'action_id' => 'submit_standup_answer',
                            'value' => json_encode([
                                'run_id' => $run->id,
                                'question_id' => $question->id,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $this->ensureSlackOk($payload, 'chat.postMessage', $integration);

        return $payload['ts'] ?? null;
    }

    public function updateStandupQuestionAsAnswered(
        Integration $integration,
        string $channel,
        string $messageTs,
        StandupQuestion $question,
        ?string $answer,
        int $questionNumber,
        int $totalQuestions,
    ): void {
        if ($channel === '' || $messageTs === '') {
            return;
        }

        $answerText = filled($answer) ? (string) $answer : '-';

        $payload = $this->postSlackApi($integration, 'chat.update', [
            'channel' => $channel,
            'ts' => $messageTs,
            'text' => "Answered daily standup question {$questionNumber}/{$totalQuestions}: {$question->question_text}",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Daily standup question {$questionNumber}/{$totalQuestions}*",
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '*'.$this->escapeSlackText($question->question_text).'*'.PHP_EOL.$this->escapeSlackText($answerText),
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => 'Submitted',
                        ],
                    ],
                ],
            ],
        ]);

        $this->ensureSlackOk($payload, 'chat.update', $integration);
    }

    public function sendDirectMessage(
        Integration $integration,
        string $externalUserId,
        string $text,
        ?string $clientMessageId = null,
    ): ?string {
        $conversation = $this->postSlackApi($integration, 'conversations.open', [
            'users' => $externalUserId,
        ]);

        $this->ensureSlackOk($conversation, 'conversations.open', $integration);

        $channel = $conversation['channel']['id'] ?? null;

        if (! $channel) {
            return null;
        }

        return $this->postMessage($integration, $channel, $text, $clientMessageId);
    }

    public function sendDirectStandupPrompt(Integration $integration, string $externalUserId, StandupRun $run, string $text): ?string
    {
        $conversation = $this->postSlackApi($integration, 'conversations.open', [
            'users' => $externalUserId,
        ]);

        $this->ensureSlackOk($conversation, 'conversations.open', $integration);

        $channel = $conversation['channel']['id'] ?? null;

        if (! $channel) {
            return null;
        }

        return $this->postStandupPrompt($integration, $channel, $run, $text);
    }

    public function sendDirectStandupQuestion(
        Integration $integration,
        string $externalUserId,
        StandupRun $run,
        StandupQuestion $question,
        int $questionNumber,
        int $totalQuestions,
    ): ?string {
        $conversation = $this->postSlackApi($integration, 'conversations.open', [
            'users' => $externalUserId,
        ]);

        $this->ensureSlackOk($conversation, 'conversations.open', $integration);

        $channel = $conversation['channel']['id'] ?? null;

        if (! $channel) {
            return null;
        }

        return $this->postStandupQuestion($integration, $channel, $run, $question, $questionNumber, $totalQuestions);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postSlackMessage(Integration $integration, array $payload): array
    {
        return $this->postSlackApi($integration, 'chat.postMessage', $payload);
    }

    private function escapeSlackText(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    private function joinChannel(Integration $integration, string $channel): void
    {
        Log::info('Slack bot attempting to join channel before posting', [
            'integration_id' => $integration->id,
            'channel' => $channel,
        ]);

        $payload = $this->postSlackApi($integration, 'conversations.join', [
            'channel' => $channel,
        ]);

        $this->ensureSlackOk($payload, 'conversations.join', $integration);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postSlackApi(Integration $integration, string $method, array $payload): array
    {
        try {
            return Http::withToken($integration->bot_token)
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(2, 500, fn ($exception) => $exception instanceof ConnectionException)
                ->post("https://slack.com/api/{$method}", $payload)
                ->throw()
                ->json();
        } catch (ConnectionException $exception) {
            Log::error('Slack API connection failed', [
                'integration_id' => $integration->id,
                'method' => $method,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException("Slack API {$method} connection failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function standupQuestionInputElement(StandupQuestion $question): array
    {
        if ($question->answer_type === 'boolean') {
            return [
                'type' => 'static_select',
                'action_id' => 'answer',
                'placeholder' => [
                    'type' => 'plain_text',
                    'text' => 'Choose an answer',
                ],
                'options' => $this->slackSelectOptions(['Yes', 'No']),
            ];
        }

        if ($question->answer_type === 'select') {
            return [
                'type' => 'static_select',
                'action_id' => 'answer',
                'placeholder' => [
                    'type' => 'plain_text',
                    'text' => 'Choose an answer',
                ],
                'options' => $this->slackSelectOptions($this->questionOptions($question)),
            ];
        }

        if ($question->answer_type === 'multi_select') {
            return [
                'type' => 'multi_static_select',
                'action_id' => 'answer',
                'placeholder' => [
                    'type' => 'plain_text',
                    'text' => 'Choose answers',
                ],
                'options' => $this->slackSelectOptions($this->questionOptions($question)),
            ];
        }

        return [
            'type' => 'plain_text_input',
            'action_id' => 'answer',
            'multiline' => $question->answer_type !== 'text' && $question->answer_type !== 'number',
        ];
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, array<string, array<string, string>|string>>
     */
    private function slackSelectOptions(array $options): array
    {
        return collect($options)
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->unique()
            ->take(100)
            ->map(fn ($option) => [
                'text' => [
                    'type' => 'plain_text',
                    'text' => mb_substr($option, 0, 75),
                ],
                'value' => mb_substr($option, 0, 75),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function questionOptions(StandupQuestion $question): array
    {
        $options = $question->getAttribute('options_json');

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        return collect(is_array($options) ? $options : [])
            ->map(fn ($option): string => (string) $option)
            ->all();
    }
}
