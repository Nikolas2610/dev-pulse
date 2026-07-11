<?php

namespace App\Http\Controllers;

use App\Models\StandupAnswer;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        $days = in_array((int) $request->integer('days', 30), [30, 60, 90], true)
            ? (int) $request->integer('days', 30)
            : 30;
        $end = now($organization?->timezone ?? config('app.timezone'))->endOfDay();
        $start = $end->copy()->subDays($days - 1)->startOfDay();

        $runs = StandupRun::query()
            ->where('organization_id', $organization?->id)
            ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->when(! $workspace, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereBetween('run_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->orderBy('run_date')
            ->get(['id', 'run_date', 'status']);

        $responses = StandupResponse::query()
            ->with('user:id,name,email')
            ->whereIn('standup_run_id', $runs->pluck('id'))
            ->get(['id', 'standup_run_id', 'user_id', 'status', 'submitted_at']);
        $blockerResponseIds = StandupAnswer::query()
            ->whereIn('standup_response_id', $responses->pluck('id'))
            ->whereHas('question', fn ($query) => $query->where('question_key', 'like', '%blocker%'))
            ->whereNotNull('answer_text')
            ->whereNotIn('answer_text', ['', '-', 'None', 'No blockers'])
            ->pluck('standup_response_id')
            ->unique();

        $runDates = $runs->pluck('run_date', 'id');
        $daily = $responses->groupBy('standup_run_id')->map(function (Collection $items, int $runId) use ($runDates, $blockerResponseIds) {
            $submitted = $items->where('status', 'submitted')->count();

            return [
                'date' => $runDates[$runId]->format('M j'),
                'completion' => $items->count() ? (int) round($submitted / $items->count() * 100) : 0,
                'responses' => $submitted,
                'blockers' => $items->whereIn('id', $blockerResponseIds)->count(),
            ];
        })->values();

        $submitted = $responses->where('status', 'submitted');
        $previousDays = $days;
        $previousStart = $start->copy()->subDays($previousDays);
        $previous = StandupResponse::query()
            ->where('organization_id', $organization?->id)
            ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->when(! $workspace, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereHas('run', fn ($query) => $query->whereBetween('run_date', [$previousStart->toDateString(), $start->copy()->subDay()->toDateString()]))
            ->get(['status']);
        $rate = $responses->count() ? (int) round($submitted->count() / $responses->count() * 100) : 0;
        $previousRate = $previous->count() ? (int) round($previous->where('status', 'submitted')->count() / $previous->count() * 100) : 0;

        $team = $responses->groupBy('user_id')->map(function (Collection $items) use ($blockerResponseIds) {
            $submittedItems = $items->where('status', 'submitted');
            $turnaround = $submittedItems->filter(fn ($item) => $item->submitted_at)->avg(function ($item) {
                $runDate = $item->submitted_at->copy()->startOfDay()->addHours(8)->addMinutes(30);

                return max(0, $runDate->diffInMinutes($item->submitted_at, false));
            });

            return [
                'name' => $items->first()->user?->name ?? 'Unknown member',
                'email' => $items->first()->user?->email,
                'submitted' => $submittedItems->count(),
                'expected' => $items->count(),
                'rate' => $items->count() ? (int) round($submittedItems->count() / $items->count() * 100) : 0,
                'avgMinutes' => (int) round($turnaround ?? 0),
                'blockers' => $items->whereIn('id', $blockerResponseIds)->count(),
            ];
        })->sortByDesc('rate')->values();

        $weekdays = collect(range(1, 5))->map(function (int $day) use ($responses, $runDates) {
            $items = $responses->filter(fn ($response) => $runDates[$response->standup_run_id]?->dayOfWeekIso === $day);

            return [
                'day' => now()->startOfWeek()->addDays($day - 1)->format('D'),
                'rate' => $items->count() ? (int) round($items->where('status', 'submitted')->count() / $items->count() * 100) : 0,
            ];
        });

        return Inertia::render('reports/index', [
            'period' => ['days' => $days, 'start' => $start->format('M j'), 'end' => $end->format('M j, Y')],
            'workspace' => $workspace?->name,
            'metrics' => [
                'completionRate' => $rate,
                'completionChange' => $rate - $previousRate,
                'responses' => $submitted->count(),
                'runs' => $runs->count(),
                'blockers' => $blockerResponseIds->count(),
                'medianMinutes' => (int) round($team->median('avgMinutes') ?? 0),
            ],
            'daily' => $daily,
            'weekdays' => $weekdays,
            'team' => $team,
        ]);
    }
}
