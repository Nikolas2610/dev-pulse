<?php

namespace App\Http\Controllers;

use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Services\ActivityEventService;
use App\Services\CurrentOrganization;
use App\Services\StandupResponseSubmissionService;
use App\Services\StandupRunCompletionService;
use App\Services\StandupSubmissionFinalizer;
use App\Services\WorkItemSuggestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StandupSubmissionController extends Controller
{
    public function create(Request $request, StandupRun $run, CurrentOrganization $currentOrganization, WorkItemSuggestionService $suggestions): Response|RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $run->organization_id === $organization->id, 404);
        abort_unless(
            $currentOrganization->workspacesForUser($organization, $request->user())->contains('id', $run->workspace_id),
            404,
        );

        $response = $run->responses()->where('user_id', $request->user()->id)->first();

        if (! $response || $response->status !== 'pending') {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This standup is no longer pending for you.')]);

            return to_route('standups.show', $run);
        }

        return Inertia::render('standup-runs/submit', [
            'run' => $run->load('workspace', 'template.questions'),
            'suggestedWorkItems' => $suggestions->jiraForUser($request->user(), $run->workspace),
        ]);
    }

    public function store(
        Request $request,
        StandupRun $run,
        CurrentOrganization $currentOrganization,
        StandupResponseSubmissionService $submission,
        StandupSubmissionFinalizer $submissionFinalizer,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $run->organization_id === $organization->id, 404);
        abort_unless(
            $currentOrganization->workspacesForUser($organization, $request->user())->contains('id', $run->workspace_id),
            404,
        );

        if (in_array($run->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'standup_run' => __('This standup is closed.'),
            ]);
        }

        $existingResponse = $run->responses()->where('user_id', $request->user()->id)->first();

        if ($existingResponse && $existingResponse->status !== 'pending') {
            throw ValidationException::withMessages([
                'standup_run' => __('You have already completed this standup.'),
            ]);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string'],
            'work_items' => ['sometimes', 'array'],
            'work_items.*' => ['array', 'max:100'],
            'work_items.*.*' => ['integer'],
        ]);
        $validatedAnswers = is_array($validated['answers'] ?? null) ? $validated['answers'] : [];
        $response = $submission->submit(
            $run,
            $request->user(),
            $validatedAnswers,
            is_array($validated['work_items'] ?? null) ? $validated['work_items'] : [],
            'web',
        );

        $submissionFinalizer->handle($response);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Standup submitted.')]);

        return to_route('standups.show', $run);
    }

    public function skip(
        Request $request,
        StandupRun $run,
        CurrentOrganization $currentOrganization,
        ActivityEventService $events,
        StandupRunCompletionService $completion,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $run->organization_id === $organization->id, 404);
        abort_unless(
            $currentOrganization->workspacesForUser($organization, $request->user())->contains('id', $run->workspace_id),
            404,
        );

        if (in_array($run->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'standup_run' => __('This standup is closed.'),
            ]);
        }

        $existingResponse = $run->responses()->where('user_id', $request->user()->id)->first();

        if ($existingResponse && $existingResponse->status !== 'pending') {
            throw ValidationException::withMessages([
                'standup_run' => __('You have already completed this standup.'),
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $run, $events, $completion, $validated): void {
            $response = StandupResponse::firstOrNew([
                'standup_run_id' => $run->id,
                'user_id' => $request->user()->id,
            ]);

            $response->fill([
                'organization_id' => $run->organization_id,
                'workspace_id' => $run->workspace_id,
                'status' => 'skipped',
                'submitted_at' => now(),
                'source' => 'web',
                'raw_json' => ['skip_reason' => $validated['reason']],
            ])->save();

            $response->answers()->delete();

            $run->loadMissing('organization', 'workspace');

            $events->record(
                $run->organization,
                ActivityEventService::STANDUP_RESPONSE_SKIPPED,
                workspace: $run->workspace,
                user: $request->user(),
                subject: $response,
            );

            $completion->completeIfAllResponsesSubmitted($run);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Standup skipped.')]);

        return to_route('standups.show', $run);
    }
}
