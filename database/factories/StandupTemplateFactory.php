<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class StandupTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workspace_id' => Workspace::factory(),
            'name' => 'Daily Focus',
            'description' => 'Daily team check-in',
            'is_default' => false,
            'is_active' => true,
            'created_by_user_id' => User::factory(),
        ];
    }
}
