<?php

namespace App\Http\Controllers;

use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Services\ActivityEventService;
use App\Services\CurrentOrganization;
use App\Services\StandupQuestionFlowService;
use App\Services\StandupRunCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StandupSubmissionController extends Controller
{
    public function create(Request $request, StandupRun $run, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $run->organization_id === $organization->id, 404);

        return Inertia::render('standup-runs/submit', [
            'run' => $run->load('workspace', 'template.questions'),
        ]);
    }

    public function store(
        Request $request,
        StandupRun $run,
        CurrentOrganization $currentOrganization,
        ActivityEventService $events,
        StandupQuestionFlowService $questionFlow,
        StandupRunCompletionService $completion,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $run->organization_id === $organization->id, 404);

        $existingResponse = $run->responses()->where('user_id', $request->user()->id)->first();

        if ($existingResponse && $existingResponse->status !== 'pending') {
            throw ValidationException::withMessages([
                'standup_run' => __('You have already submitted this standup.'),
            ]);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string'],
        ]);

        $run->loadMissing('template.questions', 'organization', 'workspace');
        $answersByQuestion = collect($validated['answers'])
            ->mapWithKeys(fn ($answer, $questionId) => [(int) $questionId => $answer])
            ->all();
        $visibleQuestions = $questionFlow->visibleQuestions(
            $questionFlow->orderedQuestions($run),
            $answersByQuestion,
        );

        $missingRequired = $visibleQuestions->first(fn ($question) => $question->is_required
            && blank($answersByQuestion[$question->id] ?? null));

        if ($missingRequired) {
            throw ValidationException::withMessages([
                "answers.{$missingRequired->id}" => __('Please answer this required question.'),
            ]);
        }

        DB::transaction(function () use ($request, $run, $validated, $events, $visibleQuestions, $completion): void {
            $response = StandupResponse::firstOrNew([
                'standup_run_id' => $run->id,
                'user_id' => $request->user()->id,
            ]);

            $response->fill([
                'organization_id' => $run->organization_id,
                'workspace_id' => $run->workspace_id,
                'status' => 'submitted',
                'submitted_at' => now(),
                'source' => 'web',
            ])->save();

            $response->answers()->delete();

            foreach ($visibleQuestions as $question) {
                $response->answers()->create([
                    'standup_question_id' => $question->id,
                    'answer_text' => $validated['answers'][$question->id] ?? null,
                ]);
            }

            $events->record(
                $run->organization,
                ActivityEventService::STANDUP_RESPONSE_SUBMITTED,
                workspace: $run->workspace,
                user: $request->user(),
                subject: $response,
            );

            $completion->completeIfAllResponsesSubmitted($run);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Standup submitted.')]);

        return to_route('standups.show', $run);
    }
}
