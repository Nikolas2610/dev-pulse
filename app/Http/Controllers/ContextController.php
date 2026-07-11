<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function update(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
            'workspace_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $organization = Organization::query()
            ->whereKey($validated['organization_id'])
            ->whereHas('users', fn ($query) => $query->whereKey($user->id))
            ->firstOrFail();

        $availableWorkspaces = $currentOrganization->workspacesForUser($organization, $user);
        $workspace = null;

        if (! empty($validated['workspace_id'])) {
            $workspace = $availableWorkspaces->firstWhere('id', (int) $validated['workspace_id']);
            abort_unless($workspace && $workspace->organization_id === $organization->id, 404);
        } else {
            $workspace = $availableWorkspaces->first();
        }

        $user->update([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace?->id,
        ]);

        return back();
    }
}
