<?php

use App\Http\Controllers\Api\IndexDataController;
use App\Http\Controllers\AutomationHealthController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscordIntegrationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SlackIntegrationController;
use App\Http\Controllers\SlackInteractionController;
use App\Http\Controllers\StandupRunController;
use App\Http\Controllers\StandupSubmissionController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TeamStandupController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceSettingsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::post('slack/interactions', SlackInteractionController::class)->name('slack.interactions');
Route::post('slack/events', [SlackInteractionController::class, 'events'])->name('slack.events');
Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
    Route::post('invitations/accept', [InvitationController::class, 'store'])->middleware('throttle:6,1')->name('invitations.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('onboarding/organization', [OnboardingController::class, 'saveOrganization'])->name('onboarding.organization.store');
    Route::post('onboarding/integrations/slack', [OnboardingController::class, 'saveSlack'])->name('onboarding.integrations.slack.store');
    Route::post('onboarding/integrations/discord', [OnboardingController::class, 'saveDiscord'])->name('onboarding.integrations.discord.store');
    Route::post('onboarding/integrations/continue', [OnboardingController::class, 'finishIntegrations'])->name('onboarding.integrations.continue');
    Route::post('onboarding/members', [OnboardingController::class, 'inviteMembers'])->name('onboarding.members.store');
    Route::post('onboarding/members/skip', [OnboardingController::class, 'skipMembers'])->name('onboarding.members.skip');
    Route::patch('onboarding/template', [OnboardingController::class, 'saveTemplate'])->name('onboarding.template.update');
    Route::post('onboarding/schedule', [OnboardingController::class, 'saveSchedule'])->name('onboarding.schedule.store');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('standups', [IndexDataController::class, 'standups'])->middleware('role-capability:viewStandups')->name('standups.index');
        Route::get('templates', [IndexDataController::class, 'templates'])->middleware('role-capability:manageTemplates')->name('templates.index');
        Route::get('schedules', [IndexDataController::class, 'schedules'])->middleware('role-capability:manageSchedules')->name('schedules.index');
        Route::get('workspaces', [IndexDataController::class, 'workspaces'])->middleware('role-capability:manageWorkspaces')->name('workspaces.index');
        Route::get('team-members', [IndexDataController::class, 'teamMembers'])->middleware('role-capability:viewTeam')->name('team-members.index');
    });

    Route::patch('context', [ContextController::class, 'update'])->name('context.update');
    Route::middleware('role-capability:viewDashboard')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports', ReportsController::class)->name('reports.index');
    });
    Route::middleware('role-capability:viewStandups')->group(function () {
        Route::get('standups', [StandupRunController::class, 'index'])->name('standups.index');
        Route::get('standups/{run}', [StandupRunController::class, 'show'])->name('standups.show');
        Route::get('standup-runs/{run}/submit', [StandupSubmissionController::class, 'create'])->name('standup-runs.submit');
        Route::post('standup-runs/{run}/submit', [StandupSubmissionController::class, 'store'])->name('standup-runs.submit.store');
        Route::post('standup-runs/{run}/skip', [StandupSubmissionController::class, 'skip'])->name('standup-runs.skip');
    });
    Route::middleware('role-capability:manageTemplates')->group(function () {
        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::patch('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    });
    Route::middleware('role-capability:manageSchedules')->group(function () {
        Route::get('automation-health', AutomationHealthController::class)->name('automation-health.index');
        Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->name('schedules.edit');
        Route::post('schedules/{schedule}/send-now', [ScheduleController::class, 'sendNow'])->name('schedules.send-now');
        Route::post('schedules/{schedule}/send-reminder', [ScheduleController::class, 'sendReminder'])->name('schedules.send-reminder');
        Route::post('schedules/{schedule}/send-summary', [ScheduleController::class, 'sendSummary'])->name('schedules.send-summary');
        Route::patch('schedules/{schedule}/active', [ScheduleController::class, 'toggleActive'])->name('schedules.toggle-active');
        Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    });
    Route::middleware('role-capability:viewTeam')->group(function () {
        Route::get('team-members', [TeamMemberController::class, 'index'])->name('team-members.index');
        Route::get('team-standups', TeamStandupController::class)->name('team-standups.index');
    });
    Route::middleware('role-capability:manageTeam')->group(function () {
        Route::post('team-members', [TeamMemberController::class, 'store'])->name('team-members.store');
        Route::patch('team-members/{user}', [TeamMemberController::class, 'update'])->name('team-members.update');
        Route::delete('team-members/{user}/workspaces/{workspace}', [TeamMemberController::class, 'destroyWorkspace'])->name('team-members.workspaces.destroy');
        Route::post('integrations/slack/{integration}/import-users', [SlackIntegrationController::class, 'importUsers'])->name('integrations.slack.import-users');
    });
    Route::middleware('role-capability:manageWorkspaces')->group(function () {
        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
        Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    });
    Route::middleware('role-capability:manageIntegrations')->group(function () {
        Route::get('integrations/discord', [DiscordIntegrationController::class, 'index'])->name('integrations.discord.index');
        Route::post('integrations/discord', [DiscordIntegrationController::class, 'store'])->name('integrations.discord.store');
        Route::get('integrations/slack', [SlackIntegrationController::class, 'index'])->name('integrations.slack.index');
        Route::post('integrations/slack/credentials', [SlackIntegrationController::class, 'storeCredentials'])->name('integrations.slack.credentials');
        Route::get('integrations/slack/connect', [SlackIntegrationController::class, 'connect'])->name('integrations.slack.connect');
        Route::get('integrations/slack/callback', [SlackIntegrationController::class, 'callback'])->name('integrations.slack.callback');
        Route::post('integrations/slack/{integration}/sync-users', [SlackIntegrationController::class, 'syncUsers'])->name('integrations.slack.sync-users');
        Route::post('integrations/slack/{integration}/sync-channels', [SlackIntegrationController::class, 'syncChannels'])->name('integrations.slack.sync-channels');
    });
    Route::middleware('role-capability:manageOrganization')->group(function () {
        Route::get('settings/organization', [OrganizationSettingsController::class, 'edit'])->name('settings.organization.edit');
        Route::patch('settings/organization', [OrganizationSettingsController::class, 'update'])->name('settings.organization.update');
    });
    Route::middleware('role-capability:manageWorkspace')->group(function () {
        Route::get('settings/workspace', [WorkspaceSettingsController::class, 'edit'])->name('settings.workspace.edit');
        Route::patch('settings/workspace', [WorkspaceSettingsController::class, 'update'])->name('settings.workspace.update');
    });
});

require __DIR__.'/settings.php';
