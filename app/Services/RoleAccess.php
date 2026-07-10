<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;

class RoleAccess
{
    public const VIEW_DASHBOARD = 'viewDashboard';

    public const VIEW_STANDUPS = 'viewStandups';

    public const MANAGE_TEMPLATES = 'manageTemplates';

    public const MANAGE_SCHEDULES = 'manageSchedules';

    public const MANAGE_INTEGRATIONS = 'manageIntegrations';

    public const VIEW_TEAM = 'viewTeam';

    public const MANAGE_TEAM = 'manageTeam';

    public const MANAGE_WORKSPACES = 'manageWorkspaces';

    public const MANAGE_ORGANIZATION = 'manageOrganization';

    public const MANAGE_WORKSPACE = 'manageWorkspace';

    /** @return array<string, bool> */
    public function capabilities(?Organization $organization, ?Workspace $workspace, ?User $user): array
    {
        $organizationRole = $organization && $user
            ? $this->organizationRole($organization, $user)
            : null;
        $workspaceRole = $workspace && $user
            ? $this->workspaceRole($workspace, $user)
            : null;
        $isOrganizationManager = in_array($organizationRole, ['owner', 'admin'], true);
        $isOperationalManager = $isOrganizationManager
            || ($this->hasActiveWorkspaceAccess($workspace, $user)
                && ($organizationRole === 'manager' || $workspaceRole === 'manager'));
        $hasWorkspaceAccess = $isOrganizationManager || $this->hasActiveWorkspaceAccess($workspace, $user);

        return [
            self::VIEW_DASHBOARD => $user !== null,
            self::VIEW_STANDUPS => $organizationRole !== null && ($workspace === null || $hasWorkspaceAccess),
            self::MANAGE_TEMPLATES => $isOperationalManager,
            self::MANAGE_SCHEDULES => $isOperationalManager,
            self::MANAGE_INTEGRATIONS => $isOperationalManager,
            self::VIEW_TEAM => $isOrganizationManager || $isOperationalManager,
            self::MANAGE_TEAM => $isOrganizationManager,
            self::MANAGE_WORKSPACES => $isOrganizationManager,
            self::MANAGE_ORGANIZATION => $isOrganizationManager,
            self::MANAGE_WORKSPACE => $isOperationalManager,
        ];
    }

    public function allows(
        string $capability,
        ?Organization $organization,
        ?Workspace $workspace,
        ?User $user,
    ): bool {
        return $this->capabilities($organization, $workspace, $user)[$capability] ?? false;
    }

    public function organizationRole(Organization $organization, User $user): ?string
    {
        return $organization->users()
            ->whereKey($user->id)
            ->value('organization_user.role');
    }

    public function workspaceRole(Workspace $workspace, User $user): ?string
    {
        return $workspace->users()
            ->whereKey($user->id)
            ->wherePivot('is_active', true)
            ->value('workspace_user.role');
    }

    public function canAccessWorkspace(Workspace $workspace, Organization $organization, User $user): bool
    {
        if ($workspace->organization_id !== $organization->id) {
            return false;
        }

        if (in_array($this->organizationRole($organization, $user), ['owner', 'admin'], true)) {
            return true;
        }

        return $this->hasActiveWorkspaceAccess($workspace, $user);
    }

    private function hasActiveWorkspaceAccess(?Workspace $workspace, ?User $user): bool
    {
        if (! $workspace || ! $user) {
            return false;
        }

        return $workspace->users()
            ->whereKey($user->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
