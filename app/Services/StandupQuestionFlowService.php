<?php

namespace App\Services;

use App\Models\StandupAnswer;
use App\Models\StandupQuestion;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use Illuminate\Support\Collection;

class StandupQuestionFlowService
{
    public const DEPENDENCY_OPERATORS = ['equals'];

    /**
     * @return Collection<int, StandupQuestion>
     */
    public function orderedQuestions(StandupRun $run): Collection
    {
        return $run->template->questions->sortBy('sort_order')->values();
    }

    public function nextQuestionForResponse(StandupRun $run, StandupResponse $response): ?StandupQuestion
    {
        $response->loadMissing('answers');
        $answersByQuestion = $this->answersByQuestion($response);
        $answeredQuestionIds = $response->answers->pluck('standup_question_id');

        return $this->orderedQuestions($run)
            ->first(fn (StandupQuestion $question) => ! $answeredQuestionIds->contains($question->id)
                && $this->isVisible($question, $answersByQuestion));
    }

    /**
     * @param  Collection<int, StandupQuestion>  $questions
     * @param  array<int, string|null>  $answersByQuestion
     * @return Collection<int, StandupQuestion>
     */
    public function visibleQuestions(Collection $questions, array $answersByQuestion): Collection
    {
        return $questions
            ->filter(fn (StandupQuestion $question) => $this->isVisible($question, $answersByQuestion))
            ->values();
    }

    /**
     * @param  array<int, string|null>  $answersByQuestion
     */
    public function isVisible(StandupQuestion $question, array $answersByQuestion): bool
    {
        if (! $question->depends_on_question_id) {
            return true;
        }

        $actual = $answersByQuestion[(int) $question->depends_on_question_id] ?? null;

        if ($actual === null) {
            return false;
        }

        return match ($question->depends_on_operator ?? 'equals') {
            'equals' => $this->normalize($actual) === $this->normalize($question->depends_on_value),
            default => false,
        };
    }

    /**
     * @return array<int, string|null>
     */
    public function answersByQuestion(StandupResponse $response): array
    {
        return $response->answers
            ->mapWithKeys(fn (StandupAnswer $answer) => [
                (int) $answer->standup_question_id => $answer->answer_text,
            ])
            ->all();
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
