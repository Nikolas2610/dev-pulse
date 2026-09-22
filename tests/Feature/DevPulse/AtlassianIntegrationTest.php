<?php

namespace Tests\Feature\DevPulse;

use App\Jobs\SyncAtlassianIntegrationJob;
use App\Models\BitbucketRepository;
use App\Models\ExternalWorkItem;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\JiraProject;
use App\Models\Organization;
use App\Models\StandupQuestion;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\JiraIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AtlassianIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.atlassian_integrations' => true]);
    }

    public function test_jira_standup_metadata_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumn('standup_questions', 'work_item_relation'));
        $this->assertTrue(Schema::hasColumn('standup_questions', 'work_item_providers'));
        $this->assertTrue(Schema::hasColumn('jira_projects', 'statuses_json'));
        $this->assertTrue(Schema::hasColumn('workspace_jira_project', 'included_status_ids'));
        $this->assertTrue(Schema::hasColumn('external_work_items', 'external_status_id'));
        $this->assertTrue(Schema::hasColumn('standup_response_items', 'standup_question_id'));
    }

    public function test_user_can_attach_visible_jira_item_and_snapshot_is_immutable(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        $template = StandupTemplate::factory()->for($organization)->for($workspace)->create(['created_by_user_id' => $user->id]);
        $question = StandupQuestion::create(['standup_template_id' => $template->id, 'question_text' => 'Today?', 'question_key' => 'today', 'work_item_relation' => 'planned', 'work_item_providers' => ['jira'], 'answer_type' => 'textarea', 'is_required' => true, 'sort_order' => 1]);
        $yesterdayQuestion = StandupQuestion::create(['standup_template_id' => $template->id, 'question_text' => 'Yesterday?', 'question_key' => 'yesterday', 'work_item_relation' => 'worked_on', 'work_item_providers' => ['jira'], 'answer_type' => 'textarea', 'is_required' => false, 'sort_order' => 2]);
        $run = StandupRun::factory()->for($organization)->for($workspace)->for($template)->create(['status' => 'active']);
        StandupResponse::create(['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'standup_run_id' => $run->id, 'user_id' => $user->id, 'status' => 'pending']);
        $integration = Integration::factory()->for($organization)->create(['workspace_id' => null, 'provider' => 'jira', 'settings_json' => ['cloud_id' => 'cloud-1', 'site_url' => 'https://example.atlassian.net']]);
        $identity = IntegrationUser::create(['integration_id' => $integration->id, 'user_id' => $user->id, 'provider' => 'jira', 'external_user_id' => 'account-1', 'display_name' => $user->name]);
        $project = JiraProject::create(['integration_id' => $integration->id, 'external_id' => '10000', 'project_key' => 'DEV', 'name' => 'Development']);
        $workspace->jiraProjects()->attach($project);
        $item = ExternalWorkItem::create(['integration_id' => $integration->id, 'workspace_id' => $workspace->id, 'jira_project_id' => $project->id, 'assignee_integration_user_id' => $identity->id, 'provider' => 'jira', 'external_id' => '10001', 'external_key' => 'DEV-1', 'type' => 'jira_issue', 'title' => 'Ship Jira integration', 'url' => 'https://example.atlassian.net/browse/DEV-1', 'status' => 'In Progress']);

        $this->actingAs($user)->get(route('standup-runs.submit', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('suggestedWorkItems', 1)->where('suggestedWorkItems.0.id', $item->id));

        $this->actingAs($user)->post(route('standup-runs.submit.store', $run), [
            'answers' => [$question->id => 'Working on the integration', $yesterdayQuestion->id => 'Started it yesterday'],
            'work_items' => [$question->id => [$item->id], $yesterdayQuestion->id => [$item->id]],
        ])->assertRedirect();

        $this->assertDatabaseHas('standup_response_items', ['standup_question_id' => $question->id, 'external_work_item_id' => $item->id, 'title_snapshot' => 'Ship Jira integration', 'relation' => 'planned']);
        $this->assertDatabaseHas('standup_response_items', ['standup_question_id' => $yesterdayQuestion->id, 'external_work_item_id' => $item->id, 'relation' => 'worked_on']);
        $item->update(['title' => 'Renamed remotely']);
        $this->assertDatabaseHas('standup_response_items', ['external_work_item_id' => $item->id, 'title_snapshot' => 'Ship Jira integration']);

        $this->actingAs($user)->get(route('team-standups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('responses.data.0.answers.0.items.0.external_key', 'DEV-1')
                ->where('responses.data.0.answers.0.items.0.title', 'Ship Jira integration')
                ->where('responses.data.0.answers.0.items.0.status', 'In Progress'));
    }

    public function test_jira_project_sync_discovers_available_statuses(): void
    {
        [, $organization] = $this->workspaceUser();
        $integration = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
            'access_token' => 'jira-token',
            'settings_json' => ['cloud_id' => 'cloud-1'],
        ]);
        Http::fake([
            '*rest/api/3/project/search*' => Http::response([
                'values' => [['id' => '10000', 'key' => 'DEV', 'name' => 'Development']],
                'total' => 1,
            ]),
            '*rest/api/3/project/DEV/statuses*' => Http::response([
                ['id' => '1', 'name' => 'To Do', 'statusCategory' => ['key' => 'new']],
                ['id' => '2', 'name' => 'In Progress', 'statusCategory' => ['key' => 'indeterminate']],
                ['id' => '3', 'name' => 'Done', 'statusCategory' => ['key' => 'done']],
            ]),
        ]);

        app(JiraIntegrationService::class)->syncProjects($integration);

        $this->assertSame([
            ['id' => '1', 'name' => 'To Do', 'category_key' => 'new'],
            ['id' => '2', 'name' => 'In Progress', 'category_key' => 'indeterminate'],
            ['id' => '3', 'name' => 'Done', 'category_key' => 'done'],
        ], $integration->jiraProjects()->firstOrFail()->statuses_json);
    }

    public function test_jira_issue_sync_uses_workspace_status_filter(): void
    {
        [, $organization, $workspace] = $this->workspaceUser();
        $integration = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
            'access_token' => 'jira-token',
            'settings_json' => ['cloud_id' => 'cloud-1', 'site_url' => 'https://example.atlassian.net'],
        ]);
        $project = JiraProject::create([
            'integration_id' => $integration->id,
            'external_id' => '10000',
            'project_key' => 'DEV',
            'name' => 'Development',
            'statuses_json' => [
                ['id' => '2', 'name' => 'In Progress', 'category_key' => 'indeterminate'],
                ['id' => '7', 'name' => 'Blocked', 'category_key' => 'indeterminate'],
            ],
        ]);
        $workspace->jiraProjects()->attach($project, ['included_status_ids' => json_encode(['2', '7'])]);
        Http::fake(['*rest/api/3/search/jql*' => Http::response(['issues' => [], 'total' => 0])]);

        app(JiraIntegrationService::class)->syncIssues($integration, $project);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/rest/api/3/search/jql')
            && $request->data()['jql'] === 'project = "DEV" AND status IN ("2", "7") AND assignee IS NOT EMPTY');
    }

    public function test_admin_can_save_jira_status_filters_for_a_workspace_project(): void
    {
        Queue::fake();
        [$user, $organization, $workspace] = $this->workspaceUser();
        $integration = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);
        $project = JiraProject::create([
            'integration_id' => $integration->id,
            'external_id' => '10000',
            'project_key' => 'DEV',
            'name' => 'Development',
            'statuses_json' => [
                ['id' => '2', 'name' => 'In Progress', 'category_key' => 'indeterminate'],
                ['id' => '3', 'name' => 'Done', 'category_key' => 'done'],
            ],
        ]);

        $this->actingAs($user)->put(route('integrations.atlassian.resources', [
            'provider' => 'jira',
            'integration' => $integration,
        ]), [
            'workspace_id' => $workspace->id,
            'resource_ids' => [$project->id],
            'status_filters' => [(string) $project->id => ['2']],
        ])->assertRedirect();

        $this->assertDatabaseHas('workspace_jira_project', [
            'workspace_id' => $workspace->id,
            'jira_project_id' => $project->id,
            'included_status_ids' => json_encode(['2']),
        ]);
        Queue::assertPushed(SyncAtlassianIntegrationJob::class);
    }

    public function test_template_questions_persist_work_item_relations(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);
        Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'bitbucket',
        ]);

        $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Engineering daily',
            'workspace_id' => $workspace->id,
            'is_active' => true,
            'questions' => [[
                'question_text' => 'What are you focusing on today?',
                'question_key' => 'today',
                'work_item_relation' => 'planned',
                'work_item_providers' => ['jira', 'bitbucket'],
                'answer_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 1,
            ]],
        ])->assertRedirect(route('templates.index'));

        $this->assertDatabaseHas('standup_questions', [
            'question_key' => 'today',
            'work_item_relation' => 'planned',
            'work_item_providers' => json_encode(['jira', 'bitbucket']),
        ]);
    }

    public function test_template_editor_exposes_only_connected_work_item_providers(): void
    {
        [$user, $organization] = $this->workspaceUser();
        Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
            'is_active' => true,
        ]);
        Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'bitbucket',
            'is_active' => false,
        ]);

        $this->actingAs($user)->get(route('templates.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('availableWorkItemProviders', ['jira']));
    }

    public function test_template_rejects_work_item_provider_without_active_integration(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);

        $this->actingAs($user)->from(route('templates.create'))->post(route('templates.store'), [
            'name' => 'Invalid provider daily',
            'workspace_id' => $workspace->id,
            'is_active' => true,
            'questions' => [[
                'question_text' => 'What are you reviewing?',
                'question_key' => 'other',
                'work_item_relation' => 'review',
                'work_item_providers' => ['bitbucket'],
                'answer_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 1,
            ]],
        ])->assertRedirect(route('templates.create'))
            ->assertSessionHasErrors('questions.0.work_item_providers.0');
    }

    public function test_template_update_preserves_provider_after_integration_is_disconnected(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        $template = StandupTemplate::factory()->for($organization)->for($workspace)->create([
            'created_by_user_id' => $user->id,
        ]);
        $question = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you focusing on today?',
            'question_key' => 'today',
            'work_item_relation' => 'planned',
            'work_item_providers' => ['jira'],
        ]);

        $this->actingAs($user)->patch(route('templates.update', $template), [
            'name' => $template->name,
            'description' => $template->description,
            'is_active' => true,
            'questions' => [[
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_key' => $question->question_key,
                'work_item_relation' => 'planned',
                'work_item_providers' => ['jira'],
                'answer_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 1,
            ]],
        ])->assertRedirect(route('templates.index'));

        $this->assertSame(['jira'], $question->fresh()->work_item_providers);
    }

    public function test_question_accepts_only_items_from_its_configured_providers(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        $template = StandupTemplate::factory()->for($organization)->for($workspace)->create([
            'created_by_user_id' => $user->id,
        ]);
        $question = StandupQuestion::create([
            'standup_template_id' => $template->id,
            'question_text' => 'What are you reviewing?',
            'question_key' => 'other',
            'work_item_relation' => 'review',
            'work_item_providers' => ['bitbucket'],
            'answer_type' => 'textarea',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $run = StandupRun::factory()->for($organization)->for($workspace)->for($template)->create([
            'status' => 'active',
        ]);
        StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $jira = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);
        $jiraIdentity = IntegrationUser::create([
            'integration_id' => $jira->id,
            'user_id' => $user->id,
            'provider' => 'jira',
            'external_user_id' => 'jira-user',
        ]);
        $project = JiraProject::create([
            'integration_id' => $jira->id,
            'external_id' => '10000',
            'project_key' => 'DEV',
            'name' => 'Development',
        ]);
        $jiraItem = ExternalWorkItem::create([
            'integration_id' => $jira->id,
            'workspace_id' => $workspace->id,
            'jira_project_id' => $project->id,
            'assignee_integration_user_id' => $jiraIdentity->id,
            'provider' => 'jira',
            'external_id' => '10001',
            'external_key' => 'DEV-1',
            'type' => 'jira_issue',
            'title' => 'Jira item',
            'url' => 'https://example.atlassian.net/browse/DEV-1',
            'status' => 'In Progress',
        ]);

        $bitbucket = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'bitbucket',
        ]);
        $repository = BitbucketRepository::create([
            'integration_id' => $bitbucket->id,
            'workspace_uuid' => 'workspace-uuid',
            'workspace_slug' => 'workspace',
            'repository_uuid' => 'repository-uuid',
            'repository_slug' => 'dev-pulse',
            'full_name' => 'acme/dev-pulse',
            'is_active' => true,
        ]);
        $workspace->bitbucketRepositories()->attach($repository);
        $repository->users()->attach($user);
        $bitbucketItem = ExternalWorkItem::create([
            'integration_id' => $bitbucket->id,
            'workspace_id' => $workspace->id,
            'bitbucket_repository_id' => $repository->id,
            'provider' => 'bitbucket',
            'external_id' => '42@repository-uuid',
            'external_key' => '#42',
            'type' => 'pull_request',
            'title' => 'Bitbucket pull request',
            'url' => 'https://bitbucket.org/acme/dev-pulse/pull-requests/42',
            'status' => 'OPEN',
        ]);

        $this->actingAs($user)->get(route('standup-runs.submit', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('suggestedWorkItems', 2));

        $this->actingAs($user)->post(route('standup-runs.submit.store', $run), [
            'answers' => [$question->id => 'Reviewing a pull request'],
            'work_items' => [$question->id => [$jiraItem->id]],
        ])->assertSessionHasErrors("work_items.{$question->id}");

        $this->actingAs($user)->post(route('standup-runs.submit.store', $run), [
            'answers' => [$question->id => 'Reviewing a pull request'],
            'work_items' => [$question->id => [$bitbucketItem->id]],
        ])->assertRedirect();

        $this->assertDatabaseHas('standup_response_items', [
            'standup_question_id' => $question->id,
            'external_work_item_id' => $bitbucketItem->id,
            'provider' => 'bitbucket',
        ]);
    }

    public function test_submission_authorizes_selected_item_outside_suggestion_limit(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        $template = StandupTemplate::factory()->for($organization)->for($workspace)->create([
            'created_by_user_id' => $user->id,
        ]);
        $question = StandupQuestion::factory()->for($template)->create([
            'question_text' => 'What are you focusing on today?',
            'question_key' => 'today',
            'work_item_relation' => 'planned',
            'work_item_providers' => ['jira'],
        ]);
        $run = StandupRun::factory()->for($organization)->for($workspace)->for($template)->create([
            'status' => 'active',
        ]);
        StandupResponse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'standup_run_id' => $run->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $integration = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);
        $identity = IntegrationUser::create([
            'integration_id' => $integration->id,
            'user_id' => $user->id,
            'provider' => 'jira',
            'external_user_id' => 'jira-user',
        ]);
        $project = JiraProject::create([
            'integration_id' => $integration->id,
            'external_id' => '10000',
            'project_key' => 'DEV',
            'name' => 'Development',
        ]);
        $oldestItem = ExternalWorkItem::create([
            'integration_id' => $integration->id,
            'workspace_id' => $workspace->id,
            'jira_project_id' => $project->id,
            'assignee_integration_user_id' => $identity->id,
            'provider' => 'jira',
            'external_id' => '10001',
            'external_key' => 'DEV-1',
            'type' => 'jira_issue',
            'title' => 'Old but still eligible',
            'url' => 'https://example.atlassian.net/browse/DEV-1',
            'status' => 'In Progress',
            'provider_updated_at' => now()->subDay(),
        ]);
        foreach (range(2, 101) as $index) {
            ExternalWorkItem::create([
                'integration_id' => $integration->id,
                'workspace_id' => $workspace->id,
                'jira_project_id' => $project->id,
                'assignee_integration_user_id' => $identity->id,
                'provider' => 'jira',
                'external_id' => (string) (10000 + $index),
                'external_key' => 'DEV-'.$index,
                'type' => 'jira_issue',
                'title' => 'Newer item '.$index,
                'url' => 'https://example.atlassian.net/browse/DEV-'.$index,
                'status' => 'In Progress',
                'provider_updated_at' => now()->addMinutes($index),
            ]);
        }

        $this->actingAs($user)->post(route('standup-runs.submit.store', $run), [
            'answers' => [$question->id => 'Continuing older work'],
            'work_items' => [$question->id => [$oldestItem->id]],
        ])->assertRedirect();

        $this->assertDatabaseHas('standup_response_items', [
            'standup_question_id' => $question->id,
            'external_work_item_id' => $oldestItem->id,
        ]);
    }

    public function test_template_rejects_duplicate_work_item_relations(): void
    {
        [$user, , $workspace] = $this->workspaceUser();
        $question = fn (string $text, int $order) => [
            'question_text' => $text,
            'question_key' => 'other',
            'work_item_relation' => 'planned',
            'answer_type' => 'textarea',
            'is_required' => true,
            'sort_order' => $order,
        ];

        $this->actingAs($user)->from(route('templates.create'))->post(route('templates.store'), [
            'name' => 'Invalid Jira daily',
            'workspace_id' => $workspace->id,
            'is_active' => true,
            'questions' => [$question('First', 1), $question('Second', 2)],
        ])->assertRedirect(route('templates.create'))
            ->assertSessionHasErrors('questions.1.work_item_relation');
    }

    public function test_atlassian_integration_pages_are_hidden_when_feature_is_disabled(): void
    {
        config(['features.atlassian_integrations' => false]);

        [$user] = $this->workspaceUser();

        $this->actingAs($user)
            ->get(route('integrations.atlassian.index', ['provider' => 'jira']))
            ->assertNotFound();
    }

    public function test_dashboard_attention_list_contains_only_blocked_work_items(): void
    {
        [$user, $organization, $workspace] = $this->workspaceUser();
        $integration = Integration::factory()->for($organization)->create([
            'workspace_id' => null,
            'provider' => 'jira',
        ]);
        $project = JiraProject::create([
            'integration_id' => $integration->id,
            'external_id' => '10000',
            'project_key' => 'DEV',
            'name' => 'Development',
        ]);
        foreach ([
            ['id' => '10001', 'key' => 'DEV-1', 'title' => 'Normal active task', 'blocked' => false],
            ['id' => '10002', 'key' => 'DEV-2', 'title' => 'Waiting on API access', 'blocked' => true],
        ] as $row) {
            ExternalWorkItem::create([
                'integration_id' => $integration->id,
                'workspace_id' => $workspace->id,
                'jira_project_id' => $project->id,
                'provider' => 'jira',
                'external_id' => $row['id'],
                'external_key' => $row['key'],
                'type' => 'jira_issue',
                'title' => $row['title'],
                'url' => 'https://example.atlassian.net/browse/'.$row['key'],
                'status' => 'In Progress',
                'is_blocked' => $row['blocked'],
            ]);
        }

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('engineeringWork.items', 1)
                ->where('engineeringWork.items.0.external_key', 'DEV-2')
                ->where('engineeringWork.items.0.title', 'Waiting on API access'));
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
