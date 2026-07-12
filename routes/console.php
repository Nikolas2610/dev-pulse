<?php

use App\Jobs\GenerateStandupRunsJob;
use App\Jobs\ProcessDueStandupRunsJob;
use App\Services\AutomationAuditService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(AutomationAuditService::class)->heartbeat())
    ->name('automation-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();
Schedule::job(new GenerateStandupRunsJob)->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new ProcessDueStandupRunsJob)->everyMinute()->withoutOverlapping();
