<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\CurrentOrganization;
use App\Support\TimezoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);

        return Inertia::render('workspaces/index', [
            'indexEndpoint' => route('api.workspaces.index'),
            'canManageWorkspaces' => $organization
                ? $currentOrganization->canManageOrganization($organization, $request->user())
                : false,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization, 403);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('workspaces', 'slug')->where('organization_id', $organization->id),
            ],
            'timezone' => TimezoneRules::required(),
            'is_active' => ['boolean'],
        ]);

        $workspace = $organization->workspaces()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'timezone' => TimezoneRules::normalize($validated['timezone']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $workspace->users()->syncWithoutDetaching([
            $request->user()->id => ['role' => 'manager', 'is_active' => true],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace created.')]);

        return to_route('workspaces.index');
    }

    public function update(
        Request $request,
        Workspace $workspace,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $workspace->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('workspaces', 'slug')
                    ->where('organization_id', $organization->id)
                    ->ignore($workspace->id),
            ],
            'timezone' => TimezoneRules::required(),
            'is_active' => ['boolean'],
        ]);

        $workspace->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'timezone' => TimezoneRules::normalize($validated['timezone']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace updated.')]);

        return to_route('workspaces.index');
    }

    public function destroy(
        Request $request,
        Workspace $workspace,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $workspace->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        if ($organization->workspaces()->count() <= 1) {
            return back()->withErrors([
                'workspace' => __('An organization must keep at least one workspace.'),
            ]);
        }

        $workspace->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace deleted.')]);

        return to_route('workspaces.index');
    }
}
