<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_members_can_view_workspace_reports(): void
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);
        $organization->users()->attach($user, ['role' => 'member']);
        $workspace->users()->attach($user, ['role' => 'member', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('reports.index', ['days' => 60]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->where('period.days', 60)
                ->where('workspace', $workspace->name)
                ->has('metrics')
                ->has('daily')
                ->has('weekdays', 5)
                ->has('team'));
    }
}
