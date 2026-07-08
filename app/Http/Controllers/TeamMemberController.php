<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);

        return Inertia::render('team-members/index', [
            'members' => $organization?->users()->with('workspaces')->orderBy('name')->get() ?? [],
            'workspaces' => $organization?->workspaces()->orderBy('name')->get(['id', 'name']) ?? [],
            'canManageMembers' => $organization
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
            'email' => ['required', 'email', 'max:255'],
            'organization_role' => ['required', 'string', 'in:admin,manager,member'],
            'workspace_id' => ['required', 'integer'],
            'workspace_role' => ['required', 'string', 'in:manager,member'],
        ]);

        $workspace = $organization->workspaces()->whereKey($validated['workspace_id'])->firstOrFail();

        $user = User::firstOrCreate(
            ['email' => Str::lower($validated['email'])],
            [
                'name' => $validated['name'],
                'password' => Hash::make(Str::password(32)),
                'email_verified_at' => now(),
                'current_organization_id' => $organization->id,
                'current_workspace_id' => $workspace->id,
                'timezone' => $workspace->timezone,
                'is_active' => true,
            ],
        );

        if ($user->current_organization_id !== $organization->id || ! $user->current_workspace_id) {
            $user->update([
                'current_organization_id' => $organization->id,
                'current_workspace_id' => $workspace->id,
            ]);
        }

        $organization->users()->syncWithoutDetaching([
            $user->id => ['role' => $validated['organization_role']],
        ]);

        $workspace->users()->syncWithoutDetaching([
            $user->id => ['role' => $validated['workspace_role'], 'is_active' => true],
        ]);

        Password::sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team member invited.')]);

        return to_route('team-members.index');
    }

    public function update(Request $request, User $user, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $organization->users()->whereKey($user->id)->exists(), 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $validated = $request->validate([
            'organization_role' => ['required', 'string', 'in:admin,manager,member'],
            'workspace_id' => ['nullable', 'integer'],
            'workspace_role' => ['nullable', 'string', 'in:manager,member'],
        ]);

        $organization->users()->updateExistingPivot($user->id, ['role' => $validated['organization_role']]);

        if (! empty($validated['workspace_id'])) {
            $workspace = $organization->workspaces()->whereKey($validated['workspace_id'])->firstOrFail();
            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $validated['workspace_role'] ?? 'member', 'is_active' => true],
            ]);
        }

        return to_route('team-members.index');
    }

    public function destroyWorkspace(
        Request $request,
        User $user,
        Workspace $workspace,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $organization->users()->whereKey($user->id)->exists(), 404);
        abort_unless($workspace->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $workspace->users()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed from workspace.')]);

        return to_route('team-members.index');
    }
}
