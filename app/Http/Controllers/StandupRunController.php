<?php

namespace App\Http\Controllers;

use App\Models\StandupRun;
use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StandupRunController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('standups/index', [
            'indexEndpoint' => route('api.standups.index'),
        ]);
    }

    public function show(Request $request, StandupRun $run, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $run->organization_id === $organization->id, 404);
        abort_unless(
            $currentOrganization->workspacesForUser($organization, $request->user())->contains('id', $run->workspace_id),
            404,
        );

        $run->load([
            'workspace',
            'template.questions',
            'responses.user',
            'responses.answers.question',
            'responses.items',
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
