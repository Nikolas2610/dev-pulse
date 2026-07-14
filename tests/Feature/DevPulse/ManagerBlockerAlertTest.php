<?php

namespace Tests\Feature\DevPulse;

use App\Jobs\SendManagerBlockerAlertJob;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\ManagerBlockerNotification;
use App\Models\Organization;
use App\Models\StandupQuestion;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ManagerBlockerAlertService;
use App\Services\StandupSubmissionFinalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManagerBlockerAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_a_workspace_manager_and_demotion_clears_assignments(): void
    {
        [$admin, $manager, $developer, , $workspace] = $this->team();

        $this->actingAs($admin)->patch(route('team-members.update', $developer), [
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
            'manager_user_id' => $manager->id,
        ])->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $developer->id,
            'manager_user_id' => $manager->id,
        ]);

        $this->actingAs($admin)->patch(route('team-members.update', $manager), [
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
            'manager_user_id' => null,
        ])->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $developer->id,
            'manager_user_id' => null,
        ]);
    }

    public function test_manager_assignment_rejects_self_non_managers_and_other_workspaces(): void
    {
        [$admin, , $developer, $organization, $workspace] = $this->team();
        $otherMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($otherMember, ['role' => 'member']);
        $workspace->users()->attach($otherMember, ['role' => 'member', 'is_active' => true]);

        foreach ([$developer->id, $otherMember->id] as $invalidManagerId) {
            $this->actingAs($admin)->patch(route('team-members.update', $developer), [
                'organization_role' => 'member',
                'workspace_id' => $workspace->id,
                'workspace_role' => 'member',
                'manager_user_id' => $invalidManagerId,
            ])->assertSessionHasErrors('manager_user_id');
        }

        $otherWorkspace = Workspace::factory()->for($organization)->create();
        $otherManager = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($otherManager, ['role' => 'manager']);
        $otherWorkspace->users()->attach($otherManager, ['role' => 'manager', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('team-members.update', $developer), [
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
            'manager_user_id' => $otherManager->id,
        ])->assertSessionHasErrors('manager_user_id');
    }

    public function test_deactivating_or_removing_a_manager_clears_workspace_assignments(): void
    {
        [$admin, $manager, $developer, , $workspace] = $this->team();

        $manager->update(['is_active' => false]);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $developer->id,
            'manager_user_id' => null,
        ]);

        $manager->update(['is_active' => true]);
        $workspace->users()->updateExistingPivot($developer->id, ['manager_user_id' => $manager->id]);
        $this->actingAs($admin)
            ->delete(route('team-members.workspaces.destroy', [$manager, $workspace]))
            ->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $developer->id,
            'manager_user_id' => null,
        ]);
    }

    public function test_schedule_requires_an_active_slack_integration_for_blocker_alerts(): void
    {
        [$admin, , , $organization, $workspace] = $this->team();
        $template = $this->template($admin, $organization, $workspace);
        $payload = $this->schedulePayload($workspace, $template, [
            'notify_manager_on_blocker' => true,
        ]);

        $this->actingAs($admin)->post(route('schedules.store'), $payload)
            ->assertSessionHasErrors('notify_manager_on_blocker');

        $integration = Integration::factory()->for($organization)->for($workspace)->create();
        $this->actingAs($admin)->post(route('schedules.store'), [
            ...$payload,
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
        ])->assertRedirect(route('schedules.index'));

        $this->assertDatabaseHas('standup_schedules', [
            'target_integration_id' => $integration->id,
            'notify_manager_on_blocker' => true,
        ]);
    }

    public function test_finalizer_queues_only_affirmative_blockers_for_enabled_schedules(): void
    {
        Queue::fake();
        $context = $this->alertContext();

        app(StandupSubmissionFinalizer::class)->handle($context['response']);

        Queue::assertPushed(SendManagerBlockerAlertJob::class, 1);

        $context['response']->answers()->where('standup_question_id', $context['blocker']->id)->update(['answer_text' => 'No']);
        $context['response']->unsetRelation('answers');
        app(StandupSubmissionFinalizer::class)->handle($context['response']);

        Queue::assertPushed(SendManagerBlockerAlertJob::class, 1);

        $context['response']->answers()->where('standup_question_id', $context['blockerDetail']->id)->update(['answer_text' => 'Still waiting']);
        $context['response']->unsetRelation('answers');
        app(StandupSubmissionFinalizer::class)->handle($context['response']);

        Queue::assertPushed(SendManagerBlockerAlertJob::class, 1);
    }

    public function test_web_and_slack_submission_paths_queue_the_shared_blocker_alert_job(): void
    {
        Queue::fake();
        $web = $this->alertContext();
        $web['response']->delete();

        $this->actingAs($web['developer'])->post(route('standup-runs.submit.store', $web['run']), [
            'answers' => [
                $web['blocker']->id => 'Yes',
                $web['blockerDetail']->id => 'Waiting for API access',
                $web['today']->id => 'Ship checkout',
            ],
        ])->assertRedirect(route('standups.show', $web['run']));

        $slack = $this->alertContext();
        $slack['response']->update(['source' => 'slack']);
        app(StandupSubmissionFinalizer::class)->handle($slack['response']);

        Queue::assertPushed(SendManagerBlockerAlertJob::class, 2);
    }

    public function test_disabled_alerts_and_members_without_managers_do_not_send(): void
    {
        Queue::fake();
        $context = $this->alertContext();
        $context['schedule']->update(['notify_manager_on_blocker' => false]);
        $context['response']->unsetRelation('run');

        app(StandupSubmissionFinalizer::class)->handle($context['response']);
        Queue::assertNothingPushed();

        $context['schedule']->update(['notify_manager_on_blocker' => true]);
        $context['workspace']->users()->updateExistingPivot($context['developer']->id, ['manager_user_id' => null]);
        Http::fake();

        app(ManagerBlockerAlertService::class)->sendForResponse($context['response']->id);

        Http::assertNothingSent();
        $this->assertSame(0, ManagerBlockerNotification::count());
    }

    public function test_alert_sends_only_blocker_answers_and_is_idempotent(): void
    {
        $context = $this->alertContext();
        Http::fake([
            'slack.com/api/conversations.open' => Http::response(['ok' => true, 'channel' => ['id' => 'D_MANAGER']]),
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        $alerts = app(ManagerBlockerAlertService::class);
        $alerts->sendForResponse($context['response']->id);
        $alerts->sendForResponse($context['response']->id);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'D_MANAGER'
            && filled($request['client_msg_id'])
            && str_contains($request['text'], $context['developer']->name)
            && str_contains($request['text'], 'Waiting for API access')
            && ! str_contains($request['text'], 'Ship checkout'));
        $this->assertSame(1, ManagerBlockerNotification::count());
        $this->assertDatabaseHas('manager_blocker_notifications', [
            'standup_response_id' => $context['response']->id,
            'status' => 'sent',
            'external_message_id' => '123.456',
        ]);
    }

    public function test_missing_slack_mapping_is_recorded_without_sending(): void
    {
        $context = $this->alertContext(linkManager: false);
        Http::fake();

        app(ManagerBlockerAlertService::class)->sendForResponse($context['response']->id);

        Http::assertNothingSent();
        $this->assertDatabaseHas('manager_blocker_notifications', [
            'standup_response_id' => $context['response']->id,
            'status' => 'skipped',
            'failure_reason' => 'The assigned manager is not linked to this Slack integration.',
        ]);
    }

    public function test_slack_api_failure_is_recorded_without_throwing(): void
    {
        $context = $this->alertContext();
        Http::fake([
            'slack.com/api/conversations.open' => Http::response(['ok' => true, 'channel' => ['id' => 'D_MANAGER']]),
            'slack.com/api/chat.postMessage' => Http::response(['ok' => false, 'error' => 'ratelimited']),
        ]);

        app(ManagerBlockerAlertService::class)->sendForResponse($context['response']->id);

        $this->assertDatabaseHas('manager_blocker_notifications', [
            'standup_response_id' => $context['response']->id,
            'status' => 'failed',
            'failure_reason' => 'Slack API chat.postMessage failed: ratelimited',
        ]);
    }

    /** @return array{User, User, User, Organization, Workspace} */
    private function team(): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $admin = User::factory()->create(['current_organization_id' => $organization->id]);
        $manager = User::factory()->create(['current_organization_id' => $organization->id]);
        $developer = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($admin, ['role' => 'admin']);
        $organization->users()->attach($manager, ['role' => 'manager']);
        $organization->users()->attach($developer, ['role' => 'member']);
        $workspace->users()->attach($admin, ['role' => 'manager', 'is_active' => true]);
        $workspace->users()->attach($manager, ['role' => 'manager', 'is_active' => true]);
        $workspace->users()->attach($developer, [
            'role' => 'member',
            'is_active' => true,
            'manager_user_id' => $manager->id,
        ]);

        return [$admin, $manager, $developer, $organization, $workspace];
    }

    private function template(User $admin, Organization $organization, Workspace $workspace): StandupTemplate
    {
        return StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($admin, 'creator')
            ->create();
    }

    /** @return array<string, mixed> */
    private function schedulePayload(Workspace $workspace, StandupTemplate $template, array $overrides = []): array
    {
        return [
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'end_time_of_day' => '10:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'all_workspace_members',
            'delivery_method' => 'email',
            'reminder_minutes_before_end' => [60, 30, 15, 5],
            'is_active' => true,
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function alertContext(bool $linkManager = true): array
    {
        [$admin, $manager, $developer, $organization, $workspace] = $this->team();
        $template = $this->template($admin, $organization, $workspace);
        $blocker = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'blocker',
            'question_text' => 'Are you blocked?',
            'answer_type' => 'boolean',
            'sort_order' => 1,
        ]);
        $blockerDetail = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'blocker_detail',
            'question_text' => 'What is blocking you?',
            'sort_order' => 2,
        ]);
        $today = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you doing today?',
            'sort_order' => 3,
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create();

        if ($linkManager) {
            IntegrationUser::create([
                'integration_id' => $integration->id,
                'user_id' => $manager->id,
                'provider' => 'slack',
                'external_user_id' => 'U_MANAGER',
                'external_email' => $manager->email,
            ]);
        }

        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'notify_manager_on_blocker' => true,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $response = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $developer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $response->answers()->createMany([
            ['standup_question_id' => $blocker->id, 'answer_text' => 'Yes'],
            ['standup_question_id' => $blockerDetail->id, 'answer_text' => 'Waiting for API access'],
            ['standup_question_id' => $today->id, 'answer_text' => 'Ship checkout'],
        ]);

        return compact(
            'admin',
            'manager',
            'developer',
            'organization',
            'workspace',
            'template',
            'blocker',
            'blockerDetail',
            'today',
            'integration',
            'schedule',
            'run',
            'response',
        );
    }
}
