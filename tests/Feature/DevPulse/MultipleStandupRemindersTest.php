<?php

namespace Tests\Feature\DevPulse;

use App\Jobs\PostLateStandupAddendumJob;
use App\Jobs\ProcessDueStandupRunsJob;
use App\Jobs\SendStandupRunReminderJob;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\Organization;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\StandupPromptNotification;
use App\Services\StandupMessageService;
use App\Services\StandupReminderMessageService;
use App\Services\StandupSchedulerService;
use App\Services\StandupSubmissionFinalizer;
use App\Services\StandupSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultipleStandupRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_run_generation_snapshots_the_deadline_and_all_reminder_times(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 05:00:00', 'UTC'));
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $schedule = $this->schedule($user, $organization, $workspace, $template);

        app(StandupSchedulerService::class)->generateDueRuns(Carbon::parse('2026-07-10 08:00:00', 'Asia/Nicosia'));

        $run = StandupRun::query()->where('standup_schedule_id', $schedule->id)->firstOrFail();

        $this->assertSame('2026-07-10 05:00:00', $run->scheduled_open_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-10 06:30:00', $run->summary_due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(
            ['2026-07-10 05:30:00', '2026-07-10 06:00:00', '2026-07-10 06:15:00', '2026-07-10 06:25:00'],
            $run->reminders()->orderBy('scheduled_for')->get()->map(fn ($reminder) => $reminder->scheduled_for->utc()->format('Y-m-d H:i:s'))->all(),
        );
    }

    public function test_due_reminder_emails_only_pending_users_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 05:30:00', 'UTC'));
        Notification::fake();
        [$pending, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $submitted = User::factory()->create();
        $organization->users()->attach($submitted, ['role' => 'member']);
        $workspace->users()->attach($submitted, ['role' => 'member', 'is_active' => true]);
        $schedule = $this->schedule($pending, $organization, $workspace, $template);
        $run = StandupRun::factory()->for($organization)->for($workspace)->for($template)->create([
            'standup_schedule_id' => $schedule->id,
            'status' => 'active',
            'scheduled_open_at' => now()->subMinutes(30),
            'summary_due_at' => now()->addHour(),
        ]);
        StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $pending->id,
            'status' => 'pending',
            'source' => 'email',
        ]);
        StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $submitted->id,
            'status' => 'submitted',
            'submitted_at' => now()->subMinute(),
            'source' => 'email',
        ]);
        $reminder = $run->reminders()->create([
            'minutes_before_end' => 60,
            'scheduled_for' => now(),
            'status' => 'pending',
        ]);
        $job = new SendStandupRunReminderJob($reminder->id);

        $job->handle(app(StandupMessageService::class), app(StandupReminderMessageService::class));
        $job->handle(app(StandupMessageService::class), app(StandupReminderMessageService::class));

        Notification::assertSentToTimes($pending, StandupPromptNotification::class, 1);
        Notification::assertNotSentTo($submitted, StandupPromptNotification::class);
        $this->assertSame('sent', $reminder->fresh()->status);
    }

    public function test_due_processor_dispatches_each_elapsed_reminder_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 05:00:00', 'UTC'));
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $schedule = $this->schedule($user, $organization, $workspace, $template);
        app(StandupSchedulerService::class)->generateDueRuns(Carbon::parse('2026-07-10 08:00:00', 'Asia/Nicosia'));
        $run = StandupRun::query()->where('standup_schedule_id', $schedule->id)->firstOrFail();
        $run->update(['status' => 'active']);
        Carbon::setTestNow(Carbon::parse('2026-07-10 06:25:00', 'UTC'));
        Queue::fake();

        (new ProcessDueStandupRunsJob)->handle();

        Queue::assertPushed(SendStandupRunReminderJob::class, 4);
    }

    public function test_channel_schedule_reminder_uses_slack_dm_with_email_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 05:30:00', 'UTC'));
        Notification::fake();
        Http::fake([
            'slack.com/api/conversations.open' => Http::response(['ok' => true, 'channel' => ['id' => 'D_PRIVATE']]),
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);
        [$linked, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $unlinked = User::factory()->create();
        $organization->users()->attach($unlinked, ['role' => 'member']);
        $workspace->users()->attach($unlinked, ['role' => 'member', 'is_active' => true]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-reminders',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $linked->id,
            'provider' => 'slack',
            'external_user_id' => 'U_LINKED',
        ]);
        $schedule = $this->schedule($linked, $organization, $workspace, $template);
        $schedule->update(['delivery_method' => 'slack_channel', 'target_integration_id' => $integration->id]);
        $run = StandupRun::factory()->for($organization)->for($workspace)->for($template)->create([
            'standup_schedule_id' => $schedule->id,
            'status' => 'active',
            'summary_due_at' => now()->addHour(),
        ]);

        foreach ([$linked, $unlinked] as $member) {
            StandupResponse::create([
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
                'standup_run_id' => $run->id,
                'user_id' => $member->id,
                'status' => 'pending',
                'source' => 'slack',
            ]);
        }

        app(StandupMessageService::class)->sendReminder($run, 'Private deadline reminder');

        Notification::assertSentTo($unlinked, StandupPromptNotification::class);
        Notification::assertNotSentTo($linked, StandupPromptNotification::class);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'D_PRIVATE'
            && $request['text'] === 'Private deadline reminder');
    }

    public function test_checkpoint_stays_open_and_late_submission_queues_an_addendum(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-10 06:30:00', 'UTC'));
        [$pending, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $schedule = $this->schedule($pending, $organization, $workspace, $template);
        $run = StandupRun::factory()->for($organization)->for($workspace)->for($template)->create([
            'standup_schedule_id' => $schedule->id,
            'status' => 'active',
            'summary_due_at' => now(),
        ]);
        $response = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $pending->id,
            'status' => 'pending',
            'source' => 'web',
        ]);

        app(StandupSummaryService::class)->postSummary($run);
        $this->assertSame('active', $run->fresh()->status);

        $response->update(['status' => 'submitted', 'submitted_at' => now()->addMinute()]);
        app(StandupSubmissionFinalizer::class)->handle($response->fresh());

        Queue::assertPushed(PostLateStandupAddendumJob::class, fn ($job) => $job->standupResponseId === $response->id);
    }

    private function schedule(User $user, Organization $organization, Workspace $workspace, StandupTemplate $template): StandupSchedule
    {
        return StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:00:00',
            'end_time_of_day' => '09:30:00',
            'days_of_week' => [5],
            'delivery_method' => 'email',
            'reminder_minutes_before_end' => [60, 30, 15, 5],
            'reminder_message_template' => StandupReminderMessageService::DEFAULT_TEMPLATE,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
    }

    /** @return array{User, Organization, Workspace, StandupTemplate} */
    private function workspaceWithTemplate(): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($user, ['role' => 'admin']);
        $workspace->users()->attach($user, ['role' => 'manager', 'is_active' => true]);
        $template = StandupTemplate::factory()->for($organization)->for($workspace)->for($user, 'creator')->create();

        return [$user, $organization, $workspace, $template];
    }
}
