<?php

namespace Tests\Feature\DevPulse;

use App\Models\Organization;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_apis_filter_sort_and_paginate_organization_data(): void
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create(['name' => 'Engineering']);
        $otherWorkspace = Workspace::factory()->for($organization)->create(['name' => 'Product', 'is_active' => false]);
        $admin = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);
        $member = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
        $organization->users()->attach($admin, ['role' => 'admin']);
        $organization->users()->attach($member, ['role' => 'member']);
        $workspace->users()->attach([$admin->id => ['role' => 'manager', 'is_active' => true]]);
        $otherWorkspace->users()->attach([$member->id => ['role' => 'member', 'is_active' => true]]);

        $template = StandupTemplate::factory()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $admin->id,
            'name' => 'Alpha template',
        ]);
        StandupTemplate::factory()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $otherWorkspace->id,
            'created_by_user_id' => $admin->id,
            'name' => 'Zulu template',
            'is_active' => false,
        ]);
        StandupRun::factory()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'status' => 'completed',
        ]);
        StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Engineering',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'all_workspace_members',
            'delivery_method' => 'email',
            'reminder_minutes_after' => 15,
            'summary_minutes_after' => 30,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('api.standups.index', ['status' => 'completed']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.status', 'completed');

        $this->getJson(route('api.templates.index', ['active' => 0, 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Zulu template');

        $this->getJson(route('api.schedules.index', ['search' => 'Engineering']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Daily Engineering');

        $this->getJson(route('api.workspaces.index', ['active' => 0]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Product');

        $this->getJson(route('api.team-members.index', ['role' => 'member', 'workspace_id' => $otherWorkspace->id]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.email', 'ada@example.test');
    }

    public function test_index_apis_reject_unsupported_sort_and_page_sizes(): void
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $admin = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);
        $organization->users()->attach($admin, ['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson(route('api.workspaces.index', ['sort' => 'organization_id', 'per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'per_page']);
    }
}
