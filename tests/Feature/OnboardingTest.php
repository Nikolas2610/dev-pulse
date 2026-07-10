<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_can_bootstrap_an_organization_workspace_and_default_template(): void
    {
        $user = User::factory()->create([
            'current_organization_id' => null,
            'current_workspace_id' => null,
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.organization.store'), [
            'organization_name' => 'DevPulse Labs',
            'workspace_name' => 'Platform',
            'timezone' => 'Asia/Nicosia',
        ]);

        $response->assertRedirect(route('onboarding.index', ['step' => 'integrations']));
        $user->refresh();
        $organization = $user->currentOrganization;
        $workspace = $user->currentWorkspace;

        $this->assertNotNull($organization);
        $this->assertNotNull($workspace);
        $this->assertSame('admin', $organization->users()->whereKey($user->id)->first()->pivot->role);
        $this->assertSame('manager', $workspace->users()->whereKey($user->id)->first()->pivot->role);
        $this->assertSame('integrations', $organization->onboarding_step);
        $this->assertNull($organization->onboarding_completed_at);

        $template = $workspace->templates()->where('is_default', true)->firstOrFail();
        $this->assertSame('Daily Standup', $template->name);
        $this->assertCount(4, $template->questions);
        $this->assertNotNull($template->questions->last()->depends_on_question_id);
    }

    public function test_incomplete_admin_is_redirected_back_to_onboarding_but_existing_organizations_are_complete(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'onboarding_step' => 'members',
            'onboarding_completed_at' => null,
        ]);
        $workspace = $organization->workspaces()->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
            'timezone' => 'Asia/Nicosia',
            'is_active' => true,
        ]);
        $organization->users()->attach($user->id, ['role' => 'admin']);
        $workspace->users()->attach($user->id, ['role' => 'manager', 'is_active' => true]);
        $user->update(['current_organization_id' => $organization->id, 'current_workspace_id' => $workspace->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.index'));

        $organization->update(['onboarding_step' => 'complete', 'onboarding_completed_at' => now()]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_admin_can_skip_optional_steps_invite_members_and_complete_onboarding(): void
    {
        Notification::fake();
        [$user, $organization, $workspace, $template] = $this->onboardingContext();

        $this->actingAs($user)->post(route('onboarding.integrations.continue'))->assertRedirect();
        $this->actingAs($user)->post(route('onboarding.members.store'), [
            'invitations' => [[
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'organization_role' => 'member',
                'workspace_role' => 'member',
            ]],
        ])->assertRedirect();

        $invited = User::query()->where('email', 'ada@example.com')->firstOrFail();
        Notification::assertSentTo($invited, TeamInvitationNotification::class);

        $questions = $template->questions->values()->map(fn ($question, int $index) => [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'question_key' => $question->question_key,
            'answer_type' => $question->answer_type,
            'options_json' => $question->options_json,
            'depends_on_question_index' => $index === 3 ? 2 : null,
            'depends_on_operator' => $index === 3 ? 'equals' : null,
            'depends_on_value' => $index === 3 ? 'Yes' : null,
            'is_required' => true,
            'sort_order' => $index + 1,
        ])->all();

        $this->actingAs($user)->patch(route('onboarding.template.update'), [
            'name' => 'Daily Standup',
            'description' => 'Our daily pulse.',
            'questions' => $questions,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('onboarding.schedule.store'), [
            'name' => 'Daily Focus',
            'timezone' => 'Asia/Nicosia',
            'time_of_day' => '08:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'delivery_method' => 'email',
            'target_integration_id' => null,
            'prompt_channel_id' => null,
            'target_channel_id' => null,
            'reminder_minutes_after' => 15,
            'summary_minutes_after' => 30,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('standup_schedules', [
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Daily Focus',
            'delivery_method' => 'email',
        ]);
        $this->assertNotNull($organization->refresh()->onboarding_completed_at);
    }

    /** @return array{User, Organization, Workspace, StandupTemplate} */
    private function onboardingContext(): array
    {
        $user = User::factory()->create(['current_organization_id' => null, 'current_workspace_id' => null]);
        $this->actingAs($user)->post(route('onboarding.organization.store'), [
            'organization_name' => 'DevPulse Labs',
            'workspace_name' => 'Platform',
            'timezone' => 'Asia/Nicosia',
        ]);
        $user->refresh();
        $organization = $user->currentOrganization;
        $workspace = $user->currentWorkspace;
        $template = $workspace->templates()->with('questions')->where('is_default', true)->firstOrFail();

        return [$user, $organization, $workspace, $template];
    }
}
