<?php

namespace Tests\Feature\DevPulse;

use App\Models\AutomationExecution;
use App\Models\Organization;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AutomationAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class AutomationHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_automation_audit_records_success_and_failure(): void
    {
        $audit = app(AutomationAuditService::class);

        $audit->run('test.success', fn (): array => ['processed' => 3]);

        try {
            $audit->run('test.failure', fn () => throw new RuntimeException('Test failure'));
        } catch (RuntimeException) {
            // The original exception must continue to Laravel's queue retry handling.
        }

        $this->assertDatabaseHas('automation_executions', [
            'task' => 'test.success',
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('automation_executions', [
            'task' => 'test.failure',
            'status' => 'failed',
            'error' => 'Test failure',
        ]);
    }

    public function test_admin_sees_scheduler_health_and_missing_expected_runs(): void
    {
        [$admin, $organization, $workspace] = $this->organizationUser('admin');
        $template = StandupTemplate::factory()->for($organization)->create([
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $admin->id,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Expected today',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'end_time_of_day' => '10:00',
            'days_of_week' => [now('Asia/Nicosia')->dayOfWeekIso],
            'recipient_mode' => 'all_workspace_members',
            'delivery_method' => 'email',
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
        AutomationExecution::create([
            'task' => 'scheduler.heartbeat',
            'status' => 'succeeded',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('automation-health.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('automation-health')
                ->where('health.scheduler.status', 'healthy')
                ->where('health.missing_runs.0.id', $schedule->id)
                ->where('health.queue.failed_jobs', 0));
    }

    public function test_member_cannot_view_automation_health(): void
    {
        [$member] = $this->organizationUser('member');

        $this->actingAs($member)->get(route('automation-health.index'))->assertForbidden();
    }

    /** @return array{User, Organization, Workspace} */
    private function organizationUser(string $role): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);
        $organization->users()->attach($user, ['role' => $role]);
        $workspace->users()->attach($user, [
            'role' => $role === 'member' ? 'member' : 'manager',
            'is_active' => true,
        ]);

        return [$user, $organization, $workspace];
    }
}
