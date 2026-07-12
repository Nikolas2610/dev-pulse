<?php

namespace Tests\Feature\DevPulse;

use App\Jobs\SyncAtlassianIntegrationJob;
use App\Models\ExternalWorkItem;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\JiraProject;
use App\Models\Organization;
use App\Models\StandupQuestion;
use App\Models\StandupRun;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AtlassianIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_attach_visible_jira_item_and_snapshot_is_immutable(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        $template = StandupTemplate::factory()->for($organization)->for($workspace)->create(['created_by_user_id' => $user->id]);
        $question = StandupQuestion::create(['standup_template_id' => $template->id, 'question_text' => 'Today?', 'question_key' => 'today', 'answer_type' => 'textarea', 'is_required' => true, 'sort_order' => 1]);
        $run = StandupRun::factory()->for($organization)->for($workspace)->for($template)->create(['status' => 'active']);
        $integration = Integration::factory()->for($organization)->create(['workspace_id' => null, 'provider' => 'jira', 'settings_json' => ['cloud_id' => 'cloud-1', 'site_url' => 'https://example.atlassian.net']]);
        $identity = IntegrationUser::create(['integration_id' => $integration->id, 'user_id' => $user->id, 'provider' => 'jira', 'external_user_id' => 'account-1', 'display_name' => $user->name]);
        $project = JiraProject::create(['integration_id' => $integration->id, 'external_id' => '10000', 'project_key' => 'DEV', 'name' => 'Development']);
        $workspace->jiraProjects()->attach($project);
        $item = ExternalWorkItem::create(['integration_id' => $integration->id, 'workspace_id' => $workspace->id, 'jira_project_id' => $project->id, 'assignee_integration_user_id' => $identity->id, 'provider' => 'jira', 'external_id' => '10001', 'external_key' => 'DEV-1', 'type' => 'jira_issue', 'title' => 'Ship Jira integration', 'url' => 'https://example.atlassian.net/browse/DEV-1', 'status' => 'In Progress']);

        $this->actingAs($user)->get(route('standup-runs.submit', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('suggestedWorkItems', 1)->where('suggestedWorkItems.0.id', $item->id));

        $this->actingAs($user)->post(route('standup-runs.submit.store', $run), [
            'answers' => [$question->id => 'Working on the integration'],
            'work_items' => [['external_work_item_id' => $item->id, 'relation' => 'worked_on']],
        ])->assertRedirect();

        $this->assertDatabaseHas('standup_response_items', ['external_work_item_id' => $item->id, 'title_snapshot' => 'Ship Jira integration', 'relation' => 'worked_on']);
        $item->update(['title' => 'Renamed remotely']);
        $this->assertDatabaseHas('standup_response_items', ['external_work_item_id' => $item->id, 'title_snapshot' => 'Ship Jira integration']);
    }

    public function test_bitbucket_webhook_requires_valid_signature_and_is_deduplicated(): void
    {
        Queue::fake();
        [, $organization] = $this->workspaceUser();
        $integration = Integration::factory()->for($organization)->create(['workspace_id' => null, 'provider' => 'bitbucket', 'settings_json' => ['webhook_secret' => 'test-secret']]);
        $payload = json_encode(['pullrequest' => ['id' => 42]], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test-secret');

        $this->call('POST', route('atlassian.webhooks', ['provider' => 'bitbucket', 'integration' => $integration]), [], [], [], ['HTTP_X_HUB_SIGNATURE' => $signature, 'HTTP_X_REQUEST_UUID' => 'delivery-1', 'CONTENT_TYPE' => 'application/json'], $payload)->assertAccepted();
        $this->call('POST', route('atlassian.webhooks', ['provider' => 'bitbucket', 'integration' => $integration]), [], [], [], ['HTTP_X_HUB_SIGNATURE' => $signature, 'HTTP_X_REQUEST_UUID' => 'delivery-1', 'CONTENT_TYPE' => 'application/json'], $payload)->assertAccepted();

        Queue::assertPushed(SyncAtlassianIntegrationJob::class, 1);
    }

    /** @return array{User, Organization, Workspace} */
    private function workspaceUser(): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create(['email_verified_at' => now(), 'current_organization_id' => $organization->id, 'current_workspace_id' => $workspace->id]);
        $organization->users()->attach($user, ['role' => 'owner']);
        $workspace->users()->attach($user, ['role' => 'manager', 'is_active' => true]);

        return [$user, $organization, $workspace];
    }
}
