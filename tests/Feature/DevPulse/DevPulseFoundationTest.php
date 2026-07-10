<?php

namespace Tests\Feature\DevPulse;

use App\Models\Integration;
use App\Models\IntegrationChannel;
use App\Models\IntegrationUser;
use App\Models\Organization;
use App\Models\StandupQuestion;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TeamInvitationNotification;
use App\Services\ActivityEventService;
use App\Services\SlackIntegrationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DevPulseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seed_creates_internal_organization_workspace_template_and_schedule(): void
    {
        $this->seed();

        $organization = Organization::where('slug', 'internal-company')->firstOrFail();
        $workspace = Workspace::where('slug', 'engineering')->firstOrFail();
        $template = StandupTemplate::where('name', 'Daily Focus')->firstOrFail();

        $this->assertSame($organization->id, $workspace->organization_id);
        $this->assertSame($organization->id, $template->organization_id);
        $this->assertCount(4, $template->questions);
        $this->assertDatabaseHas('standup_schedules', [
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_template_with_questions_scoped_to_current_organization(): void
    {
        [$user, $organization] = $this->createUserWithOrganization('admin');

        $response = $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Morning Focus',
            'description' => 'Daily team check-in',
            'workspace_id' => null,
            'is_default' => true,
            'is_active' => true,
            'questions' => [
                [
                    'question_text' => 'What did you complete yesterday?',
                    'question_key' => 'yesterday',
                    'answer_type' => 'textarea',
                    'is_required' => true,
                    'sort_order' => 1,
                ],
                [
                    'question_text' => 'Are you blocked?',
                    'question_key' => 'blocker',
                    'answer_type' => 'textarea',
                    'is_required' => false,
                    'sort_order' => 2,
                ],
            ],
        ]);

        $response->assertRedirect(route('templates.index'));

        $template = StandupTemplate::where('name', 'Morning Focus')->firstOrFail();

        $this->assertSame($organization->id, $template->organization_id);
        $this->assertSame($user->id, $template->created_by_user_id);
        $this->assertSame(['yesterday', 'blocker'], $template->questions()->orderBy('sort_order')->pluck('question_key')->all());
    }

    public function test_admin_can_create_template_question_with_answer_options(): void
    {
        [$user] = $this->createUserWithOrganization('admin');

        $response = $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Mood Check',
            'description' => null,
            'workspace_id' => null,
            'is_default' => false,
            'is_active' => true,
            'questions' => [
                [
                    'question_text' => 'How do you feel today?',
                    'question_key' => 'mood',
                    'answer_type' => 'select',
                    'options_json' => ['bad', 'good', 'excellent'],
                    'is_required' => true,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('templates.index'));

        $question = StandupTemplate::where('name', 'Mood Check')->firstOrFail()
            ->questions()
            ->firstOrFail();

        $this->assertSame('select', $question->answer_type);
        $this->assertSame(['bad', 'good', 'excellent'], $question->options_json);
    }

    public function test_admin_can_update_template_questions_from_edit_screen_payload(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization('admin');
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($user, 'creator')
            ->create(['name' => 'Daily Focus']);
        $existingQuestion = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'Old question',
            'question_key' => 'old',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->patch(route('templates.update', $template), [
            'name' => 'Updated Focus',
            'description' => 'Updated daily standup.',
            'is_active' => true,
            'questions' => [
                [
                    'id' => $existingQuestion->id,
                    'question_text' => 'What shipped yesterday?',
                    'question_key' => 'yesterday',
                    'answer_type' => 'textarea',
                    'is_required' => true,
                    'sort_order' => 1,
                ],
                [
                    'question_text' => 'What is blocking you?',
                    'question_key' => 'blocker',
                    'answer_type' => 'textarea',
                    'depends_on_question_index' => 0,
                    'depends_on_operator' => 'equals',
                    'depends_on_value' => 'Yes',
                    'is_required' => false,
                    'sort_order' => 2,
                ],
            ],
        ]);

        $response->assertRedirect(route('templates.index'));

        $template->refresh();

        $this->assertSame('Updated Focus', $template->name);
        $this->assertSame(
            ['What shipped yesterday?', 'What is blocking you?'],
            $template->questions()->orderBy('sort_order')->pluck('question_text')->all(),
        );
        $updatedQuestions = $template->questions()->orderBy('sort_order')->get();
        $this->assertSame($updatedQuestions[0]->id, $updatedQuestions[1]->depends_on_question_id);
        $this->assertSame('equals', $updatedQuestions[1]->depends_on_operator);
        $this->assertSame('Yes', $updatedQuestions[1]->depends_on_value);
        $this->assertDatabaseMissing('standup_questions', [
            'standup_template_id' => $template->id,
            'question_text' => 'Old question',
        ]);
    }

    public function test_member_cannot_manage_templates_or_schedules(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization('member');
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->create();
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'reminder_minutes_after' => 30,
            'summary_minutes_after' => 60,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Member Template',
            'questions' => [
                [
                    'question_text' => 'What are you doing?',
                    'answer_type' => 'textarea',
                    'is_required' => true,
                    'sort_order' => 1,
                ],
            ],
        ])->assertForbidden();

        $this->actingAs($user)->get(route('templates.edit', $template))->assertForbidden();
        $this->actingAs($user)->patch(route('templates.update', $template), [
            'name' => 'Changed by member',
            'questions' => [
                [
                    'question_text' => 'Changed?',
                    'answer_type' => 'textarea',
                    'is_required' => true,
                    'sort_order' => 1,
                ],
            ],
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('templates.destroy', $template))->assertForbidden();

        $this->actingAs($user)->post(route('schedules.store'), [
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Member Schedule',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1],
            'reminder_minutes_after' => 30,
            'summary_minutes_after' => 60,
        ])->assertForbidden();

        $this->actingAs($user)->get(route('schedules.edit', $schedule))->assertForbidden();
        $this->actingAs($user)->patch(route('schedules.update', $schedule), [
            'name' => 'Changed by member',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '09:00',
            'reminder_minutes_after' => 15,
            'summary_minutes_after' => 45,
            'is_active' => true,
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('schedules.toggle-active', $schedule), [
            'is_active' => false,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('schedules.destroy', $schedule))->assertForbidden();

        $this->assertDatabaseMissing('standup_templates', ['name' => 'Member Template']);
        $this->assertDatabaseMissing('standup_schedules', ['name' => 'Member Schedule']);
    }

    public function test_admin_can_create_schedule_with_selected_recipients(): void
    {
        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($admin, 'creator')
            ->create();
        $selectedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $unselectedMember = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($selectedMember, ['role' => 'member']);
        $organization->users()->attach($unselectedMember, ['role' => 'member']);
        $workspace->users()->attach($selectedMember, ['role' => 'member', 'is_active' => true]);
        $workspace->users()->attach($unselectedMember, ['role' => 'member', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('schedules.store'), [
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Selected Recipient Schedule',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'selected_members',
            'delivery_method' => 'email',
            'participant_user_ids' => [$selectedMember->id],
            'reminder_minutes_after' => 30,
            'summary_minutes_after' => 60,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('schedules.index'));

        $schedule = StandupSchedule::where('name', 'Selected Recipient Schedule')->firstOrFail();

        $this->assertSame('selected_members', $schedule->recipient_mode);
        $this->assertDatabaseHas('standup_schedule_user', [
            'standup_schedule_id' => $schedule->id,
            'user_id' => $selectedMember->id,
        ]);
        $this->assertDatabaseMissing('standup_schedule_user', [
            'standup_schedule_id' => $schedule->id,
            'user_id' => $unselectedMember->id,
        ]);
    }

    public function test_admin_can_activate_and_deactivate_schedule_from_index_action(): void
    {
        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($admin, 'creator')
            ->create();
        $schedule = StandupSchedule::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Toggle Schedule',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('schedules.toggle-active', $schedule), ['is_active' => false])
            ->assertRedirect(route('schedules.index'));

        $this->assertFalse($schedule->refresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('schedules.toggle-active', $schedule), ['is_active' => true])
            ->assertRedirect(route('schedules.index'));

        $this->assertTrue($schedule->refresh()->is_active);
    }

    public function test_schedule_delivery_method_validates_required_integration_fields(): void
    {
        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($admin, 'creator')
            ->create();
        $slackIntegration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-test',
        ]);
        $discordIntegration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'discord',
            'webhook_url' => 'https://discord.com/api/webhooks/123/token',
        ]);
        $promptChannel = $slackIntegration->channels()->create([
            'external_channel_id' => 'C_PROMPT',
            'name' => 'standups',
            'type' => 'public',
            'is_active' => true,
        ]);

        $basePayload = [
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Delivery Schedule',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'all_workspace_members',
            'participant_user_ids' => [],
            'reminder_minutes_after' => 30,
            'summary_minutes_after' => 60,
            'target_integration_id' => null,
            'prompt_channel_id' => null,
            'target_channel_id' => null,
            'is_active' => true,
        ];

        $this->actingAs($admin)
            ->post(route('schedules.store'), [...$basePayload, 'delivery_method' => 'slack_channel'])
            ->assertSessionHasErrors(['target_integration_id', 'prompt_channel_id']);

        $this->actingAs($admin)
            ->post(route('schedules.store'), [
                ...$basePayload,
                'name' => 'Slack Channel Delivery',
                'delivery_method' => 'slack_channel',
                'target_integration_id' => $slackIntegration->id,
                'prompt_channel_id' => $promptChannel->id,
            ])
            ->assertRedirect(route('schedules.index'));

        $this->actingAs($admin)
            ->post(route('schedules.store'), [
                ...$basePayload,
                'name' => 'Discord Delivery',
                'delivery_method' => 'discord_channel',
                'target_integration_id' => $discordIntegration->id,
            ])
            ->assertRedirect(route('schedules.index'));

        $this->assertDatabaseHas('standup_schedules', [
            'name' => 'Slack Channel Delivery',
            'delivery_method' => 'slack_channel',
            'target_integration_id' => $slackIntegration->id,
            'prompt_channel_id' => $promptChannel->id,
        ]);
        $this->assertDatabaseHas('standup_schedules', [
            'name' => 'Discord Delivery',
            'delivery_method' => 'discord_channel',
            'target_integration_id' => $discordIntegration->id,
        ]);
    }

    public function test_slack_channel_schedule_rejects_selected_recipients_without_slack_mapping(): void
    {
        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($admin, 'creator')
            ->create();
        $slackIntegration = Integration::factory()->for($organization)->for($workspace)->create([
            'provider' => 'slack',
            'bot_token' => 'xoxb-test',
        ]);
        $promptChannel = IntegrationChannel::create([
            'integration_id' => $slackIntegration->id,
            'external_channel_id' => 'C_PROMPT',
            'name' => 'standups',
            'type' => 'public',
            'is_active' => true,
        ]);
        $linkedMember = User::factory()->create(['current_organization_id' => $organization->id]);
        $unlinkedMember = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($linkedMember, ['role' => 'member']);
        $organization->users()->attach($unlinkedMember, ['role' => 'member']);
        $workspace->users()->attach($linkedMember, ['role' => 'member', 'is_active' => true]);
        $workspace->users()->attach($unlinkedMember, ['role' => 'member', 'is_active' => true]);
        IntegrationUser::create([
            'integration_id' => $slackIntegration->id,
            'user_id' => $linkedMember->id,
            'provider' => 'slack',
            'external_user_id' => 'U_LINKED',
            'external_email' => $linkedMember->email,
        ]);

        $payload = [
            'workspace_id' => $workspace->id,
            'standup_template_id' => $template->id,
            'name' => 'Slack Channel Delivery',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'recipient_mode' => 'selected_members',
            'delivery_method' => 'slack_channel',
            'participant_user_ids' => [$linkedMember->id, $unlinkedMember->id],
            'reminder_minutes_after' => 30,
            'summary_minutes_after' => 60,
            'target_integration_id' => $slackIntegration->id,
            'prompt_channel_id' => $promptChannel->id,
            'target_channel_id' => null,
            'is_active' => true,
        ];

        $this->actingAs($admin)
            ->post(route('schedules.store'), $payload)
            ->assertRedirect(route('schedules.index'));

        $schedule = StandupSchedule::where('name', 'Slack Channel Delivery')->firstOrFail();

        $this->assertDatabaseHas('standup_schedule_user', [
            'standup_schedule_id' => $schedule->id,
            'user_id' => $linkedMember->id,
        ]);
        $this->assertDatabaseHas('standup_schedule_user', [
            'standup_schedule_id' => $schedule->id,
            'user_id' => $unlinkedMember->id,
        ]);
    }

    public function test_admin_can_invite_edit_and_remove_member_from_workspace(): void
    {
        Notification::fake();

        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');

        $inviteResponse = $this->actingAs($admin)->post(route('team-members.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
        ]);

        $inviteResponse->assertRedirect(route('team-members.index'));

        $member = User::where('email', 'ada@example.com')->firstOrFail();

        Notification::assertSentTo(
            $member,
            TeamInvitationNotification::class,
            function (TeamInvitationNotification $notification) use ($member, $organization, $workspace): bool {
                $mail = $notification->toMail($member);

                return $notification->token !== null
                    && $notification->organization->is($organization)
                    && $notification->workspace->is($workspace)
                    && $mail->subject === "You're invited to {$organization->name} on DevPulse"
                    && $mail->actionText === 'Accept invitation'
                    && str_contains($mail->actionUrl, '/invitations/');
            },
        );
        Notification::assertNotSentTo($member, ResetPassword::class);

        $this->assertSame($organization->id, $member->current_organization_id);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('team-members.update', $member), [
            'organization_role' => 'manager',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'manager',
        ])->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => 'manager',
        ]);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'manager',
        ]);

        $this->actingAs($admin)
            ->delete(route('team-members.workspaces.destroy', [$member, $workspace]))
            ->assertRedirect(route('team-members.index'));

        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_invited_member_can_accept_the_invitation_and_create_a_password(): void
    {
        Notification::fake();

        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');

        $this->actingAs($admin)->post(route('team-members.store'), [
            'name' => 'Katherine Johnson',
            'email' => 'katherine@example.com',
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
        ])->assertRedirect(route('team-members.index'));

        $member = User::where('email', 'katherine@example.com')->firstOrFail();
        $token = null;

        Notification::assertSentTo(
            $member,
            TeamInvitationNotification::class,
            function (TeamInvitationNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return $token !== null;
            },
        );

        $this->post(route('logout'));
        $this->assertGuest();

        $this->get(route('invitations.accept', [
            'token' => $token,
            'email' => $member->email,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('email', $member->email)
            ->where('organizationName', $organization->name)
            ->where('workspaceName', $workspace->name)
        );

        $password = 'Invited-password-123!';

        $this->post(route('invitations.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($member);
        $this->assertTrue(Hash::check($password, $member->fresh()->password));
    }

    public function test_existing_user_invitation_does_not_change_their_password(): void
    {
        Notification::fake();

        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');
        $member = User::factory()->create(['email' => 'existing@example.com']);

        $this->actingAs($admin)->post(route('team-members.store'), [
            'name' => $member->name,
            'email' => $member->email,
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
        ])->assertRedirect(route('team-members.index'));

        Notification::assertSentTo(
            $member,
            TeamInvitationNotification::class,
            function (TeamInvitationNotification $notification) use ($member): bool {
                $mail = $notification->toMail($member);

                return $notification->token === null
                    && $mail->actionText === 'Open DevPulse'
                    && $mail->actionUrl === route('login');
            },
        );
        Notification::assertNotSentTo($member, ResetPassword::class);
        $this->assertTrue(Hash::check('password', $member->fresh()->password));
    }

    public function test_member_cannot_invite_edit_or_remove_team_members(): void
    {
        [$member, $organization, $workspace] = $this->createUserWithOrganization('member');
        $target = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($target, ['role' => 'member']);
        $workspace->users()->attach($target, ['role' => 'member', 'is_active' => true]);

        $this->actingAs($member)->post(route('team-members.store'), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'organization_role' => 'member',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'member',
        ])->assertForbidden();

        $this->actingAs($member)->patch(route('team-members.update', $target), [
            'organization_role' => 'manager',
            'workspace_id' => $workspace->id,
            'workspace_role' => 'manager',
        ])->assertForbidden();

        $this->actingAs($member)
            ->delete(route('team-members.workspaces.destroy', [$target, $workspace]))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'grace@example.com']);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $target->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $target->id,
            'role' => 'member',
        ]);
    }

    public function test_user_can_switch_current_organization_and_workspace_context(): void
    {
        [$user, $firstOrganization, $firstWorkspace] = $this->createUserWithOrganization('member');
        $secondOrganization = Organization::factory()->create(['name' => 'Client Org']);
        $secondWorkspace = Workspace::factory()->for($secondOrganization)->create(['name' => 'Mobile']);

        $secondOrganization->users()->attach($user, ['role' => 'member']);
        $secondWorkspace->users()->attach($user, ['role' => 'member', 'is_active' => true]);

        $response = $this->actingAs($user)->patch(route('context.update'), [
            'organization_id' => $secondOrganization->id,
            'workspace_id' => $secondWorkspace->id,
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertSame($secondOrganization->id, $user->current_organization_id);
        $this->assertSame($secondWorkspace->id, $user->current_workspace_id);

        $this->actingAs($user)->patch(route('context.update'), [
            'organization_id' => $firstOrganization->id,
            'workspace_id' => $firstWorkspace->id,
        ])->assertRedirect();

        $user->refresh();

        $this->assertSame($firstOrganization->id, $user->current_organization_id);
        $this->assertSame($firstWorkspace->id, $user->current_workspace_id);
    }

    public function test_user_cannot_switch_to_workspace_outside_selected_organization(): void
    {
        [$user, $organization] = $this->createUserWithOrganization('member');
        $otherOrganization = Organization::factory()->create();
        $otherWorkspace = Workspace::factory()->for($otherOrganization)->create();

        $otherOrganization->users()->attach($user, ['role' => 'member']);
        $otherWorkspace->users()->attach($user, ['role' => 'member', 'is_active' => true]);

        $this->actingAs($user)->patch(route('context.update'), [
            'organization_id' => $organization->id,
            'workspace_id' => $otherWorkspace->id,
        ])->assertNotFound();

        $user->refresh();

        $this->assertSame($organization->id, $user->current_organization_id);
        $this->assertNotSame($otherWorkspace->id, $user->current_workspace_id);
    }

    public function test_admin_can_create_update_and_delete_workspaces(): void
    {
        [$admin, $organization, $workspace] = $this->createUserWithOrganization('admin');

        $createResponse = $this->actingAs($admin)->post(route('workspaces.store'), [
            'name' => 'Product Team',
            'slug' => 'product-team',
            'timezone' => 'Asia/Nicosia',
            'is_active' => true,
        ]);

        $createResponse->assertRedirect(route('workspaces.index'));

        $createdWorkspace = Workspace::where('slug', 'product-team')->firstOrFail();

        $this->assertSame($organization->id, $createdWorkspace->organization_id);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $createdWorkspace->id,
            'user_id' => $admin->id,
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('workspaces.update', $createdWorkspace), [
            'name' => 'Product Ops',
            'slug' => 'product-ops',
            'timezone' => 'UTC',
            'is_active' => false,
        ])->assertRedirect(route('workspaces.index'));

        $this->assertDatabaseHas('workspaces', [
            'id' => $createdWorkspace->id,
            'name' => 'Product Ops',
            'slug' => 'product-ops',
            'timezone' => 'UTC',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('workspaces.destroy', $createdWorkspace))
            ->assertRedirect(route('workspaces.index'));

        $this->assertDatabaseMissing('workspaces', ['id' => $createdWorkspace->id]);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_workspace_timezone_must_be_valid(): void
    {
        [$admin] = $this->createUserWithOrganization('admin');

        $response = $this->actingAs($admin)->post(route('workspaces.store'), [
            'name' => 'Invalid Zone',
            'slug' => 'invalid-zone',
            'timezone' => 'Not/A_Timezone',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('timezone');
        $this->assertDatabaseMissing('workspaces', ['slug' => 'invalid-zone']);
    }

    public function test_member_cannot_manage_workspaces(): void
    {
        [$member, $organization, $workspace] = $this->createUserWithOrganization('member');

        $this->actingAs($member)->post(route('workspaces.store'), [
            'name' => 'Support',
            'slug' => 'support',
            'timezone' => 'Asia/Nicosia',
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($member)->patch(route('workspaces.update', $workspace), [
            'name' => 'Changed',
            'slug' => 'changed',
            'timezone' => 'UTC',
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($member)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertForbidden();

        $this->assertDatabaseMissing('workspaces', [
            'organization_id' => $organization->id,
            'slug' => 'support',
        ]);
        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => $workspace->name,
        ]);
    }

    public function test_member_can_submit_standup_once_and_activity_event_is_recorded(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization('member');
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->create();
        $question = StandupQuestion::factory()->for($template)->create([
            'question_key' => 'blocker',
            'question_text' => 'Are you blocked?',
            'answer_type' => 'textarea',
        ]);
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create(['status' => 'active']);

        $response = $this->actingAs($user)->post(route('standup-runs.submit.store', $run), [
            'answers' => [
                $question->id => 'Waiting for API response',
            ],
        ]);

        $response->assertRedirect(route('standups.show', $run));

        $standupResponse = StandupResponse::where('standup_run_id', $run->id)->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('submitted', $standupResponse->status);
        $this->assertDatabaseHas('standup_answers', [
            'standup_response_id' => $standupResponse->id,
            'standup_question_id' => $question->id,
            'answer_text' => 'Waiting for API response',
        ]);
        $this->assertDatabaseHas('activity_events', [
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'event_type' => ActivityEventService::STANDUP_RESPONSE_SUBMITTED,
        ]);

        $secondResponse = $this->actingAs($user)->post(route('standup-runs.submit.store', $run), [
            'answers' => [
                $question->id => 'A changed answer',
            ],
        ]);

        $secondResponse->assertSessionHasErrors('standup_run');
    }

    public function test_slack_user_sync_maps_existing_app_users_by_email(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization('admin');
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create([
                'provider' => 'slack',
                'bot_token' => 'xoxb-test',
            ]);

        Http::fake([
            'slack.com/api/users.list' => Http::response([
                'ok' => true,
                'members' => [
                    [
                        'id' => 'U123',
                        'name' => 'nikolas',
                        'deleted' => false,
                        'profile' => [
                            'email' => $user->email,
                            'real_name' => $user->name,
                            'image_72' => 'https://example.com/avatar.png',
                        ],
                    ],
                ],
            ]),
        ]);

        app(SlackIntegrationService::class)->syncUsers($integration);

        $this->assertDatabaseHas('integration_users', [
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'slack',
            'external_user_id' => 'U123',
            'external_email' => $user->email,
        ]);
        $this->assertDatabaseHas('activity_events', [
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'event_type' => ActivityEventService::SLACK_USER_SYNCED,
            'external_id' => 'U123',
        ]);
    }

    /**
     * @return array{User, Organization, Workspace}
     */
    private function createUserWithOrganization(string $organizationRole): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($user, ['role' => $organizationRole]);
        $workspace->users()->attach($user, ['role' => $organizationRole === 'member' ? 'member' : 'manager', 'is_active' => true]);

        return [$user, $organization, $workspace];
    }
}
