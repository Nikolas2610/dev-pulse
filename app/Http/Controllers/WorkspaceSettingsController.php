<?php

namespace App\Http\Controllers;

use App\Services\CurrentOrganization;
use App\Support\TimezoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSettingsController extends Controller
{
    public function edit(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        abort_unless(
            $organization && $currentOrganization->canManageCurrentWorkspace($organization, $request->user()),
            403,
        );

        return Inertia::render('settings/workspace', [
            'workspace' => $workspace,
        ]);
    }

    public function update(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        abort_unless($workspace !== null, 404);
        abort_unless(
            $currentOrganization->canManageCurrentWorkspace($organization, $request->user()),
            403,
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => TimezoneRules::required(),
        ]);

        $workspace->update([
            ...$validated,
            'timezone' => TimezoneRules::normalize($validated['timezone']),
        ]);

        return to_route('settings.workspace.edit');
    }
}
