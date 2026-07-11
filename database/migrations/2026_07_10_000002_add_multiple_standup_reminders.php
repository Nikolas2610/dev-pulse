<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_TEMPLATE = 'Reminder: {title} ends in {time_remaining} at {end_time}. {url}';

    public function up(): void
    {
        Schema::table('standup_schedules', function (Blueprint $table): void {
            $table->time('end_time_of_day')->nullable()->after('time_of_day');
            $table->json('reminder_minutes_before_end')->nullable()->after('days_of_week');
            $table->text('reminder_message_template')->nullable()->after('reminder_minutes_before_end');
        });

        Schema::table('standup_runs', function (Blueprint $table): void {
            $table->timestamp('scheduled_open_at')->nullable()->after('status');
            $table->timestamp('summary_due_at')->nullable()->after('opened_at');
        });

        Schema::table('standup_responses', function (Blueprint $table): void {
            $table->timestamp('late_summary_posted_at')->nullable()->after('submitted_at');
        });

        Schema::create('standup_run_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standup_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minutes_before_end');
            $table->timestamp('scheduled_for');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['standup_run_id', 'minutes_before_end']);
            $table->index(['status', 'scheduled_for']);
        });

        DB::table('standup_schedules')
            ->orderBy('id')
            ->eachById(function (object $schedule): void {
                $duration = max(1, (int) $schedule->summary_minutes_after);
                $start = Carbon::createFromFormat('H:i:s', (string) $schedule->time_of_day, 'UTC');
                $end = $start->copy()->addMinutes($duration);
                $offsets = collect([60, 30, 15, 5])
                    ->filter(fn (int $offset): bool => $offset < $duration)
                    ->values()
                    ->all();

                DB::table('standup_schedules')->where('id', $schedule->id)->update([
                    'end_time_of_day' => $end->format('H:i:s'),
                    'reminder_minutes_before_end' => json_encode($offsets, JSON_THROW_ON_ERROR),
                    'reminder_message_template' => self::DEFAULT_TEMPLATE,
                ]);
            });

        DB::table('standup_runs')
            ->join('standup_schedules', 'standup_schedules.id', '=', 'standup_runs.standup_schedule_id')
            ->whereIn('standup_runs.status', ['scheduled', 'active'])
            ->select([
                'standup_runs.id',
                'standup_runs.run_date',
                'standup_schedules.timezone',
                'standup_schedules.time_of_day',
                'standup_schedules.end_time_of_day',
                'standup_schedules.reminder_minutes_before_end',
            ])
            ->orderBy('standup_runs.id')
            ->each(function (object $run): void {
                $open = Carbon::parse($run->run_date.' '.$run->time_of_day, $run->timezone);
                $summary = Carbon::parse($run->run_date.' '.$run->end_time_of_day, $run->timezone);

                if ($summary->lessThanOrEqualTo($open)) {
                    $summary->addDay();
                }

                DB::table('standup_runs')->where('id', $run->id)->update([
                    'scheduled_open_at' => $open->copy()->utc(),
                    'summary_due_at' => $summary->copy()->utc(),
                ]);

                $offsets = json_decode((string) $run->reminder_minutes_before_end, true, flags: JSON_THROW_ON_ERROR);

                foreach ($offsets as $offset) {
                    $scheduledFor = $summary->copy()->subMinutes((int) $offset)->utc();

                    DB::table('standup_run_reminders')->insertOrIgnore([
                        'standup_run_id' => $run->id,
                        'minutes_before_end' => (int) $offset,
                        'scheduled_for' => $scheduledFor,
                        'status' => $scheduledFor->isPast() ? 'skipped' : 'pending',
                        'attempts' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('standup_run_reminders');

        Schema::table('standup_responses', function (Blueprint $table): void {
            $table->dropColumn('late_summary_posted_at');
        });

        Schema::table('standup_runs', function (Blueprint $table): void {
            $table->dropColumn(['scheduled_open_at', 'summary_due_at']);
        });

        Schema::table('standup_schedules', function (Blueprint $table): void {
            $table->dropColumn(['end_time_of_day', 'reminder_minutes_before_end', 'reminder_message_template']);
        });
    }
};
