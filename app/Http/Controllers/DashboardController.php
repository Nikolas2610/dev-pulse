<?php

namespace App\Http\Controllers;

use App\Models\ExternalWorkItem;
use App\Models\StandupAnswer;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Services\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const NON_BLOCKER_ANSWERS = [
        '-',
        'n/a',
        'na',
        'no',
        'no blocker',
        'no blockers',
        'none',
        'nope',
        'not blocked',
        'nothing',
    ];

    public function __invoke(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        $now = now($organization ? $organization->timezone : config('app.timezone'));
        $run = StandupRun::query()
            ->with(['workspace', 'template.questions', 'responses.user', 'responses.answers.question', 'responses.items', 'summary'])
            ->where('organization_id', $organization?->id)
            ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->when(! $workspace, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereDate('run_date', $now->toDateString())
            ->latest()
            ->first();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $weekRuns = StandupRun::query()
            ->where('organization_id', $organization?->id)
            ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->when(! $workspace, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereDate('run_date', '>=', $weekStart->toDateString())
            ->whereDate('run_date', '<=', $weekEnd->toDateString())
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'status']);
        $todayStats = $this->participationStats(collect($run ? [$run->id] : []));
        $weekStats = $this->participationStats($weekRuns->pluck('id'));

        return Inertia::render('dashboard', [
            'todayRun' => $run,
            'latestSummary' => $run?->summary,
            'stats' => [
                'today' => [
                    'label' => $now->format('D, M j'),
                    'hasRun' => $run !== null,
                    'status' => $run?->status,
                    ...$todayStats,
                ],
                'week' => [
                    'label' => $this->weekLabel($weekStart, $weekEnd),
                    'runs' => $weekRuns->count(),
                    'completedRuns' => $weekRuns->where('status', 'completed')->count(),
                    ...$weekStats,
                ],
            ],
            'engineeringWork' => $workspace ? $this->engineeringWork($workspace->id, (int) ($workspace->stale_pr_days ?? 3)) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function engineeringWork(int $workspaceId, int $staleDays): array
    {
        $base = ExternalWorkItem::query()->where('workspace_id', $workspaceId)->open();

        return [
            'jiraOpen' => (clone $base)->where('type', 'jira_issue')->count(),
            'pullRequests' => (clone $base)->where('type', 'pull_request')->count(),
            'blocked' => (clone $base)->where('is_blocked', true)->count(),
            'pendingReviews' => (clone $base)->where('type', 'pull_request')->whereJsonLength('reviewer_external_ids', '>', 0)->count(),
            'stalePullRequests' => (clone $base)->where('type', 'pull_request')->where('provider_updated_at', '<=', now()->subDays($staleDays))->count(),
            'lastSyncedAt' => (clone $base)->max('last_synced_at'),
            'items' => (clone $base)->with(['assignee.user:id,name', 'author.user:id,name'])->orderByDesc('is_blocked')->orderByDesc('provider_updated_at')->limit(12)->get(),
        ];
    }

    /**
     * @param  Collection<int, int>  $runIds
     * @return array{submitted: int, expected: int, pending: int, blockers: int, completionRate: int}
     */
    private function participationStats(Collection $runIds): array
    {
        if ($runIds->isEmpty()) {
            return [
                'submitted' => 0,
                'expected' => 0,
                'pending' => 0,
                'blockers' => 0,
                'completionRate' => 0,
            ];
        }

        $responses = StandupResponse::query()->whereIn('standup_run_id', $runIds);
        $expected = (clone $responses)->count();
        $submitted = (clone $responses)->where('status', 'submitted')->count();
        $skipped = (clone $responses)->where('status', 'skipped')->count();
        $pending = (clone $responses)->where('status', 'pending')->count();
        $blockers = StandupAnswer::query()
            ->whereHas('response', fn ($query) => $query
                ->whereIn('standup_run_id', $runIds)
                ->where('status', 'submitted'))
            ->whereHas('question', fn ($query) => $query->where('question_key', 'like', '%blocker%'))
            ->whereNotNull('answer_text')
            ->where('answer_text', '!=', '')
            ->whereNotIn(DB::raw('LOWER(TRIM(answer_text))'), self::NON_BLOCKER_ANSWERS)
            ->count();

        return [
            'submitted' => $submitted,
            'expected' => $expected,
            'pending' => $pending,
            'blockers' => $blockers,
            'completionRate' => $expected > 0
                ? (int) round((($submitted + $skipped) / $expected) * 100)
                : 0,
        ];
    }

    private function weekLabel(CarbonInterface $start, CarbonInterface $end): string
    {
        return $start->isSameMonth($end)
            ? $start->format('M j').'–'.$end->format('j')
            : $start->format('M j').'–'.$end->format('M j');
    }
}
