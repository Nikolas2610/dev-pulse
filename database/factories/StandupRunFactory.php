<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\StandupRun;
use App\Models\StandupTemplate;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StandupRun> */
class StandupRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workspace_id' => Workspace::factory(),
            'standup_template_id' => StandupTemplate::factory(),
            'run_date' => now()->toDateString(),
            'status' => 'scheduled',
        ];
    }
}
