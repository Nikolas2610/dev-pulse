<?php

namespace Tests\Feature\DevPulse;

use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\Organization;
use App\Models\StandupRun;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SlackIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use RuntimeException;
use Tests\TestCase;

class SlackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_slack_connect_redirects_back_with_error_when_oauth_credentials_are_missing(): void
    {
        [$user] = $this->createUserWithOrganization();

        config([
            'services.slack.client_id' => null,
            'services.slack.client_secret' => null,
            'services.slack.redirect_uri' => null,
        ]);

        $this
            ->actingAs($user)
            ->get(route('integrations.slack.connect'))
            ->assertRedirect(route('integrations.slack.index'))
            ->assertSessionHas(SessionKey::FLASH_DATA, [
                'toast' => [
                    'type' => 'error',
                    'message' => 'Add Slack client ID and client secret for this workspace before connecting Slack.',
                ],
            ]);
    }

    public function test_admin_can_save_workspace_slack_oauth_credentials(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();

        $this
            ->actingAs($user)
            ->post(route('integrations.slack.credentials'), [
                'client_id' => 'workspace-client-id',
                'client_secret' => 'workspace-client-secret',
                'signing_secret' => 'workspace-signing-secret',
            ])
            ->assertRedirect(route('integrations.slack.index'));

        $integration = Integration::query()
            ->where('organization_id', $organization->id)
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'slack')
            ->firstOrFail();

        $this->assertSame('workspace-client-id', $integration->client_id);
        $this->assertSame('workspace-client-secret', $integration->client_secret);
        $this->assertSame('workspace-signing-secret', $integration->signing_secret);
    }

    public function test_slack_connect_redirects_to_slack_oauth_with_generated_callback_url(): void
    {
        [$user] = $this->createUserWithOrganization();

        config([
            'services.slack.client_id' => '123456.abcdef',
            'services.slack.client_secret' => 'secret',
            'services.slack.redirect_uri' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('integrations.slack.connect'));

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertIsString($location);
        $this->assertStringStartsWith('https://slack.com/oauth/v2/authorize?', $location);
        $this->assertStringContainsString('client_id=123456.abcdef', $location);
        $this->assertStringContainsString('redirect_uri='.urlencode(route('integrations.slack.callback')), $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_slack_connect_uses_workspace_credentials_before_environment_credentials(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();

        config([
            'services.slack.client_id' => null,
            'services.slack.client_secret' => null,
            'services.slack.redirect_uri' => null,
        ]);

        Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create([
                'client_id' => 'workspace-client-id',
                'client_secret' => 'workspace-client-secret',
                'external_id' => null,
                'connected_at' => null,
                'access_token' => null,
                'bot_token' => null,
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('integrations.slack.connect'));

        $location = $response->headers->get('Location');

        $this->assertIsString($location);
        $this->assertStringContainsString('client_id=workspace-client-id', $location);
    }

    public function test_slack_user_sync_logs_and_fails_when_slack_returns_error(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create([
                'provider' => 'slack',
                'bot_token' => 'xoxb-test',
                'scopes_json' => ['users:read'],
            ]);

        Http::fake([
            'slack.com/api/users.list' => Http::response([
                'ok' => false,
                'error' => 'missing_scope',
                'needed' => 'users:read.email',
                'provided' => 'users:read',
            ]),
        ]);
        Log::spy();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slack API users.list failed: missing_scope');

        try {
            app(SlackIntegrationService::class)->syncUsers($integration);
        } finally {
            Log::shouldHaveReceived('info')->with('Slack user sync started', [
                'integration_id' => $integration->id,
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
                'has_bot_token' => true,
                'scopes' => ['users:read'],
            ]);
            Log::shouldHaveReceived('error')->with('Slack user sync API error', [
                'integration_id' => $integration->id,
                'error' => 'missing_scope',
                'needed' => 'users:read.email',
                'provided' => 'users:read',
            ]);
        }
    }

    public function test_slack_user_sync_logs_counts_when_successful(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create([
                'provider' => 'slack',
                'bot_token' => 'xoxb-test',
                'scopes_json' => ['users:read', 'users:read.email'],
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
                        ],
                    ],
                    [
                        'id' => 'UBOT',
                        'name' => 'bot',
                        'is_bot' => true,
                    ],
                ],
            ]),
        ]);
        Log::spy();

        $synced = app(SlackIntegrationService::class)->syncUsers($integration);

        $this->assertSame(1, $synced);
        Log::shouldHaveReceived('info')->with('Slack user sync completed', [
            'integration_id' => $integration->id,
            'members_received' => 2,
            'users_synced' => 1,
            'users_matched' => 1,
            'users_unmatched' => 0,
            'users_skipped' => 1,
        ]);
    }

    public function test_slack_user_sync_logs_network_failures(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create([
                'provider' => 'slack',
                'bot_token' => 'xoxb-test',
                'scopes_json' => ['users:read', 'users:read.email'],
            ]);

        Http::fake(fn () => throw new ConnectionException('DNS timeout'));
        Log::spy();

        $this->expectException(ConnectionException::class);

        try {
            app(SlackIntegrationService::class)->syncUsers($integration);
        } finally {
            Log::shouldHaveReceived('error')->with('Slack user sync failed', [
                'integration_id' => $integration->id,
                'exception' => ConnectionException::class,
                'message' => 'DNS timeout',
            ]);
        }
    }

    public function test_slack_message_delivery_fails_when_slack_returns_error_payload(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create([
                'provider' => 'slack',
                'bot_token' => 'xoxb-test',
            ]);
        $template = StandupTemplate::factory()
            ->for($organization)
            ->for($workspace)
            ->for($user, 'creator')
            ->create();
        $run = StandupRun::factory()
            ->for($organization)
            ->for($workspace)
            ->for($template)
            ->create();

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => false,
                'error' => 'missing_scope',
                'needed' => 'chat:write',
                'provided' => 'users:read',
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slack API chat.postMessage failed: missing_scope');

        app(SlackIntegrationService::class)->postStandupPrompt($integration, 'C123', $run, 'Submit standup');
    }

    public function test_slack_message_delivery_joins_channel_and_retries_when_bot_is_not_in_channel(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create([
                'provider' => 'slack',
                'bot_token' => 'xoxb-test',
            ]);

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::sequence()
                ->push(['ok' => false, 'error' => 'not_in_channel'])
                ->push(['ok' => true, 'ts' => '123.456']),
            'slack.com/api/conversations.join' => Http::response(['ok' => true]),
        ]);

        $messageId = app(SlackIntegrationService::class)->postMessage($integration, 'C123', 'Summary text');

        $this->assertSame('123.456', $messageId);
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/conversations.join'
            && $request['channel'] === 'C123');
        Http::assertSentCount(3);
    }

    public function test_slack_integration_page_lists_synced_slack_users(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create(['provider' => 'slack', 'name' => 'Psillovits']);
        $linkedUser = User::factory()->create(['email' => 'linked@example.com']);

        $organization->users()->attach($linkedUser, ['role' => 'member']);
        $workspace->users()->attach($linkedUser, ['role' => 'member', 'is_active' => true]);

        IntegrationUser::query()->create([
            'integration_id' => $integration->id,
            'user_id' => $linkedUser->id,
            'provider' => 'slack',
            'external_user_id' => 'U_LINKED',
            'external_username' => 'linked',
            'external_email' => 'linked@example.com',
            'display_name' => 'Linked User',
        ]);
        IntegrationUser::query()->create([
            'integration_id' => $integration->id,
            'provider' => 'slack',
            'external_user_id' => 'U_UNLINKED',
            'external_username' => 'unlinked',
            'external_email' => 'unlinked@example.com',
            'display_name' => 'Unlinked User',
        ]);

        $this
            ->actingAs($user)
            ->get(route('integrations.slack.index'))
            ->assertInertia(fn ($page) => $page
                ->component('integrations/index')
                ->where('provider', 'slack')
                ->has('slackUsers', 2)
                ->where('slackUsers.0.external_user_id', 'U_UNLINKED')
                ->where('slackUsers.0.is_linked', false)
                ->where('slackUsers.1.external_user_id', 'U_LINKED')
                ->where('slackUsers.1.is_linked', true)
            );
    }

    public function test_admin_can_import_selected_synced_slack_users_into_current_workspace(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create(['provider' => 'slack']);
        $selected = IntegrationUser::query()->create([
            'integration_id' => $integration->id,
            'provider' => 'slack',
            'external_user_id' => 'U_SELECTED',
            'external_username' => 'selected',
            'external_email' => 'selected@example.com',
            'display_name' => 'Selected Slack User',
            'avatar_url' => 'https://example.com/selected.png',
        ]);
        $notSelected = IntegrationUser::query()->create([
            'integration_id' => $integration->id,
            'provider' => 'slack',
            'external_user_id' => 'U_NOT_SELECTED',
            'external_username' => 'not-selected',
            'external_email' => 'not-selected@example.com',
            'display_name' => 'Not Selected',
        ]);

        $this
            ->actingAs($user)
            ->post(route('integrations.slack.import-users', $integration), [
                'integration_user_ids' => [$selected->id],
                'organization_role' => 'member',
                'workspace_role' => 'member',
            ])
            ->assertRedirect(route('integrations.slack.index'));

        $imported = User::query()->where('email', 'selected@example.com')->firstOrFail();

        $this->assertSame('Selected Slack User', $imported->name);
        $this->assertSame('https://example.com/selected.png', $imported->avatar_url);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $imported->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $imported->id,
            'role' => 'member',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('integration_users', [
            'id' => $selected->id,
            'user_id' => $imported->id,
        ]);
        $this->assertDatabaseHas('integration_users', [
            'id' => $notSelected->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'not-selected@example.com']);
    }

    public function test_importing_slack_user_without_email_is_rejected(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();
        $integration = Integration::factory()
            ->for($organization)
            ->for($workspace)
            ->create(['provider' => 'slack']);
        $slackUser = IntegrationUser::query()->create([
            'integration_id' => $integration->id,
            'provider' => 'slack',
            'external_user_id' => 'U_NO_EMAIL',
            'external_username' => 'no-email',
            'display_name' => 'No Email',
        ]);

        $this
            ->actingAs($user)
            ->from(route('integrations.slack.index'))
            ->post(route('integrations.slack.import-users', $integration), [
                'integration_user_ids' => [$slackUser->id],
                'organization_role' => 'member',
                'workspace_role' => 'member',
            ])
            ->assertRedirect(route('integrations.slack.index'))
            ->assertSessionHasErrors(['integration_user_ids']);

        $this->assertDatabaseMissing('users', ['name' => 'No Email']);
    }

    /**
     * @return array{User, Organization, Workspace}
     */
    private function createUserWithOrganization(): array
    {
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);

        $organization->users()->attach($user, ['role' => 'admin']);
        $workspace->users()->attach($user, ['role' => 'manager', 'is_active' => true]);

        return [$user, $organization, $workspace];
    }
}
