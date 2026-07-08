<?php

namespace App\Http\Controllers;

use App\Models\StandupRun;
use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        $run = StandupRun::query()
            ->with(['workspace', 'template.questions', 'responses.user', 'responses.answers.question', 'summary'])
            ->where('organization_id', $organization?->id)
            ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->whereDate('run_date', now($organization?->timezone ?? config('app.timezone'))->toDateString())
            ->latest()
            ->first();

        return Inertia::render('dashboard', [
            'todayRun' => $run,
            'latestSummary' => $run?->summary,
        ]);
    }
}
