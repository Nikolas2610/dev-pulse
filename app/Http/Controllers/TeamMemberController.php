<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TeamInvitationNotification;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspaces = $organization
            ? $currentOrganization->workspacesForUser($organization, $request->user())
            : collect();

        return Inertia::render('team-members/index', [
            'indexEndpoint' => route('api.team-members.index'),
            'workspaces' => $workspaces->map->only(['id', 'name'])->values(),
            'workspaceManagers' => $workspaces->mapWithKeys(fn (Workspace $workspace) => [
                $workspace->id => $workspace->users()
                    ->wherePivot('role', 'manager')
                    ->wherePivot('is_active', true)
                    ->where('users.is_active', true)
                    ->orderBy('name')
                    ->get(['users.id', 'name'])
                    ->map->only(['id', 'name'])
                    ->values(),
            ]),
            'canManageMembers' => $organization
                ? $currentOrganization->canManageOrganization($organization, $request->user())
                : false,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);

        if (! $organization) {
            abort(403);
        }

        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'organization_role' => ['required', 'string', 'in:admin,manager,member'],
            'workspace_id' => ['required', 'integer'],
            'workspace_role' => ['required', 'string', 'in:manager,member'],
            'manager_user_id' => ['nullable', 'integer'],
        ]);

        $workspace = Workspace::query()
            ->where('organization_id', $organization->id)
            ->whereKey($validated['workspace_id'])
            ->firstOrFail();
        $existingUser = User::query()->where('email', Str::lower($validated['email']))->first();
        $this->validateAssignedManager($workspace, $validated['manager_user_id'] ?? null, $existingUser?->id);

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
            $user->id => [
                'role' => $validated['workspace_role'],
                'is_active' => true,
                'manager_user_id' => $validated['manager_user_id'] ?? null,
            ],
        ]);

        $token = $user->wasRecentlyCreated
            ? Password::createToken($user)
            : null;

        $user->notify(new TeamInvitationNotification(
            organization: $organization,
            workspace: $workspace,
            invitedBy: $request->user(),
            role: $validated['organization_role'],
            token: $token,
        ));

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
            'manager_user_id' => ['nullable', 'integer'],
        ]);

        $organization->users()->updateExistingPivot($user->id, ['role' => $validated['organization_role']]);

        if (! empty($validated['workspace_id'])) {
            $workspace = Workspace::query()
                ->where('organization_id', $organization->id)
                ->whereKey($validated['workspace_id'])
                ->firstOrFail();
            $this->validateAssignedManager($workspace, $validated['manager_user_id'] ?? null, $user->id);
            $workspace->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => $validated['workspace_role'] ?? 'member',
                    'is_active' => true,
                    'manager_user_id' => $validated['manager_user_id'] ?? null,
                ],
            ]);

            if (($validated['workspace_role'] ?? 'member') !== 'manager') {
                $this->clearManagerAssignments($workspace, $user->id);
            }
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

        $this->clearManagerAssignments($workspace, $user->id);
        $workspace->users()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed from workspace.')]);

        return to_route('team-members.index');
    }

    private function validateAssignedManager(Workspace $workspace, ?int $managerUserId, ?int $memberUserId): void
    {
        if (! $managerUserId) {
            return;
        }

        if ($memberUserId && $managerUserId === $memberUserId) {
            throw ValidationException::withMessages([
                'manager_user_id' => __('A member cannot be their own manager.'),
            ]);
        }

        $isEligible = $workspace->users()
            ->whereKey($managerUserId)
            ->wherePivot('role', 'manager')
            ->wherePivot('is_active', true)
            ->where('users.is_active', true)
            ->exists();

        if (! $isEligible) {
            throw ValidationException::withMessages([
                'manager_user_id' => __('Select an active manager from this workspace.'),
            ]);
        }
    }

    private function clearManagerAssignments(Workspace $workspace, int $managerUserId): void
    {
        DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)
            ->where('manager_user_id', $managerUserId)
            ->update(['manager_user_id' => null, 'updated_at' => now()]);
    }
}
