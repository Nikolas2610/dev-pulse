<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\StandupAnswer;
use App\Models\StandupResponse;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReportsDemoSeeder extends Seeder
{
    private const MANAGER_INDEXES = [0, 6, 12];

    public function run(): void
    {
        mt_srand(20260711);
        $organization = Organization::where('slug', 'internal-company')->firstOrFail();
        $workspace = Workspace::whereBelongsTo($organization)->where('slug', 'engineering')->firstOrFail();
        $template = StandupTemplate::whereBelongsTo($workspace)->where('name', 'Daily Focus')->firstOrFail();
        $schedule = StandupSchedule::whereBelongsTo($workspace)->where('name', 'Daily Focus')->firstOrFail();
        $questions = $template->questions()->get()->keyBy('question_key');

        $people = ['Aisha Rahman', 'Ben Carter', 'Camila Torres', 'Dario Novak', 'Elena Rossi', 'Felix Wong', 'Grace Kim', 'Hugo Silva', 'Iris Jensen', 'Jon Bell', 'Kira Ahmed', 'Luca Moretti', 'Mina Park', 'Noah Williams', 'Priya Shah', 'Quinn Taylor', 'Rosa Alvarez', 'Sam Okafor'];
        $users = collect($people)->map(function (string $name, int $index) use ($organization, $workspace) {
            $workspaceRole = in_array($index, self::MANAGER_INDEXES, true)
                ? 'manager'
                : 'member';
            $user = User::updateOrCreate(['email' => sprintf('reports-demo-%02d@example.com', $index + 1)], [
                'name' => $name, 'password' => 'password', 'email_verified_at' => now(), 'current_organization_id' => $organization->id,
                'current_workspace_id' => $workspace->id, 'timezone' => $workspace->timezone, 'is_active' => true, 'onboarding_required' => false,
            ]);
            $organization->users()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
            $workspace->users()->syncWithoutDetaching([$user->id => ['role' => $workspaceRole, 'is_active' => true]]);

            return $user;
        });

        $users = $workspace->users()->wherePivot('is_active', true)->get();
        foreach (range(119, 0) as $daysAgo) {
            $date = Carbon::today($workspace->timezone)->subDays($daysAgo);
            if ($date->isWeekend()) {
                continue;
            }

            $run = StandupRun::updateOrCreate(['standup_schedule_id' => $schedule->id, 'run_date' => $date->toDateString()], [
                'organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'standup_template_id' => $template->id,
                'status' => $date->isToday() ? 'open' : 'completed', 'opened_at' => $date->copy()->setTime(8, 30), 'summary_posted_at' => $date->isToday() ? null : $date->copy()->setTime(10, 0),
            ]);

            foreach ($users as $userIndex => $user) {
                $weekdayPenalty = $date->isFriday() ? 16 : ($date->isMonday() ? 7 : 0);
                $personalPenalty = ($userIndex % 8) * 2;
                $submitted = mt_rand(1, 100) > 5 + $weekdayPenalty + $personalPenalty;
                $minutes = mt_rand(3, 95) + ($date->isFriday() ? 20 : 0);
                $response = StandupResponse::updateOrCreate(['standup_run_id' => $run->id, 'user_id' => $user->id], [
                    'organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'status' => $submitted ? 'submitted' : 'pending',
                    'submitted_at' => $submitted ? $date->copy()->setTime(8, 30)->addMinutes($minutes) : null, 'source' => mt_rand(1, 10) > 2 ? 'slack' : 'web',
                ]);
                $response->answers()->delete();
                if (! $submitted) {
                    continue;
                }

                $hasBlocker = mt_rand(1, 100) <= 13;
                $answers = [
                    'yesterday' => 'Completed DEV-'.mt_rand(120, 980).' and reviewed a teammate pull request.',
                    'today' => 'Shipping the next product increment and tightening test coverage.',
                    'blocker' => $hasBlocker ? ['Waiting on API credentials.', 'CI is intermittently failing.', 'Needs product clarification.'][mt_rand(0, 2)] : 'No blockers',
                    'ticket' => 'DEV-'.mt_rand(120, 980),
                ];
                foreach ($answers as $key => $text) {
                    if ($questions->has($key)) {
                        StandupAnswer::create(['standup_response_id' => $response->id, 'standup_question_id' => $questions[$key]->id, 'answer_text' => $text]);
                    }
                }
            }
        }
    }
}
