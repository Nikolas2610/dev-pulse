<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['slug' => 'internal-company'],
            [
                'name' => 'Internal Company',
                'timezone' => 'Asia/Nicosia',
                'is_active' => true,
            ],
        );

        $workspace = Workspace::firstOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'engineering'],
            [
                'name' => 'Engineering',
                'timezone' => 'Asia/Nicosia',
                'is_active' => true,
            ],
        );

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
            'timezone' => 'Asia/Nicosia',
        ]);

        $organization->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        $workspace->users()->syncWithoutDetaching([$user->id => ['role' => 'manager', 'is_active' => true]]);

        $template = StandupTemplate::firstOrCreate(
            ['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'name' => 'Daily Focus'],
            [
                'description' => 'Default daily engineering standup.',
                'is_default' => true,
                'is_active' => true,
                'created_by_user_id' => $user->id,
            ],
        );

        if ($template->questions()->count() === 0) {
            foreach ([
                ['What did you complete yesterday?', 'yesterday', 1],
                ['What are you focusing on today?', 'today', 2],
                ['Are you blocked by anything?', 'blocker', 3],
                ['Which ticket are you working on?', 'ticket', 4],
            ] as [$text, $key, $order]) {
                $template->questions()->create([
                    'question_text' => $text,
                    'question_key' => $key,
                    'answer_type' => 'textarea',
                    'is_required' => true,
                    'sort_order' => $order,
                ]);
            }
        }

        StandupSchedule::firstOrCreate(
            ['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'name' => 'Daily Focus'],
            [
                'standup_template_id' => $template->id,
                'timezone' => 'Asia/Nicosia',
                'time_of_day' => '08:30:00',
                'days_of_week' => [1, 2, 3, 4, 5],
                'reminder_minutes_after' => 15,
                'summary_minutes_after' => 30,
                'is_active' => true,
                'created_by_user_id' => $user->id,
            ],
        );
    }
}
