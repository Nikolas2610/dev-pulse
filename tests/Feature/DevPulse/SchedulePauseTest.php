<?php

namespace Tests\Feature\DevPulse;

use App\Jobs\OpenStandupRunJob;
use App\Models\Organization;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\StandupMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulePauseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_pause_and_resume_a_schedule(): void
    {
        [$admin, $organization, $workspace, $template] = $this->scheduleContext();
        $schedule = $this->createSchedule($admin, $organization, $workspace, $template);
        $scheduledRun = $this->createRun($schedule, $template, 'scheduled', now()->toDateString());
        $activeRun = $this->createRun($schedule, $template, 'active', now()->addDay()->toDateString());

        $this->actingAs($admin)
            ->patch(route('schedules.toggle-active', $schedule), ['is_active' => false])
            ->assertRedirect(route('schedules.index'));

        $this->assertFalse($schedule->refresh()->is_active);
        $this->assertSame('cancelled', $scheduledRun->refresh()->status);
        $this->assertSame('active', $activeRun->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('schedules.toggle-active', $schedule), ['is_active' => true])
            ->assertRedirect(route('schedules.index'));

        $this->assertTrue($schedule->refresh()->is_active);
        $this->assertSame('cancelled', $scheduledRun->refresh()->status);
    }

    public function test_queued_run_does_not_open_while_its_schedule_is_paused(): void
    {
        [$admin, $organization, $workspace, $template] = $this->scheduleContext();
        $schedule = $this->createSchedule($admin, $organization, $workspace, $template, isActive: false);
        $run = $this->createRun($schedule, $template, 'scheduled', now()->toDateString(), now()->subMinute());

        (new OpenStandupRunJob($run->id))->handle(app(StandupMessageService::class));

        $this->assertSame('scheduled', $run->refresh()->status);
        $this->assertNull($run->opened_at);
        $this->assertDatabaseCount('standup_responses', 0);
    }

    /** @return array{User, Organization, Workspace, StandupTemplate} */
    private function scheduleContext(): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $admin = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);
        $organization->users()->attach($admin, ['role' => 'admin']);
        $workspace->users()->attach($admin, ['role' => 'manager', 'is_active' => true]);
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($admin, 'creator')
            ->create();

        return [$admin, $organization, $workspace, $template];
    }

    private function createSchedule(
        User $admin,
        Organization $organization,
        Workspace $workspace,
        StandupTemplate $template,
        bool $isActive = true,
    ): StandupSchedule {
        return StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => $isActive,
            'created_by_user_id' => $admin->id,
        ]);
    }

    private function createRun(
        StandupSchedule $schedule,
        StandupTemplate $template,
        string $status,
        string $runDate,
        mixed $scheduledOpenAt = null,
    ): StandupRun {
        return StandupRun::factory()->create([
            'organization_id' => $schedule->organization_id,
            'workspace_id' => $schedule->workspace_id,
            'standup_schedule_id' => $schedule->id,
            'standup_template_id' => $template->id,
            'run_date' => $runDate,
            'scheduled_open_at' => $scheduledOpenAt,
            'status' => $status,
        ]);
    }
}
