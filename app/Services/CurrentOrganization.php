<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CurrentOrganization
{
    public function fromRequest(Request $request): ?Organization
    {
        $user = $request->user();

        return $user instanceof User ? $this->forUser($user) : null;
    }

    public function forUser(User $user): ?Organization
    {
        if ($user->current_organization_id) {
            return Organization::query()
                ->whereKey($user->current_organization_id)
                ->whereHas('users', fn ($query) => $query->whereKey($user->id))
                ->first();
        }

        return $user->organizations()->first();
    }

    public function roleForUser(Organization $organization, User $user): ?string
    {
        $pivot = $organization->users()
            ->whereKey($user->id)
            ->first()
            ?->pivot;

        if (! $pivot instanceof Pivot) {
            return null;
        }

        $role = $pivot->getAttribute('role');

        return is_string($role) ? $role : null;
    }

    public function canManageOrganization(Organization $organization, User $user): bool
    {
        return in_array($this->roleForUser($organization, $user), ['owner', 'admin'], true);
    }

    public function canManageCurrentWorkspace(Organization $organization, User $user): bool
    {
        $workspace = $this->currentWorkspaceForUser($organization, $user);

        return app(RoleAccess::class)->allows(
            RoleAccess::MANAGE_WORKSPACE,
            $organization,
            $workspace,
            $user,
        );
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function workspacesForUser(Organization $organization, User $user): Collection
    {
        if ($this->canManageOrganization($organization, $user)) {
            return $organization->workspaces()->orderBy('name')->get();
        }

        return $user->workspaces()
            ->where('organization_id', $organization->id)
            ->wherePivot('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function manageableWorkspacesForUser(Organization $organization, User $user): Collection
    {
        $organizationRole = $this->roleForUser($organization, $user);

        if (in_array($organizationRole, ['owner', 'admin'], true)) {
            return $organization->workspaces()->orderBy('name')->get();
        }

        return $user->workspaces()
            ->where('workspaces.organization_id', $organization->id)
            ->where('workspace_user.is_active', true)
            ->when($organizationRole !== 'manager', fn ($query) => $query->where('workspace_user.role', 'manager'))
            ->orderBy('name')
            ->get();
    }

    public function currentWorkspaceForUser(Organization $organization, User $user): ?Workspace
    {
        $workspaces = $this->workspacesForUser($organization, $user);

        if ($user->current_workspace_id) {
            $workspace = $workspaces->firstWhere('id', $user->current_workspace_id);

            if ($workspace instanceof Workspace) {
                return $workspace;
            }
        }

        return $workspaces->first();
    }
}
