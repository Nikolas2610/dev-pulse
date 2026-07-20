<?php

namespace App\Http\Controllers;

use App\Models\AutomationExecution;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AutomationHealthController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_if($organization === null, 403);

        $heartbeat = AutomationExecution::query()
            ->where('task', 'scheduler.heartbeat')
            ->where('status', 'succeeded')
            ->latest('started_at')
            ->first();
        $heartbeatAge = $heartbeat?->started_at?->diffInSeconds(now());

        $missingRuns = StandupSchedule::query()
            ->with('workspace:id,name')
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get()
            ->filter(function (StandupSchedule $schedule): bool {
                $today = now($schedule->timezone);

                return in_array($today->dayOfWeekIso, array_map('intval', $schedule->days_of_week ?? []), true)
                    && ! StandupRun::query()
                        ->where('standup_schedule_id', $schedule->id)
                        ->whereDate('run_date', $today->toDateString())
                        ->exists();
            })
            ->map(fn (StandupSchedule $schedule): array => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'workspace' => $schedule->workspace?->name,
                'expected_date' => now($schedule->timezone)->toDateString(),
                'timezone' => $schedule->timezone,
            ])->values();

        $stalledRuns = StandupRun::query()
            ->with('schedule:id,name', 'workspace:id,name')
            ->where('organization_id', $organization->id)
            ->where(function ($query): void {
                $query->where(fn ($query) => $query
                    ->where('status', 'scheduled')
                    ->where(fn ($query) => $query
                        ->whereNull('standup_schedule_id')
                        ->orWhereHas('schedule', fn ($query) => $query->where('is_active', true)))
                    ->where('scheduled_open_at', '<', now()->subMinutes(5)))
                    ->orWhere(fn ($query) => $query
                        ->whereIn('status', ['active', 'completed'])
                        ->whereNull('summary_posted_at')
                        ->where('summary_due_at', '<', now()->subMinutes(5)));
            })
            ->latest('run_date')
            ->limit(20)
            ->get()
            ->map(fn (StandupRun $run): array => [
                'id' => $run->id,
                'name' => $run->schedule?->name,
                'workspace' => $run->workspace?->name,
                'status' => $run->status,
                'scheduled_open_at' => $run->scheduled_open_at?->toIso8601String(),
                'summary_due_at' => $run->summary_due_at?->toIso8601String(),
            ]);

        $recentExecutions = AutomationExecution::query()
            ->where('task', '!=', 'scheduler.heartbeat')
            ->latest('started_at')
            ->limit(30)
            ->get(['id', 'task', 'status', 'started_at', 'finished_at', 'duration_ms', 'metrics_json', 'error']);
        $failedJobs = DB::table('failed_jobs')->latest('failed_at')->limit(20)->get([
            'id', 'uuid', 'connection', 'queue', 'exception', 'failed_at',
        ])->map(fn ($job): array => [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'error' => str($job->exception)->before("\n")->limit(240)->toString(),
            'failed_at' => $job->failed_at,
        ]);

        return Inertia::render('automation-health', [
            'health' => [
                'scheduler' => [
                    'status' => $heartbeatAge !== null && $heartbeatAge <= 180 ? 'healthy' : 'down',
                    'last_seen_at' => $heartbeat?->started_at?->toIso8601String(),
                    'age_seconds' => $heartbeatAge,
                ],
                'queue' => [
                    'status' => $failedJobs->isEmpty() && $stalledRuns->isEmpty() ? 'healthy' : 'attention',
                    'pending_jobs' => DB::table('jobs')->count(),
                    'failed_jobs' => DB::table('failed_jobs')->count(),
                ],
                'missing_runs' => $missingRuns,
                'stalled_runs' => $stalledRuns,
            ],
            'recentExecutions' => $recentExecutions,
            'failedJobs' => $failedJobs,
        ]);
    }
}
