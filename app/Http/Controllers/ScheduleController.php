<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Services\CurrentOrganization;
use App\Services\StandupMessageService;
use App\Services\StandupSummaryService;
use App\Support\TimezoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ScheduleController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);

        return Inertia::render('schedules/index', [
            'schedules' => StandupSchedule::query()
                ->with('workspace', 'template', 'targetIntegration', 'promptChannel', 'targetChannel')
                ->withCount('participants')
                ->where('organization_id', $organization?->id)
                ->latest()
                ->get(),
            'workspaces' => $organization?->workspaces()->orderBy('name')->get(['id', 'name']) ?? [],
            'templates' => StandupTemplate::where('organization_id', $organization?->id)->orderBy('name')->get(['id', 'name']),
            'integrations' => Integration::with('channels')
                ->where('organization_id', $organization?->id)
                ->whereIn('provider', ['slack', 'discord'])
                ->orderBy('provider')
                ->orderBy('name')
                ->get(),
            'members' => $organization?->users()
                ->with([
                    'workspaces' => fn ($query) => $query->wherePivot('is_active', true),
                    'integrationUsers' => fn ($query) => $query->where('provider', 'slack')->select('id', 'integration_id', 'user_id', 'provider'),
                ])
                ->orderBy('name')
                ->get(['users.id', 'name', 'email']) ?? [],
            'canManageSchedules' => $organization
                ? $currentOrganization->canManageOrganization($organization, $request->user())
                : false,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization, 403);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $validated = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'standup_template_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'timezone' => TimezoneRules::required(),
            'time_of_day' => ['required', 'date_format:H:i'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'recipient_mode' => ['required', 'string', 'in:all_workspace_members,selected_members'],
            'delivery_method' => ['required', 'string', 'in:email,slack_dm,slack_channel,discord_channel'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['integer'],
            'reminder_minutes_after' => ['required', 'integer', 'min:0'],
            'summary_minutes_after' => ['required', 'integer', 'min:0'],
            'target_integration_id' => ['nullable', 'integer'],
            'prompt_channel_id' => ['nullable', 'integer'],
            'target_channel_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['recipient_mode'] === 'selected_members' && empty($validated['participant_user_ids'])) {
            return back()->withErrors([
                'participant_user_ids' => __('Select at least one recipient.'),
            ])->withInput();
        }

        if ($response = $this->validateDeliveryConfiguration($validated, $organization->id)) {
            return $response;
        }

        if ($response = $this->validateRecipientConfiguration($validated)) {
            return $response;
        }

        $schedule = StandupSchedule::create([
            ...Arr::except($validated, 'participant_user_ids'),
            'organization_id' => $organization->id,
            'timezone' => TimezoneRules::normalize($validated['timezone']),
            'created_by_user_id' => $request->user()->id,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $this->syncParticipants($schedule, $validated['participant_user_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule created.')]);

        return to_route('schedules.index');
    }

    public function edit(Request $request, StandupSchedule $schedule, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        return Inertia::render('schedules/edit', [
            'schedule' => $schedule->load('workspace', 'template', 'targetIntegration', 'promptChannel', 'targetChannel', 'participants'),
            'workspaces' => $organization->workspaces()->orderBy('name')->get(['id', 'name']),
            'templates' => StandupTemplate::where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name']),
            'integrations' => Integration::with('channels')
                ->where('organization_id', $organization->id)
                ->whereIn('provider', ['slack', 'discord'])
                ->orderBy('provider')
                ->orderBy('name')
                ->get(),
            'members' => $organization->users()
                ->with([
                    'workspaces' => fn ($query) => $query->wherePivot('is_active', true),
                    'integrationUsers' => fn ($query) => $query->where('provider', 'slack')->select('id', 'integration_id', 'user_id', 'provider'),
                ])
                ->orderBy('name')
                ->get(['users.id', 'name', 'email']),
            'canManageSchedules' => true,
        ]);
    }

    public function update(Request $request, StandupSchedule $schedule, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => TimezoneRules::required(),
            'time_of_day' => ['required', 'date_format:H:i'],
            'reminder_minutes_after' => ['required', 'integer', 'min:0'],
            'summary_minutes_after' => ['required', 'integer', 'min:0'],
            'recipient_mode' => ['required', 'string', 'in:all_workspace_members,selected_members'],
            'delivery_method' => ['required', 'string', 'in:email,slack_dm,slack_channel,discord_channel'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['integer'],
            'target_integration_id' => ['nullable', 'integer'],
            'prompt_channel_id' => ['nullable', 'integer'],
            'target_channel_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['recipient_mode'] === 'selected_members' && empty($validated['participant_user_ids'])) {
            return back()->withErrors([
                'participant_user_ids' => __('Select at least one recipient.'),
            ])->withInput();
        }

        if ($response = $this->validateDeliveryConfiguration($validated, $organization->id)) {
            return $response;
        }

        if ($response = $this->validateRecipientConfiguration($validated)) {
            return $response;
        }

        $schedule->update([
            ...Arr::except($validated, 'participant_user_ids'),
            'timezone' => TimezoneRules::normalize($validated['timezone']),
        ]);

        $this->syncParticipants($schedule, $validated['participant_user_ids'] ?? []);

        return to_route('schedules.index');
    }

    public function destroy(Request $request, StandupSchedule $schedule, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $schedule->delete();

        return to_route('schedules.index');
    }

    public function toggleActive(Request $request, StandupSchedule $schedule, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $schedule->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $schedule->is_active ? __('Schedule activated.') : __('Schedule deactivated.'),
        ]);

        return to_route('schedules.index');
    }

    public function sendNow(
        Request $request,
        StandupSchedule $schedule,
        CurrentOrganization $currentOrganization,
        StandupMessageService $messages,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $baseScheduleDate = now($schedule->timezone)->toDateString();
        $allowsMultipleManualRuns = $this->allowsMultipleManualRunsForManualSend();
        $scheduleDate = $allowsMultipleManualRuns
            ? $this->nextAvailableManualRunDate($schedule, $baseScheduleDate)
            : $baseScheduleDate;

        $runAttributes = [
            'organization_id' => $schedule->organization_id,
            'workspace_id' => $schedule->workspace_id,
            'standup_template_id' => $schedule->standup_template_id,
            'status' => 'scheduled',
        ];

        $run = $allowsMultipleManualRuns
            ? StandupRun::create([
                'standup_schedule_id' => $schedule->id,
                'run_date' => $scheduleDate,
                ...$runAttributes,
            ])
            : StandupRun::firstOrCreate(
                [
                    'standup_schedule_id' => $schedule->id,
                    'run_date' => $scheduleDate,
                ],
                [
                    ...$runAttributes,
                ],
            );

        Log::info('Manual standup send requested', [
            'standup_schedule_id' => $schedule->id,
            'standup_run_id' => $run->id,
            'base_run_date' => $baseScheduleDate,
            'run_date' => $scheduleDate,
            'run_was_created' => $run->wasRecentlyCreated,
            'local_allows_multiple_manual_runs' => $allowsMultipleManualRuns,
            'delivery_method' => $schedule->delivery_method,
            'recipient_mode' => $schedule->recipient_mode,
        ]);

        $messages->openRun($run);

        $pendingCount = $run->responses()->where('status', 'pending')->count();

        Inertia::flash('toast', [
            'type' => $pendingCount > 0 ? 'success' : 'info',
            'message' => $pendingCount > 0
                ? __('Standup sent now.')
                : __('No pending recipients to notify for this run.'),
        ]);

        return to_route('schedules.index');
    }

    public function sendSummary(
        Request $request,
        StandupSchedule $schedule,
        CurrentOrganization $currentOrganization,
        StandupSummaryService $summaries,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $run = StandupRun::query()
            ->where('standup_schedule_id', $schedule->id)
            ->latest('run_date')
            ->latest('id')
            ->first();

        if (! $run) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('No standup run exists for this schedule yet.'),
            ]);

            return to_route('schedules.index');
        }

        try {
            $summaries->postSummary($run, requireExternalDelivery: true);
        } catch (RuntimeException $exception) {
            Log::error('Manual standup summary send failed', [
                'standup_schedule_id' => $schedule->id,
                'standup_run_id' => $run->id,
                'message' => $exception->getMessage(),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Could not send summary. Configure a Slack summary channel or Discord webhook for this schedule first.'),
            ]);

            return to_route('schedules.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Summary sent for the latest run.'),
        ]);

        return to_route('schedules.index');
    }

    public function sendReminder(
        Request $request,
        StandupSchedule $schedule,
        CurrentOrganization $currentOrganization,
        StandupMessageService $messages,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $run = StandupRun::query()
            ->where('standup_schedule_id', $schedule->id)
            ->latest('run_date')
            ->latest('id')
            ->first();

        if (! $run) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('No standup run exists for this schedule yet. Send the schedule first.'),
            ]);

            return to_route('schedules.index');
        }

        $pendingCount = $run->responses()->where('status', 'pending')->count();

        if ($pendingCount === 0) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('No pending recipients to remind for the latest run.'),
            ]);

            return to_route('schedules.index');
        }

        try {
            $messages->sendReminder($run);
        } catch (Throwable $exception) {
            Log::error('Manual standup reminder send failed', [
                'standup_schedule_id' => $schedule->id,
                'standup_run_id' => $run->id,
                'message' => $exception->getMessage(),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Could not send reminder. Check the schedule delivery configuration and integration logs.'),
            ]);

            return to_route('schedules.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('Reminder sent to :count pending recipient.|Reminder sent to :count pending recipients.', $pendingCount, [
                'count' => $pendingCount,
            ]),
        ]);

        return to_route('schedules.index');
    }

    private function allowsMultipleManualRunsForManualSend(): bool
    {
        return config('app.env') === 'local';
    }

    private function nextAvailableManualRunDate(StandupSchedule $schedule, string $baseDate): string
    {
        $date = Carbon::parse($baseDate, $schedule->timezone)->startOfDay();

        while (StandupRun::query()
            ->where('standup_schedule_id', $schedule->id)
            ->whereDate('run_date', $date->toDateString())
            ->exists()) {
            $date = $date->addDay();
        }

        return $date->toDateString();
    }

    /**
     * @param  array<int, int|string>  $participantUserIds
     */
    private function syncParticipants(StandupSchedule $schedule, array $participantUserIds): void
    {
        if ($schedule->recipient_mode !== 'selected_members') {
            $schedule->participants()->sync([]);

            return;
        }

        $activeWorkspaceUserIds = $schedule->workspace
            ->users()
            ->wherePivot('is_active', true)
            ->whereIn('users.id', $participantUserIds)
            ->pluck('users.id')
            ->all();

        if ($schedule->delivery_method === 'slack_channel' && $schedule->target_integration_id) {
            $slackLinkedUserIds = IntegrationUser::query()
                ->where('integration_id', $schedule->target_integration_id)
                ->whereIn('user_id', $activeWorkspaceUserIds)
                ->pluck('user_id')
                ->all();

            $activeWorkspaceUserIds = array_values(array_intersect($activeWorkspaceUserIds, $slackLinkedUserIds));
        }

        $schedule->participants()->sync($activeWorkspaceUserIds);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateDeliveryConfiguration(array $validated, int $organizationId): ?RedirectResponse
    {
        $deliveryMethod = $validated['delivery_method'];
        $integration = isset($validated['target_integration_id'])
            ? Integration::query()
                ->where('organization_id', $organizationId)
                ->find($validated['target_integration_id'])
            : null;
        $errors = [];

        if (in_array($deliveryMethod, ['slack_dm', 'slack_channel'], true) && $integration?->provider !== 'slack') {
            $errors['target_integration_id'] = __('Select a Slack integration for this delivery method.');
        }

        if ($deliveryMethod === 'discord_channel' && ($integration?->provider !== 'discord' || ! $integration->webhook_url)) {
            $errors['target_integration_id'] = __('Select a Discord integration with a webhook URL.');
        }

        if ($deliveryMethod !== 'slack_channel') {
            return $errors === [] ? null : back()->withErrors($errors)->withInput();
        }

        if (empty($validated['prompt_channel_id'])) {
            $errors['prompt_channel_id'] = __('Select a Slack prompt channel.');
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $channelExists = $integration?->channels()
            ->whereKey($validated['prompt_channel_id'])
            ->where('is_active', true)
            ->exists();

        if (! $channelExists) {
            return back()->withErrors([
                'prompt_channel_id' => __('Select an active Slack prompt channel for this integration.'),
            ])->withInput();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateRecipientConfiguration(array $validated): ?RedirectResponse
    {
        if (($validated['delivery_method'] ?? null) !== 'slack_channel') {
            return null;
        }

        if (($validated['recipient_mode'] ?? null) !== 'selected_members') {
            return null;
        }

        $participantUserIds = array_unique($validated['participant_user_ids'] ?? []);

        if ($participantUserIds === []) {
            return null;
        }

        $linkedCount = IntegrationUser::query()
            ->where('integration_id', $validated['target_integration_id'] ?? null)
            ->whereIn('user_id', $participantUserIds)
            ->distinct('user_id')
            ->count('user_id');

        if ($linkedCount === count($participantUserIds)) {
            return null;
        }

        return back()->withErrors([
            'participant_user_ids' => __('Slack channel schedules can only include recipients linked to this Slack integration. Import or link the Slack users first.'),
        ])->withInput();
    }
}
