<?php

namespace Tests\Feature\DevPulse;

use App\Models\Organization;
use App\Models\StandupRun;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolePageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_open_all_role_controlled_pages(): void
    {
        foreach (['owner', 'admin'] as $role) {
            [$user] = $this->createContext($role, 'manager');

            foreach ($this->allPages() as $route) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }
        }
    }

    public function test_organization_and_workspace_managers_receive_scoped_management_pages(): void
    {
        foreach ([['manager', 'member'], ['member', 'manager']] as [$organizationRole, $workspaceRole]) {
            [$user] = $this->createContext($organizationRole, $workspaceRole);

            foreach ([
                'dashboard',
                'standups.index',
                'templates.index',
                'schedules.index',
                'team-members.index',
                'integrations.slack.index',
                'integrations.discord.index',
                'settings.workspace.edit',
            ] as $route) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }

            foreach (['workspaces.index', 'settings.organization.edit'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }

            $this->actingAs($user)->getJson(route('api.team-members.index'))->assertOk();
            $this->actingAs($user)->getJson(route('api.workspaces.index'))->assertForbidden();
        }
    }

    public function test_member_can_only_open_member_pages(): void
    {
        [$member] = $this->createContext('member', 'member');

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
        $this->actingAs($member)->get(route('standups.index'))->assertOk();
        $this->actingAs($member)->getJson(route('api.standups.index'))->assertOk();

        foreach ([
            'templates.index',
            'schedules.index',
            'team-members.index',
            'workspaces.index',
            'integrations.slack.index',
            'integrations.discord.index',
            'settings.organization.edit',
            'settings.workspace.edit',
        ] as $route) {
            $this->actingAs($member)->get(route($route))->assertForbidden();
        }

        foreach ([
            'api.templates.index',
            'api.schedules.index',
            'api.team-members.index',
            'api.workspaces.index',
        ] as $route) {
            $this->actingAs($member)->getJson(route($route))->assertForbidden();
        }
    }

    public function test_shared_access_props_match_the_current_roles(): void
    {
        [$manager] = $this->createContext('member', 'manager');

        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('access.organizationRole', 'member')
            ->where('access.workspaceRole', 'manager')
            ->where('access.capabilities.manageTemplates', true)
            ->where('access.capabilities.manageTeam', false)
            ->where('access.capabilities.manageWorkspaces', false));
    }

    public function test_role_middleware_protects_settings_and_team_writes(): void
    {
        [$member, $organization, $workspace] = $this->createContext('member', 'member');

        $this->actingAs($member)->patch(route('settings.organization.update'), [
            'name' => 'Unauthorized organization name',
            'timezone' => 'UTC',
        ])->assertForbidden();

        $this->actingAs($member)->patch(route('settings.workspace.update'), [
            'name' => 'Unauthorized workspace name',
            'timezone' => 'UTC',
        ])->assertForbidden();

        $this->actingAs($member)->post(route('team-members.store'), [
            'name' => 'Unauthorized Invite',
            'email' => 'unauthorized@example.test',
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
        ])->assertForbidden();

        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'name' => $organization->name]);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => $workspace->name]);
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
    }

    public function test_users_cannot_open_standup_runs_from_unassigned_workspaces(): void
    {
        [$member, $organization] = $this->createContext('member', 'member');
        $otherWorkspace = Workspace::factory()->for($organization)->create();
        $template = StandupTemplate::factory()->for($organization)->for($otherWorkspace)->create();
        $run = StandupRun::factory()->for($organization)->for($otherWorkspace)->for($template, 'template')->create();

        $this->actingAs($member)->get(route('standups.show', $run))->assertNotFound();
        $this->actingAs($member)->get(route('standup-runs.submit', $run))->assertNotFound();
    }

    public function test_workspace_managers_only_receive_operational_data_for_managed_workspaces(): void
    {
        [$manager, $organization, $managedWorkspace] = $this->createContext('member', 'manager');
        $memberWorkspace = Workspace::factory()->for($organization)->create();
        $memberWorkspace->users()->attach($manager, ['role' => 'member', 'is_active' => true]);

        StandupTemplate::factory()->for($organization)->for($managedWorkspace)->create(['name' => 'Managed template']);
        StandupTemplate::factory()->for($organization)->for($memberWorkspace)->create(['name' => 'Hidden template']);

        $this->assertCount(1, app(CurrentOrganization::class)->manageableWorkspacesForUser($organization, $manager));

        $this->actingAs($manager)
            ->getJson(route('api.templates.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Managed template');
    }

    /** @return list<string> */
    private function allPages(): array
    {
        return [
            'dashboard',
            'standups.index',
            'templates.index',
            'schedules.index',
            'team-members.index',
            'workspaces.index',
            'integrations.slack.index',
            'integrations.discord.index',
            'settings.organization.edit',
            'settings.workspace.edit',
        ];
    }

    /** @return array{User, Organization, Workspace} */
    private function createContext(string $organizationRole, string $workspaceRole): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);

        $organization->users()->attach($user, ['role' => $organizationRole]);
        $workspace->users()->attach($user, ['role' => $workspaceRole, 'is_active' => true]);

        return [$user, $organization, $workspace];
    }
}
