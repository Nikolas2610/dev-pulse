<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\StandupReminderMessageService;
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

        $seedUsers = collect([
            ['name' => 'Test User', 'email' => 'test@example.com', 'organization_role' => 'owner', 'workspace_role' => 'manager'],
            ['name' => 'Maya Chen', 'email' => 'maya@example.com', 'organization_role' => 'admin', 'workspace_role' => 'manager'],
            ['name' => 'Owen Patel', 'email' => 'owen@example.com', 'organization_role' => 'member', 'workspace_role' => 'member'],
            ['name' => 'Sofia Garcia', 'email' => 'sofia@example.com', 'organization_role' => 'member', 'workspace_role' => 'member'],
            ['name' => 'Leo Martin', 'email' => 'leo@example.com', 'organization_role' => 'member', 'workspace_role' => 'member'],
        ])->map(function (array $seedUser) use ($organization, $workspace) {
            $user = User::firstOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'password' => 'password',
                    'current_organization_id' => $organization->id,
                    'current_workspace_id' => $workspace->id,
                    'timezone' => 'Asia/Nicosia',
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'onboarding_required' => false,
                ],
            );

            $organization->users()->syncWithoutDetaching([
                $user->id => ['role' => $seedUser['organization_role']],
            ]);

            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $seedUser['workspace_role'], 'is_active' => true],
            ]);

            return $user;
        });

        $user = $seedUsers->first();

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
                'end_time_of_day' => '10:00:00',
                'days_of_week' => [1, 2, 3, 4, 5],
                'reminder_minutes_after' => 15,
                'summary_minutes_after' => 90,
                'reminder_minutes_before_end' => [60, 30, 15, 5],
                'reminder_message_template' => StandupReminderMessageService::DEFAULT_TEMPLATE,
                'is_active' => true,
                'created_by_user_id' => $user->id,
            ],
        );

        $this->call(ReportsDemoSeeder::class);
    }
}
