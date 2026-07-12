<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamStandupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_managers_can_view_team_standups(): void
    {
        [$user, $organization, $workspace] = $this->userWithRole('member', 'manager');

        $this->actingAs($user)
            ->get(route('team-standups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('team-standups/index')
                ->where('workspace', $workspace->name)
                ->has('responses.data')
                ->has('members')
                ->has('filters'));
    }

    public function test_organization_admins_can_view_team_standups(): void
    {
        [$user] = $this->userWithRole('admin', 'member');

        $this->actingAs($user)->get(route('team-standups.index'))->assertOk();
    }

    public function test_regular_members_cannot_view_team_standups(): void
    {
        [$user] = $this->userWithRole('member', 'member');

        $this->actingAs($user)->get(route('team-standups.index'))->assertForbidden();
    }

    /** @return array{User, Organization, Workspace} */
    private function userWithRole(string $organizationRole, string $workspaceRole): array
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
