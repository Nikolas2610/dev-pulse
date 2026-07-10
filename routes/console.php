<?php

use App\Jobs\GenerateStandupRunsJob;
use App\Jobs\ProcessDueStandupRunsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateStandupRunsJob)->everyMinute()->withoutOverlapping();
Schedule::job(new ProcessDueStandupRunsJob)->everyMinute()->withoutOverlapping();
