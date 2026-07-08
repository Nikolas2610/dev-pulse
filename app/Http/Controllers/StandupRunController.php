<?php

namespace App\Http\Controllers;

use App\Models\StandupRun;
use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StandupRunController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;

        return Inertia::render('standups/index', [
            'runs' => StandupRun::query()
                ->with('workspace', 'template')
                ->where('organization_id', $organization?->id)
                ->when($workspace, fn ($query) => $query->where('workspace_id', $workspace->id))
                ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
                ->when($request->date('date'), fn ($query, $date) => $query->whereDate('run_date', $date))
                ->latest('run_date')
                ->get(),
        ]);
    }

    public function show(Request $request, StandupRun $run, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $run->organization_id === $organization->id, 404);

        $run->load([
            'workspace',
            'template.questions',
            'responses.user',
            'responses.answers.question',
            'summary',
        ]);

        return Inertia::render('standups/show', [
            'run' => $run,
            'members' => $run->responses
                ->map(fn ($response) => $response->user)
                ->filter()
                ->sortBy('name')
                ->values(),
        ]);
    }
}
