<?php

namespace Tests\Feature\DevPulse;

use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscordIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_workspace_discord_webhook(): void
    {
        [$user, $organization, $workspace] = $this->createUserWithOrganization();

        $this
            ->actingAs($user)
            ->post(route('integrations.discord.store'), [
                'name' => 'Engineering Discord',
                'webhook_url' => 'https://discord.com/api/webhooks/123/token',
            ])
            ->assertRedirect(route('integrations.discord.index'));

        $integration = Integration::query()
            ->where('organization_id', $organization->id)
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'discord')
            ->firstOrFail();

        $this->assertSame('Engineering Discord', $integration->name);
        $this->assertSame('https://discord.com/api/webhooks/123/token', $integration->webhook_url);
    }

    public function test_discord_webhook_url_must_be_discord_webhook(): void
    {
        [$user] = $this->createUserWithOrganization();

        $this
            ->actingAs($user)
            ->post(route('integrations.discord.store'), [
                'name' => 'Bad webhook',
                'webhook_url' => 'https://example.com/webhook',
            ])
            ->assertSessionHasErrors('webhook_url');
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
