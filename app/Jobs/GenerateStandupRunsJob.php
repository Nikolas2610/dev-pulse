<?php

namespace App\Jobs;

use App\Services\AutomationAuditService;
use App\Services\StandupSchedulerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateStandupRunsJob implements ShouldQueue
{
    use Queueable;

    public function handle(StandupSchedulerService $scheduler): void
    {
        $audit = app(AutomationAuditService::class);

        $audit->run('standups.generate-runs', fn (): array => [
            'runs_created' => $scheduler->generateDueRuns(),
        ]);
    }
}
