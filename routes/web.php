<?php

use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscordIntegrationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SlackIntegrationController;
use App\Http\Controllers\SlackInteractionController;
use App\Http\Controllers\StandupRunController;
use App\Http\Controllers\StandupSubmissionController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceSettingsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::post('slack/interactions', SlackInteractionController::class)->name('slack.interactions');
Route::post('slack/events', [SlackInteractionController::class, 'events'])->name('slack.events');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('context', [ContextController::class, 'update'])->name('context.update');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('standups', [StandupRunController::class, 'index'])->name('standups.index');
    Route::get('standups/{run}', [StandupRunController::class, 'show'])->name('standups.show');
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::patch('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::get('schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->name('schedules.edit');
    Route::post('schedules/{schedule}/send-now', [ScheduleController::class, 'sendNow'])->name('schedules.send-now');
    Route::post('schedules/{schedule}/send-reminder', [ScheduleController::class, 'sendReminder'])->name('schedules.send-reminder');
    Route::post('schedules/{schedule}/send-summary', [ScheduleController::class, 'sendSummary'])->name('schedules.send-summary');
    Route::patch('schedules/{schedule}/active', [ScheduleController::class, 'toggleActive'])->name('schedules.toggle-active');
    Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    Route::get('team-members', [TeamMemberController::class, 'index'])->name('team-members.index');
    Route::post('team-members', [TeamMemberController::class, 'store'])->name('team-members.store');
    Route::patch('team-members/{user}', [TeamMemberController::class, 'update'])->name('team-members.update');
    Route::delete('team-members/{user}/workspaces/{workspace}', [TeamMemberController::class, 'destroyWorkspace'])->name('team-members.workspaces.destroy');
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::get('integrations/discord', [DiscordIntegrationController::class, 'index'])->name('integrations.discord.index');
    Route::post('integrations/discord', [DiscordIntegrationController::class, 'store'])->name('integrations.discord.store');
    Route::get('integrations/slack', [SlackIntegrationController::class, 'index'])->name('integrations.slack.index');
    Route::post('integrations/slack/credentials', [SlackIntegrationController::class, 'storeCredentials'])->name('integrations.slack.credentials');
    Route::get('integrations/slack/connect', [SlackIntegrationController::class, 'connect'])->name('integrations.slack.connect');
    Route::get('integrations/slack/callback', [SlackIntegrationController::class, 'callback'])->name('integrations.slack.callback');
    Route::post('integrations/slack/{integration}/sync-users', [SlackIntegrationController::class, 'syncUsers'])->name('integrations.slack.sync-users');
    Route::post('integrations/slack/{integration}/sync-channels', [SlackIntegrationController::class, 'syncChannels'])->name('integrations.slack.sync-channels');
    Route::post('integrations/slack/{integration}/import-users', [SlackIntegrationController::class, 'importUsers'])->name('integrations.slack.import-users');
    Route::get('standup-runs/{run}/submit', [StandupSubmissionController::class, 'create'])->name('standup-runs.submit');
    Route::post('standup-runs/{run}/submit', [StandupSubmissionController::class, 'store'])->name('standup-runs.submit.store');
    Route::get('settings/organization', [OrganizationSettingsController::class, 'edit'])->name('settings.organization.edit');
    Route::patch('settings/organization', [OrganizationSettingsController::class, 'update'])->name('settings.organization.update');
    Route::get('settings/workspace', [WorkspaceSettingsController::class, 'edit'])->name('settings.workspace.edit');
    Route::patch('settings/workspace', [WorkspaceSettingsController::class, 'update'])->name('settings.workspace.update');
});

require __DIR__.'/settings.php';
