<?php

namespace App\Http\Controllers;

use App\Models\StandupResponse;
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamStandupController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:pending,submitted'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $responses = StandupResponse::query()
            ->with([
                'user:id,name,email,avatar_url',
                'run:id,run_date,standup_template_id,status',
                'run.template:id,name',
                'answers:id,standup_response_id,standup_question_id,answer_text',
                'answers.question:id,question_text,question_key,sort_order',
            ])
            ->where('standup_responses.organization_id', $organization?->id)
            ->when($workspace, fn (Builder $query) => $query->where('standup_responses.workspace_id', $workspace->id))
            ->when(! $workspace, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($validated['user'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereHas('run', fn (Builder $runQuery) => $runQuery->whereDate('run_date', '>=', $from)))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereHas('run', fn (Builder $runQuery) => $runQuery->whereDate('run_date', '<=', $to)))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->whereHas('user', fn (Builder $userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('answers', fn (Builder $answerQuery) => $answerQuery->where('answer_text', 'like', "%{$search}%"))
                        ->orWhereHas('run.template', fn (Builder $templateQuery) => $templateQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->join('standup_runs', 'standup_runs.id', '=', 'standup_responses.standup_run_id')
            ->orderByDesc('standup_runs.run_date')
            ->orderBy('standup_responses.user_id')
            ->select('standup_responses.*')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString()
            ->through(fn (StandupResponse $response) => [
                'id' => $response->id,
                'status' => $response->status,
                'submittedAt' => $response->submitted_at?->toIso8601String(),
                'user' => $response->user,
                'run' => [
                    'id' => $response->run->id,
                    'date' => $response->run->run_date->toDateString(),
                    'status' => $response->run->status,
                    'template' => $response->run->template?->name,
                ],
                'answers' => $response->answers
                    ->sortBy(fn ($answer) => $answer->question->sort_order ?? 999)
                    ->map(fn ($answer) => [
                        'key' => $answer->question?->question_key,
                        'question' => $answer->question?->question_text,
                        'answer' => $answer->answer_text,
                    ])->values()->all(),
            ]);

        $members = $workspace
            ? $workspace->users()->wherePivot('is_active', true)->orderBy('name')->get(['users.id', 'name', 'email'])
            : collect();

        return Inertia::render('team-standups/index', [
            'responses' => $responses,
            'members' => $members,
            'filters' => [
                'search' => $search,
                'user' => isset($validated['user']) ? (string) $validated['user'] : '',
                'status' => $validated['status'] ?? '',
                'from' => $validated['from'] ?? '',
                'to' => $validated['to'] ?? '',
                'per_page' => $validated['per_page'] ?? 10,
            ],
            'workspace' => $workspace?->name,
        ]);
    }
}
