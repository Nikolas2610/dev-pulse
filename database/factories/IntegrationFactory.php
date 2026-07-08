<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workspace_id' => Workspace::factory(),
            'provider' => 'slack',
            'name' => 'Slack',
            'external_id' => 'T'.fake()->numerify('######'),
            'bot_token' => 'xoxb-'.fake()->uuid(),
            'settings_json' => [],
            'scopes_json' => ['chat:write', 'users:read', 'channels:read'],
            'is_active' => true,
            'connected_at' => now(),
        ];
    }
}
