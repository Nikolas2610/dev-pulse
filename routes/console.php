<?php

use App\Jobs\GenerateStandupRunsJob;
use App\Jobs\ProcessDueStandupRunsJob;
use App\Jobs\SyncAtlassianIntegrationJob;
use App\Models\Integration;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateStandupRunsJob)->everyMinute()->withoutOverlapping();
Schedule::job(new ProcessDueStandupRunsJob)->everyMinute()->withoutOverlapping();
Schedule::call(function (): void {
    Integration::query()->whereIn('provider', ['jira', 'bitbucket'])->where('is_active', true)->pluck('id')->each(fn (int $id) => SyncAtlassianIntegrationJob::dispatch($id));
})->everyFifteenMinutes()->name('sync-atlassian-integrations')->withoutOverlapping();
