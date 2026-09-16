<?php

namespace Tests\Feature\DevPulse;

use App\Jobs\GenerateStandupRunsJob;
use App\Jobs\PostStandupSummaryJob;
use App\Models\ExternalWorkItem;
use App\Models\Integration;
use App\Models\IntegrationChannel;
use App\Models\IntegrationUser;
use App\Models\JiraProject;
use App\Models\Organization;
use App\Models\StandupQuestion;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\StandupPromptNotification;
use App\Services\ActivityEventService;
use App\Services\StandupMessageService;
use App\Services\StandupQuestionFlowService;
use App\Services\StandupSchedulerService;
use App\Services\StandupSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StandupRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_standup_runs_job_creates_todays_run_for_active_schedule(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();

        StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [(int) now('Asia/Nicosia')->dayOfWeekIso],
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);

        app(GenerateStandupRunsJob::class)->handle(app(StandupSchedulerService::class));

        $this->assertNotNull(StandupRun::query()
            ->where('organization_id', $organization->id)
            ->where('workspace_id', $workspace->id)
            ->where('standup_template_id', $template->id)
            ->whereDate('run_date', now('Asia/Nicosia')->toDateString())
            ->where('status', 'scheduled')
            ->first());
        $this->assertDatabaseHas('activity_events', [
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'event_type' => ActivityEventService::STANDUP_RUN_CREATED,
        ]);
    }

    public function test_post_summary_job_generates_summary_and_posts_to_slack_channel(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you focusing on today?',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-summary',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $pendingUser = User::factory()->create();
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $pendingUser->id,
            'provider' => 'slack',
            'external_user_id' => 'U_PENDING',
            'external_email' => $pendingUser->email,
        ]);
        $channel = IntegrationChannel::create([
            'integration_id' => $integration->id,
            'external_channel_id' => 'C123',
            'name' => 'standups',
            'type' => 'public',
            'is_active' => true,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'target_integration_id' => $integration->id,
            'target_channel_id' => $channel->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $standupResponse = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Shipping DevPulse',
        ]);
        StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $pendingUser->id,
            'status' => 'pending',
            'source' => 'slack',
        ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        (new PostStandupSummaryJob($run->id))->handle(app(StandupSummaryService::class));

        $this->assertDatabaseHas('standup_summaries', [
            'standup_run_id' => $run->id,
            'posted_to_integration_id' => $integration->id,
            'posted_to_channel_id' => $channel->id,
            'external_message_id' => '123.456',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && str_contains($request['text'], 'Here is the report from <@U123>')
            && str_contains($request['text'], '*What are you focusing on today*')
            && str_contains($request['text'], 'Shipping DevPulse'));
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && str_contains($request['text'], '*Pending standup responses*')
            && str_contains($request['text'], '- <@U_PENDING>')
            && ! str_contains($request['text'], $pendingUser->name));
        $this->assertDatabaseHas('activity_events', [
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'event_type' => ActivityEventService::STANDUP_SUMMARY_POSTED,
        ]);
    }

    public function test_admin_can_manually_send_latest_schedule_summary(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you focusing on today?',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-summary',
        ]);
        $channel = IntegrationChannel::create([
            'integration_id' => $integration->id,
            'external_channel_id' => 'C123',
            'name' => 'standups',
            'type' => 'public',
            'is_active' => true,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'target_integration_id' => $integration->id,
            'target_channel_id' => $channel->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $standupResponse = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Shipping summaries',
        ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        $this->actingAs($user)
            ->post(route('schedules.send-summary', $schedule))
            ->assertRedirect(route('schedules.index'));

        $this->assertDatabaseHas('standup_summaries', [
            'standup_run_id' => $run->id,
            'posted_to_integration_id' => $integration->id,
            'posted_to_channel_id' => $channel->id,
            'external_message_id' => '123.456',
        ]);
    }

    public function test_slack_summary_pending_uses_run_recipients_not_all_workspace_members(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $unselectedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you focusing on today?',
        ]);

        $organization->users()->attach($unselectedMember, ['role' => 'member']);
        $workspace->users()->attach($unselectedMember, ['role' => 'member', 'is_active' => true]);

        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-summary',
        ]);
        $channel = IntegrationChannel::create([
            'integration_id' => $integration->id,
            'external_channel_id' => 'C123',
            'name' => 'standups',
            'type' => 'public',
            'is_active' => true,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Selected Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'selected_members',
            'target_integration_id' => $integration->id,
            'target_channel_id' => $channel->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $schedule->participants()->attach($user->id);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'run_date' => now($organization->timezone)->toDateString(),
                'status' => 'active',
            ]);
        $standupResponse = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Shipping selected summaries',
        ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        (new PostStandupSummaryJob($run->id))->handle(app(StandupSummaryService::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && str_contains($request['text'], 'Shipping selected summaries')
            && ! str_contains($request['text'], 'Pending standup responses')
            && ! str_contains($request['text'], $unselectedMember->name));
        $this->assertDatabaseHas('standup_summaries', [
            'standup_run_id' => $run->id,
            'summary_text' => "*Daily standup - {$workspace->name}*\n".$run->run_date->toDateString()."\nCompleted: *1/1*\n\n*{$user->name} submitted their standup*\n- *Today:* Shipping selected summaries",
        ]);
    }

    public function test_manual_summary_send_requires_configured_external_destination(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you focusing on today?',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-summary',
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'target_integration_id' => $integration->id,
            'target_channel_id' => null,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $standupResponse = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Shipping summaries',
        ]);

        Http::fake();

        $this->actingAs($user)
            ->post(route('schedules.send-summary', $schedule))
            ->assertRedirect(route('schedules.index'));

        $this->assertDatabaseMissing('standup_summaries', [
            'standup_run_id' => $run->id,
        ]);
        Http::assertNothingSent();
    }

    public function test_manual_summary_send_redirects_when_slack_rejects_channel_post(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you focusing on today?',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-summary',
        ]);
        $channel = IntegrationChannel::create([
            'integration_id' => $integration->id,
            'external_channel_id' => 'C123',
            'name' => 'standups',
            'type' => 'private',
            'is_active' => true,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'target_integration_id' => $integration->id,
            'target_channel_id' => $channel->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $standupResponse = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Shipping summaries',
        ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => false, 'error' => 'not_in_channel']),
            'slack.com/api/conversations.join' => Http::response(['ok' => false, 'error' => 'method_not_supported_for_channel_type']),
        ]);

        $this->actingAs($user)
            ->post(route('schedules.send-summary', $schedule))
            ->assertRedirect(route('schedules.index'));

        $this->assertDatabaseMissing('standup_summaries', [
            'standup_run_id' => $run->id,
        ]);
    }

    public function test_manual_summary_send_redirects_when_slack_post_times_out(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you focusing on today?',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-summary',
        ]);
        $channel = IntegrationChannel::create([
            'integration_id' => $integration->id,
            'external_channel_id' => 'C123',
            'name' => 'standups',
            'type' => 'public',
            'is_active' => true,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'target_integration_id' => $integration->id,
            'target_channel_id' => $channel->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $standupResponse = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Shipping summaries',
        ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => fn () => throw new ConnectionException('DNS timeout'),
        ]);

        $this->actingAs($user)
            ->post(route('schedules.send-summary', $schedule))
            ->assertRedirect(route('schedules.index'));

        $this->assertDatabaseMissing('standup_summaries', [
            'standup_run_id' => $run->id,
        ]);
    }

    public function test_open_run_posts_slack_prompt_to_prompt_channel(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-prompt',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U_PROMPT',
            'external_email' => $user->email,
        ]);
        $promptChannel = IntegrationChannel::create([
            'integration_id' => $integration->id,
            'external_channel_id' => 'C_PROMPT',
            'name' => 'standup-bot',
            'type' => 'public',
            'is_active' => true,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_channel',
            'target_integration_id' => $integration->id,
            'prompt_channel_id' => $promptChannel->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        app(StandupMessageService::class)->openRun($run);

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'C_PROMPT'
            && $request['blocks'][1]['elements'][0]['action_id'] === 'join_standup'
            && $request['blocks'][1]['elements'][1]['action_id'] === 'skip_standup'
            && $request['blocks'][1]['elements'][0]['value'] === (string) $run->id
            && ! str_contains($request['text'], route('standup-runs.submit', $run)));
    }

    public function test_slack_channel_open_run_tracks_linked_and_unlinked_users_for_private_reminder_fallback(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $unlinkedMember = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($unlinkedMember, ['role' => 'member']);
        $workspace->users()->attach($unlinkedMember, ['role' => 'member', 'is_active' => true]);

        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-prompt',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U_LINKED',
            'external_email' => $user->email,
        ]);
        $promptChannel = IntegrationChannel::create([
            'integration_id' => $integration->id,
            'external_channel_id' => 'C_PROMPT',
            'name' => 'standups',
            'type' => 'public',
            'is_active' => true,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Channel Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'all_workspace_members',
            'delivery_method' => 'slack_channel',
            'target_integration_id' => $integration->id,
            'prompt_channel_id' => $promptChannel->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        app(StandupMessageService::class)->openRun($run);

        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'slack',
        ]);
        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $unlinkedMember->id,
            'status' => 'pending',
        ]);
    }

    public function test_open_run_emails_pending_recipients_when_delivery_method_is_email(): void
    {
        Notification::fake();

        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Email Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'email',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        app(StandupMessageService::class)->openRun($run);

        Notification::assertSentTo(
            $user,
            StandupPromptNotification::class,
            fn (StandupPromptNotification $notification) => $notification->run->is($run)
                && $notification->reminder === false,
        );
    }

    public function test_admin_can_manually_send_scheduled_standup_now(): void
    {
        Notification::fake();

        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Manual Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'email',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('schedules.send-now', $schedule));

        $run = StandupRun::query()
            ->where('standup_schedule_id', $schedule->id)
            ->whereDate('run_date', now('Asia/Nicosia')->toDateString())
            ->firstOrFail();

        $response->assertRedirect(route('schedules.index'));
        $this->assertSame('active', $run->status);
        $this->assertNotNull($run->opened_at);
        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'email',
        ]);
        Notification::assertSentTo($user, StandupPromptNotification::class);
    }

    #[DataProvider('deployedEnvironments')]
    public function test_manual_send_creates_a_new_run_each_time_for_testing_in_deployed_environments(string $environment): void
    {
        Notification::fake();
        config(['app.env' => $environment]);

        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Manual Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'email',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('schedules.send-now', $schedule));
        $this->actingAs($user)->post(route('schedules.send-now', $schedule));

        $runs = StandupRun::query()
            ->where('standup_schedule_id', $schedule->id)
            ->orderBy('run_date')
            ->get();

        $this->assertCount(2, $runs);
        $this->assertNotSame(
            $runs[0]->run_date->toDateString(),
            $runs[1]->run_date->toDateString(),
        );
        $this->assertSame(2, StandupResponse::query()
            ->whereIn('standup_run_id', $runs->pluck('id'))
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->count());
        Notification::assertCount(2);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deployedEnvironments(): array
    {
        return [
            'production' => ['production'],
            'staging' => ['staging'],
        ];
    }

    public function test_slack_dm_delivery_sends_only_to_mapped_pending_users(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you focusing on today?',
        ]);
        $unmappedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($unmappedMember, ['role' => 'member']);
        $workspace->users()->attach($unmappedMember, ['role' => 'member', 'is_active' => true]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-dm',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U_MAPPED',
            'external_email' => $user->email,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack DM Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        Http::fake([
            'slack.com/api/conversations.open' => Http::response(['ok' => true, 'channel' => ['id' => 'D_MAPPED']]),
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        app(StandupMessageService::class)->openRun($run);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/conversations.open'
            && $request['users'] === 'U_MAPPED');
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'D_MAPPED'
            && $request['blocks'][1]['elements'][0]['action_id'] === 'join_standup'
            && $request['blocks'][1]['elements'][1]['action_id'] === 'skip_standup'
            && ! str_contains($request['text'], route('standup-runs.submit', $run)));
    }

    public function test_slack_dm_delivery_starts_with_join_or_skip_choice(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        StandupQuestion::factory()->for($template)->create([
            'question_text' => 'How do you feel today?',
            'question_key' => 'mood',
            'answer_type' => 'select',
            'options_json' => ['bad', 'good', 'excellent'],
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-dm',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U_MAPPED',
            'external_email' => $user->email,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack DM Mood Check',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        Http::fake([
            'slack.com/api/conversations.open' => Http::response(['ok' => true, 'channel' => ['id' => 'D_MAPPED']]),
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456']),
        ]);

        app(StandupMessageService::class)->openRun($run);

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['blocks'][1]['elements'][0]['action_id'] === 'join_standup'
            && $request['blocks'][1]['elements'][1]['action_id'] === 'skip_standup');
    }

    public function test_email_reminder_sends_only_to_pending_recipients(): void
    {
        Notification::fake();

        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $submittedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($submittedMember, ['role' => 'member']);
        $workspace->users()->attach($submittedMember, ['role' => 'member', 'is_active' => true]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Reminder Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'email',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $run->responses()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'email',
        ]);
        $run->responses()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $submittedMember->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);

        app(StandupMessageService::class)->sendReminder($run);

        Notification::assertSentTo(
            $user,
            StandupPromptNotification::class,
            fn (StandupPromptNotification $notification) => $notification->run->is($run)
                && $notification->reminder === true,
        );
        Notification::assertNotSentTo($submittedMember, StandupPromptNotification::class);
    }

    public function test_slack_button_interaction_opens_standup_modal(): void
    {
        config(['features.atlassian_integrations' => true]);
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you focusing on today?',
            'work_item_relation' => 'planned',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-modal',
            'signing_secret' => 'test-signing-secret',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
        ]);
        $jira = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);
        $jiraUser = IntegrationUser::create([
            'integration_id' => $jira->id,
            'user_id' => $user->id,
            'provider' => 'jira',
            'external_user_id' => 'jira-account-1',
        ]);
        $project = JiraProject::create([
            'integration_id' => $jira->id,
            'external_id' => '10000',
            'project_key' => 'DEV',
            'name' => 'Development',
        ]);
        ExternalWorkItem::create([
            'integration_id' => $jira->id,
            'workspace_id' => $workspace->id,
            'jira_project_id' => $project->id,
            'assignee_integration_user_id' => $jiraUser->id,
            'provider' => 'jira',
            'external_id' => '10001',
            'external_key' => 'DEV-1',
            'type' => 'jira_issue',
            'title' => 'Ship Slack Jira picker',
            'url' => 'https://example.atlassian.net/browse/DEV-1',
            'status' => 'In Progress',
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);

        Http::fake([
            'slack.com/api/views.open' => Http::response(['ok' => true]),
        ]);

        $this->postSignedSlackPayload([
            'type' => 'block_actions',
            'trigger_id' => 'trigger-123',
            'user' => ['id' => 'U123'],
            'actions' => [
                [
                    'action_id' => 'submit_standup',
                    'value' => (string) $run->id,
                ],
            ],
        ], 'test-signing-secret')
            ->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/views.open'
            && $request['trigger_id'] === 'trigger-123'
            && $request['view']['callback_id'] === 'devpulse_standup_submission'
            && collect($request['view']['blocks'])->contains(fn (array $block) => ($block['block_id'] ?? null) === 'work_items_'.$question->id
                && data_get($block, 'element.type') === 'multi_static_select'
                && data_get($block, 'element.options.0.value') !== null));
    }

    public function test_slack_modal_submission_stores_standup_answers(): void
    {
        config(['features.atlassian_integrations' => true]);
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you focusing on today?',
            'work_item_relation' => 'planned',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'external_id' => 'T123',
            'bot_token' => 'xoxb-submit',
            'signing_secret' => 'test-signing-secret',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $jira = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);
        $jiraUser = IntegrationUser::create([
            'integration_id' => $jira->id,
            'user_id' => $user->id,
            'provider' => 'jira',
            'external_user_id' => 'jira-account-1',
        ]);
        $project = JiraProject::create([
            'integration_id' => $jira->id,
            'external_id' => '10000',
            'project_key' => 'DEV',
            'name' => 'Development',
        ]);
        $item = ExternalWorkItem::create([
            'integration_id' => $jira->id,
            'workspace_id' => $workspace->id,
            'jira_project_id' => $project->id,
            'assignee_integration_user_id' => $jiraUser->id,
            'provider' => 'jira',
            'external_id' => '10001',
            'external_key' => 'DEV-1',
            'type' => 'jira_issue',
            'title' => 'Ship Slack Jira picker',
            'url' => 'https://example.atlassian.net/browse/DEV-1',
            'status' => 'In Progress',
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);

        Http::fake([
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        $submissionPayload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U123'],
            'view' => [
                'id' => 'V123',
                'private_metadata' => json_encode([
                    'run_id' => $run->id,
                    'integration_id' => $integration->id,
                    'channel_id' => 'D123',
                    'message_ts' => '123.456',
                ]),
                'state' => [
                    'values' => [
                        'question_'.$question->id => [
                            'answer' => [
                                'value' => 'Working on the Slack bot flow',
                            ],
                        ],
                        'work_items_'.$question->id => [
                            'selected_items' => [
                                'selected_options' => [
                                    ['value' => (string) $item->id],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postSignedSlackPayload($submissionPayload, 'test-signing-secret')
            ->assertOk()
            ->assertJson(['response_action' => 'clear']);

        $this->postSignedSlackPayload($submissionPayload, 'test-signing-secret')
            ->assertOk()
            ->assertJson(['response_action' => 'clear']);

        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'source' => 'slack',
        ]);
        $this->assertDatabaseHas('standup_answers', [
            'standup_question_id' => $question->id,
            'answer_text' => 'Working on the Slack bot flow',
        ]);
        $this->assertDatabaseHas('standup_response_items', [
            'standup_question_id' => $question->id,
            'external_work_item_id' => $item->id,
            'relation' => 'planned',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
            && $request['channel'] === 'D123'
            && $request['ts'] === '123.456'
            && $request['text'] === 'You submitted this standup.');
    }

    public function test_slack_user_can_skip_without_a_reason_and_prompt_is_replaced(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        StandupQuestion::factory()->for($template)->create();
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'external_id' => 'T123',
            'bot_token' => 'xoxb-skip',
            'signing_secret' => 'test-signing-secret',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'slack',
        ]);

        Http::fake([
            'slack.com/api/views.open' => Http::response(['ok' => true]),
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        $this->postSignedSlackPayload([
            'type' => 'block_actions',
            'trigger_id' => 'trigger-skip',
            'user' => ['id' => 'U123'],
            'channel' => ['id' => 'D123'],
            'message' => ['ts' => '123.456'],
            'actions' => [[
                'action_id' => 'skip_standup',
                'value' => (string) $run->id,
            ]],
        ], 'test-signing-secret')->assertOk();

        $metadata = null;
        Http::assertSent(function ($request) use (&$metadata): bool {
            if ($request->url() !== 'https://slack.com/api/views.open') {
                return false;
            }

            $metadata = json_decode($request['view']['private_metadata'], true);

            return $request['view']['blocks'][0]['optional'] === true
                && $metadata['channel_id'] === 'D123'
                && $metadata['message_ts'] === '123.456';
        });

        $this->postSignedSlackPayload([
            'type' => 'view_submission',
            'user' => ['id' => 'U123'],
            'view' => [
                'id' => 'V-SKIP',
                'private_metadata' => json_encode($metadata),
                'state' => ['values' => []],
            ],
        ], 'test-signing-secret')
            ->assertOk()
            ->assertJson(['response_action' => 'clear']);

        $response = StandupResponse::query()
            ->where('standup_run_id', $run->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame('skipped', $response->status);
        $this->assertSame([], $response->raw_json);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
            && $request['channel'] === 'D123'
            && $request['ts'] === '123.456'
            && $request['text'] === 'You chose to skip this standup.'
            && count($request['blocks']) === 1);

        $this->postSignedSlackPayload([
            'type' => 'block_actions',
            'trigger_id' => 'stale-trigger',
            'user' => ['id' => 'U123'],
            'channel' => ['id' => 'D123'],
            'message' => ['ts' => '111.222'],
            'actions' => [[
                'action_id' => 'join_standup',
                'value' => (string) $run->id,
            ]],
        ], 'test-signing-secret')->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
            && $request['ts'] === '111.222'
            && $request['text'] === 'You chose to skip this standup.');
        $this->assertCount(1, Http::recorded(
            fn ($request) => $request->url() === 'https://slack.com/api/views.open',
        ));
    }

    public function test_slack_message_answer_stores_answer_and_sends_next_question(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $firstQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What did you finish?',
            'sort_order' => 1,
        ]);
        $secondQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What is next?',
            'sort_order' => 2,
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'external_id' => 'T123',
            'bot_token' => 'xoxb-submit',
            'signing_secret' => 'test-signing-secret',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $run->responses()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'slack',
            'raw_json' => ['current_question_id' => $firstQuestion->id],
        ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '456.789']),
        ]);

        $this->postSignedSlackPayload([
            'type' => 'block_actions',
            'team' => ['id' => 'T123'],
            'user' => ['id' => 'U123'],
            'channel' => ['id' => 'D123'],
            'message' => ['ts' => '123.456'],
            'actions' => [[
                'action_id' => 'submit_standup_answer',
                'value' => json_encode([
                    'run_id' => $run->id,
                    'question_id' => $firstQuestion->id,
                ]),
            ]],
            'state' => [
                'values' => [
                    'question_'.$firstQuestion->id => [
                        'answer' => ['value' => 'Finished the Slack flow'],
                    ],
                ],
            ],
        ], 'test-signing-secret')
            ->assertOk()
            ->assertJson([
                'replace_original' => true,
                'text' => 'Answer saved.',
            ]);

        $this->assertDatabaseHas('standup_answers', [
            'standup_question_id' => $firstQuestion->id,
            'answer_text' => 'Finished the Slack flow',
        ]);
        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'D123'
            && str_contains($request['text'], $secondQuestion->question_text)
            && $request['blocks'][2]['elements'][0]['action_id'] === 'submit_standup_answer');
    }

    public function test_question_flow_skips_dependent_question_when_answer_does_not_match(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $blockedQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'Are you blocked?',
            'answer_type' => 'boolean',
            'sort_order' => 1,
        ]);
        $blockerDetailsQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you blocked by?',
            'depends_on_question_id' => $blockedQuestion->id,
            'depends_on_operator' => 'equals',
            'depends_on_value' => 'Yes',
            'sort_order' => 2,
        ]);
        $nextQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What is next?',
            'sort_order' => 3,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create(['status' => 'active']);
        $response = $run->responses()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'slack',
        ]);

        $response->answers()->create([
            'standup_question_id' => $blockedQuestion->id,
            'answer_text' => 'No',
        ]);

        $this->assertSame(
            $nextQuestion->id,
            app(StandupQuestionFlowService::class)
                ->nextQuestionForResponse($run->load('template.questions'), $response->load('answers'))
                ?->id,
        );

        $response->answers()->where('standup_question_id', $blockedQuestion->id)->update([
            'answer_text' => 'Yes',
        ]);

        $this->assertSame(
            $blockerDetailsQuestion->id,
            app(StandupQuestionFlowService::class)
                ->nextQuestionForResponse($run->load('template.questions'), $response->load('answers'))
                ?->id,
        );
    }

    public function test_slack_message_last_answer_marks_response_submitted(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you focusing on today?',
            'sort_order' => 1,
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'external_id' => 'T123',
            'bot_token' => 'xoxb-submit',
            'signing_secret' => 'test-signing-secret',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $run->responses()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'slack',
            'raw_json' => ['current_question_id' => $question->id],
        ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '456.789']),
        ]);

        $this->postSignedSlackPayload([
            'type' => 'block_actions',
            'team' => ['id' => 'T123'],
            'user' => ['id' => 'U123'],
            'channel' => ['id' => 'D123'],
            'message' => ['ts' => '123.456'],
            'actions' => [[
                'action_id' => 'submit_standup_answer',
                'value' => json_encode([
                    'run_id' => $run->id,
                    'question_id' => $question->id,
                ]),
            ]],
            'state' => [
                'values' => [
                    'question_'.$question->id => [
                        'answer' => ['value' => 'Working on summaries'],
                    ],
                ],
            ],
        ], 'test-signing-secret')
            ->assertOk()
            ->assertJson([
                'response_type' => 'ephemeral',
                'replace_original' => false,
                'text' => 'Standup submitted. Thank you.',
            ]);

        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'source' => 'slack',
        ]);
        $this->assertDatabaseHas('standup_answers', [
            'standup_question_id' => $question->id,
            'answer_text' => 'Working on summaries',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
            && $request['channel'] === 'D123'
            && $request['ts'] === '123.456'
            && str_contains($request['blocks'][1]['text']['text'], 'Working on summaries'));
        Http::assertNotSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['text'] === 'Thank you, your standup has been submitted.');
        $this->assertSame('completed', $run->refresh()->status);
    }

    public function test_opening_new_run_completes_older_active_runs_for_same_schedule(): void
    {
        Notification::fake();
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        StandupQuestion::factory()->for($template)->create(['sort_order' => 1]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'email',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $oldRun = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $newRun = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'run_date' => now()->addDay()->toDateString(),
                'status' => 'scheduled',
            ]);

        app(StandupMessageService::class)->openRun($newRun);

        $this->assertSame('completed', $oldRun->refresh()->status);
        $this->assertSame('active', $newRun->refresh()->status);
    }

    public function test_slack_message_answer_for_submitted_response_is_rejected_as_closed(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you focusing on today?',
            'sort_order' => 1,
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'external_id' => 'T123',
            'bot_token' => 'xoxb-submit',
            'signing_secret' => 'test-signing-secret',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'completed',
            ]);
        $response = $run->responses()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'source' => 'slack',
            'submitted_at' => now(),
            'raw_json' => ['current_question_id' => null],
        ]);
        $response->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Original answer',
        ]);

        Http::fake();

        $this->postSignedSlackPayload([
            'type' => 'block_actions',
            'team' => ['id' => 'T123'],
            'user' => ['id' => 'U123'],
            'channel' => ['id' => 'D123'],
            'message' => ['ts' => '123.456'],
            'actions' => [[
                'action_id' => 'submit_standup_answer',
                'value' => json_encode([
                    'run_id' => $run->id,
                    'question_id' => $question->id,
                ]),
            ]],
            'state' => [
                'values' => [
                    'question_'.$question->id => [
                        'answer' => ['value' => 'Stale answer'],
                    ],
                ],
            ],
        ], 'test-signing-secret')
            ->assertOk()
            ->assertJson([
                'replace_original' => true,
                'text' => 'This standup is closed. Please answer the latest standup prompt.',
            ]);

        $this->assertDatabaseHas('standup_answers', [
            'standup_response_id' => $response->id,
            'standup_question_id' => $question->id,
            'answer_text' => 'Original answer',
        ]);
        $this->assertDatabaseMissing('standup_answers', [
            'standup_response_id' => $response->id,
            'standup_question_id' => $question->id,
            'answer_text' => 'Stale answer',
        ]);
        Http::assertNothingSent();
    }

    public function test_slack_message_edit_updates_answer_without_advancing_question(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $firstQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What did you finish?',
            'sort_order' => 1,
        ]);
        $secondQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What is next?',
            'sort_order' => 2,
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'external_id' => 'T123',
            'bot_token' => 'xoxb-submit',
            'signing_secret' => 'test-signing-secret',
        ]);
        IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'slack_dm',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $standupResponse = $run->responses()->create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'slack',
            'raw_json' => ['current_question_id' => $secondQuestion->id],
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $firstQuestion->id,
            'answer_text' => 'Old answer',
            'answer_json' => [
                'slack_message_ts' => '111.222',
                'slack_channel_id' => 'D123',
            ],
        ]);

        Http::fake();

        $this->postSignedSlackPayload([
            'type' => 'block_actions',
            'team' => ['id' => 'T123'],
            'user' => ['id' => 'U123'],
            'channel' => ['id' => 'D123'],
            'message' => ['ts' => '111.222'],
            'actions' => [[
                'action_id' => 'submit_standup_answer',
                'value' => json_encode([
                    'run_id' => $run->id,
                    'question_id' => $firstQuestion->id,
                ]),
            ]],
            'state' => [
                'values' => [
                    'question_'.$firstQuestion->id => [
                        'answer' => ['value' => 'Updated first answer'],
                    ],
                ],
            ],
        ], 'test-signing-secret')->assertOk();

        $this->assertDatabaseHas('standup_answers', [
            'standup_response_id' => $standupResponse->id,
            'standup_question_id' => $firstQuestion->id,
            'answer_text' => 'Updated first answer',
        ]);
        $this->assertSame(
            $secondQuestion->id,
            $standupResponse->refresh()->raw_json['current_question_id'],
        );
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
            && $request['channel'] === 'D123'
            && $request['ts'] === '111.222'
            && str_contains($request['blocks'][1]['text']['text'], 'Updated first answer'));
        Http::assertNotSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage');
    }

    public function test_post_summary_job_posts_to_discord_webhook(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'today',
            'question_text' => 'What are you focusing on today?',
        ]);
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'discord',
            'name' => 'Discord',
            'webhook_url' => 'https://discord.com/api/webhooks/123/token',
            'bot_token' => null,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'discord_channel',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'active',
            ]);
        $standupResponse = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'source' => 'web',
        ]);
        $standupResponse->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Shipping Discord delivery',
        ]);

        Http::fake([
            'discord.com/api/webhooks/*' => Http::response(['id' => 'discord-message-id']),
        ]);

        (new PostStandupSummaryJob($run->id))->handle(app(StandupSummaryService::class));

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://discord.com/api/webhooks/123/token')
            && $request['content']
            && str_contains($request['content'], 'Shipping Discord delivery'));
        $this->assertDatabaseHas('standup_summaries', [
            'standup_run_id' => $run->id,
            'posted_to_integration_id' => $integration->id,
            'posted_to_channel_id' => null,
            'external_message_id' => 'discord-message-id',
        ]);
    }

    public function test_open_run_creates_pending_responses_only_for_selected_schedule_recipients(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $selectedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $unselectedMember = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($selectedMember, ['role' => 'member']);
        $organization->users()->attach($unselectedMember, ['role' => 'member']);
        $workspace->users()->attach($selectedMember, ['role' => 'member', 'is_active' => true]);
        $workspace->users()->attach($unselectedMember, ['role' => 'member', 'is_active' => true]);

        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Selected Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'selected_members',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $schedule->participants()->attach($selectedMember->id);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        app(StandupMessageService::class)->openRun($run);

        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $selectedMember->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $unselectedMember->id,
        ]);
    }

    public function test_standup_show_lists_only_run_recipients_not_all_workspace_members(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $selectedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $unselectedMember = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($selectedMember, ['role' => 'member']);
        $organization->users()->attach($unselectedMember, ['role' => 'member']);
        $workspace->users()->attach($selectedMember, ['role' => 'member', 'is_active' => true]);
        $workspace->users()->attach($unselectedMember, ['role' => 'member', 'is_active' => true]);

        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Selected Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'selected_members',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $schedule->participants()->attach($selectedMember->id);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        app(StandupMessageService::class)->openRun($run);

        $this->actingAs($user)
            ->get(route('standups.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('standups/show')
                ->has('members', 1)
                ->where('members.0.id', $selectedMember->id)
                ->has('run.responses', 1)
                ->where('run.responses.0.user_id', $selectedMember->id));
    }

    public function test_dashboard_uses_run_recipients_not_all_workspace_members(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $selectedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $unselectedMember = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($selectedMember, ['role' => 'member']);
        $organization->users()->attach($unselectedMember, ['role' => 'member']);
        $workspace->users()->attach($selectedMember, ['role' => 'member', 'is_active' => true]);
        $workspace->users()->attach($unselectedMember, ['role' => 'member', 'is_active' => true]);

        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Selected Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'selected_members',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $schedule->participants()->attach($selectedMember->id);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'run_date' => now($organization->timezone)->toDateString(),
                'status' => 'scheduled',
            ]);

        app(StandupMessageService::class)->openRun($run);

        $run->responses()
            ->where('user_id', $selectedMember->id)
            ->update(['status' => 'skipped', 'submitted_at' => now()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('todayRun.responses', 1)
                ->where('todayRun.responses.0.user_id', $selectedMember->id)
                ->where('todayRun.workspace.id', $workspace->id)
                ->where('stats.today.hasRun', true)
                ->where('stats.today.submitted', 0)
                ->where('stats.today.skipped', 1)
                ->where('stats.today.expected', 1)
                ->where('stats.today.pending', 0)
                ->where('stats.today.completionRate', 100)
                ->where('stats.week.runs', 1)
                ->where('stats.week.submitted', 0)
                ->where('stats.week.skipped', 1)
                ->where('stats.week.expected', 1)
                ->where('stats.week.pending', 0)
                ->where('stats.week.completionRate', 100));
    }

    public function test_open_run_posts_prompt_to_discord_webhook(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $integration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'discord',
            'name' => 'Discord',
            'webhook_url' => 'https://discord.com/api/webhooks/123/token',
            'bot_token' => null,
        ]);
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Discord Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'discord_channel',
            'target_integration_id' => $integration->id,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        Http::fake([
            'discord.com/api/webhooks/*' => Http::response(['id' => 'discord-message-id']),
        ]);

        app(StandupMessageService::class)->openRun($run);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://discord.com/api/webhooks/123/token')
            && str_contains($request['content'], "Submit today's standup"));
    }

    public function test_open_run_skips_inactive_workspace_members(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $inactiveMember = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($inactiveMember, ['role' => 'member']);
        $workspace->users()->attach($inactiveMember, ['role' => 'member', 'is_active' => false]);

        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'All Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'all_workspace_members',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'standup_schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

        app(StandupMessageService::class)->openRun($run);

        $this->assertDatabaseHas('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('standup_responses', [
            'standup_run_id' => $run->id,
            'user_id' => $inactiveMember->id,
        ]);
    }

    public function test_user_can_skip_pending_standup(): void
    {
        [$user, $organization, $workspace, $template] = $this->workspaceWithTemplate();
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create([
                'status' => 'active',
            ]);
        $response = StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'web',
        ]);
        $question = StandupQuestion::factory()->for($template)->create();
        $response->answers()->create([
            'standup_question_id' => $question->id,
            'answer_text' => 'Draft answer',
        ]);

        $this->actingAs($user)
            ->post(route('standup-runs.skip', $run), ['reason' => 'Out sick'])
            ->assertRedirect(route('standups.show', $run));

        $this->assertDatabaseHas('standup_responses', [
            'id' => $response->id,
            'status' => 'skipped',
            'source' => 'web',
        ]);
        $this->assertSame('Out sick', $response->refresh()->raw_json['skip_reason']);
        $this->assertDatabaseMissing('standup_answers', [
            'standup_response_id' => $response->id,
            'answer_text' => 'Draft answer',
        ]);
        $this->assertSame('completed', $run->refresh()->status);
        $this->assertDatabaseHas('activity_events', [
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'event_type' => ActivityEventService::STANDUP_RESPONSE_SKIPPED,
        ]);
    }

    private function workspaceWithTemplate(): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($user, ['role' => 'admin']);
        $workspace->users()->attach($user, ['role' => 'manager', 'is_active' => true]);
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($user, 'creator')
            ->create();

        return [$user, $organization, $workspace, $template];
    }

    private function postSignedSlackPayload(array $payload, string $signingSecret)
    {
        $body = 'payload='.urlencode(json_encode($payload));
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, $signingSecret);

        return $this->call('POST', route('slack.interactions'), [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => $signature,
        ], $body);
    }

    private function postSignedSlackEvent(array $payload, string $signingSecret)
    {
        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, $signingSecret);

        return $this->call('POST', route('slack.events'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => $signature,
        ], $body);
    }
}
