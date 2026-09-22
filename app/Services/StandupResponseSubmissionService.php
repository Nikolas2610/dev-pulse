<?php

namespace App\Services;

use App\Models\ExternalWorkItem;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StandupResponseSubmissionService
{
    public function __construct(
        private readonly WorkItemSuggestionService $suggestions,
        private readonly StandupQuestionFlowService $questionFlow,
        private readonly ActivityEventService $events,
        private readonly StandupRunCompletionService $completion,
    ) {}

    /**
     * @param  array<int|string, string|null>  $answers
     * @param  array<int|string, array<int, int|string>>  $workItemsByQuestion
     * @param  array<string, mixed>  $raw
     */
    public function submit(
        StandupRun $run,
        User $user,
        array $answers,
        array $workItemsByQuestion,
        string $source,
        ?string $externalMessageId = null,
        array $raw = [],
    ): StandupResponse {
        $run->loadMissing('template.questions', 'organization', 'workspace');
        $answersByQuestion = collect($answers)
            ->mapWithKeys(fn ($answer, $questionId) => [(int) $questionId => $answer])
            ->all();
        $visibleQuestions = $this->questionFlow->visibleQuestions(
            $this->questionFlow->orderedQuestions($run),
            $answersByQuestion,
        );
        $visibleById = $visibleQuestions->keyBy('id');
        $missingRequired = $visibleQuestions->first(fn ($question) => $question->is_required
            && blank($answersByQuestion[$question->id] ?? null));

        if ($missingRequired) {
            throw ValidationException::withMessages([
                "answers.{$missingRequired->id}" => __('Please answer this required question.'),
            ]);
        }

        $submittedItemIds = collect($workItemsByQuestion)
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $allowedItems = $this->suggestions
            ->eligibleByIdsForUser($user, $run->workspace, $submittedItemIds)
            ->keyBy('id');
        $selected = [];
        foreach ($workItemsByQuestion as $questionId => $itemIds) {
            $question = $visibleById->get((int) $questionId);
            $allowedProviders = $question?->work_item_providers ?? [];
            if (! $question || blank($question->work_item_relation) || $allowedProviders === []) {
                throw ValidationException::withMessages([
                    "work_items.{$questionId}" => __('This question cannot accept work items.'),
                ]);
            }

            foreach (array_values(array_unique(array_map('intval', $itemIds))) as $itemId) {
                if (! $allowedItems->has($itemId) || ! in_array($allowedItems->get($itemId)->provider, $allowedProviders, true)) {
                    throw ValidationException::withMessages([
                        "work_items.{$questionId}" => __('One or more selected work items are not available for this question.'),
                    ]);
                }
                $selected[] = [$question, $allowedItems->get($itemId)];
            }
        }

        return DB::transaction(function () use ($run, $user, $source, $externalMessageId, $raw, $visibleQuestions, $answersByQuestion, $selected): StandupResponse {
            $response = StandupResponse::firstOrNew([
                'standup_run_id' => $run->id,
                'user_id' => $user->id,
            ]);
            $response->fill([
                'organization_id' => $run->organization_id,
                'workspace_id' => $run->workspace_id,
                'status' => 'submitted',
                'submitted_at' => now(),
                'source' => $source,
                'external_message_id' => $externalMessageId,
                'raw_json' => $raw,
            ])->save();

            $response->answers()->delete();
            foreach ($visibleQuestions as $question) {
                $response->answers()->create([
                    'standup_question_id' => $question->id,
                    'answer_text' => $answersByQuestion[$question->id] ?? null,
                ]);
            }

            $response->items()->delete();
            foreach ($selected as [$question, $item]) {
                /** @var ExternalWorkItem $item */
                $response->items()->create([
                    'standup_question_id' => $question->id,
                    'external_work_item_id' => $item->id,
                    'relation' => $question->work_item_relation,
                    'provider' => $item->provider,
                    'external_key' => $item->external_key,
                    'title_snapshot' => $item->title,
                    'status_snapshot' => $item->status,
                    'url_snapshot' => $item->url,
                ]);
            }

            $this->events->record(
                $run->organization,
                ActivityEventService::STANDUP_RESPONSE_SUBMITTED,
                source: $source,
                workspace: $run->workspace,
                user: $user,
                subject: $response,
                externalId: $externalMessageId,
            );
            $this->completion->completeIfAllResponsesSubmitted($run);

            return $response;
        });
    }
}
