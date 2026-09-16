<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\Organization;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Services\CurrentOrganization;
use App\Services\StandupMessageService;
use App\Services\StandupReminderMessageService;
use App\Services\StandupSummaryService;
use App\Services\StandupTimingService;
use App\Support\TimezoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ScheduleController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspaces = $organization
            ? $currentOrganization->manageableWorkspacesForUser($organization, $request->user())
            : collect();
        $workspaceIds = $workspaces->pluck('id');
        $isOrganizationManager = $organization
            && $currentOrganization->canManageOrganization($organization, $request->user());

        return Inertia::render('schedules/index', [
            'indexEndpoint' => route('api.schedules.index'),
            'workspaces' => $workspaces->map->only(['id', 'name'])->values(),
            'templates' => StandupTemplate::where('organization_id', $organization?->id)
                ->when(! $isOrganizationManager, fn ($query) => $query->whereIn('workspace_id', $workspaceIds))
                ->orderBy('name')->get(['id', 'name']),
            'integrations' => Integration::with('channels')
                ->where('organization_id', $organization?->id)
                ->where('is_active', true)
                ->when(! $isOrganizationManager, fn ($query) => $query->whereIn('workspace_id', $workspaceIds))
                ->whereIn('provider', ['slack', 'discord'])
                ->orderBy('provider')
                ->orderBy('name')
                ->get(),
            'members' => $organization?->users()
                ->with([
                    'workspaces' => fn ($query) => $query
                        ->wherePivot('is_active', true)
                        ->when(! $isOrganizationManager, fn ($query) => $query->whereIn('workspaces.id', $workspaceIds)),
                    'integrationUsers' => fn ($query) => $query->where('provider', 'slack')->select('id', 'integration_id', 'user_id', 'provider'),
                ])
                ->when(! $isOrganizationManager, fn ($query) => $query->whereHas(
                    'workspaces',
                    fn ($query) => $query->whereIn('workspaces.id', $workspaceIds)->where('workspace_user.is_active', true),
                ))
                ->orderBy('name')
                ->get(['users.id', 'name', 'email']) ?? [],
            'canManageSchedules' => $organization
                ? $currentOrganization->canManageCurrentWorkspace($organization, $request->user())
                : false,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization !== null, 403);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);

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
            'end_time_of_day' => ['nullable', 'date_format:H:i'],
            'reminder_minutes_before_end' => ['nullable', 'array', 'min:1'],
            'reminder_minutes_before_end.*' => ['integer', 'min:1'],
            'reminder_message_template' => ['nullable', 'string', 'max:1000'],
            'reminder_minutes_after' => ['nullable', 'integer', 'min:0'],
            'summary_minutes_after' => ['nullable', 'integer', 'min:1', 'max:1439'],
            'target_integration_id' => ['nullable', 'integer'],
            'prompt_channel_id' => ['nullable', 'integer'],
            'target_channel_id' => ['nullable', 'integer'],
            'notify_manager_on_blocker' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $validated = $this->normalizeAndValidateReminderSettings($validated);
        $this->authorizeScheduleSelection($validated, $organization, $request, $currentOrganization);

        if ($validated['recipient_mode'] === 'selected_members' && empty($validated['participant_user_ids'])) {
            return back()->withErrors([
                'participant_user_ids' => __('Select at least one recipient.'),
            ])->withInput();
        }

        if ($response = $this->validateDeliveryConfiguration($validated, $organization->id)) {
            return $response;
        }

        if ($response = $this->validateManagerBlockerAlertConfiguration($validated, $organization->id)) {
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
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeScheduleWorkspace($schedule, $organization, $request, $currentOrganization);

        $workspaces = $currentOrganization->manageableWorkspacesForUser($organization, $request->user());
        $workspaceIds = $workspaces->pluck('id');
        $isOrganizationManager = $currentOrganization->canManageOrganization($organization, $request->user());

        return Inertia::render('schedules/edit', [
            'schedule' => $schedule->load('workspace', 'template', 'targetIntegration', 'promptChannel', 'targetChannel', 'participants'),
            'workspaces' => $workspaces->map->only(['id', 'name'])->values(),
            'templates' => StandupTemplate::where('organization_id', $organization->id)
                ->when(! $isOrganizationManager, fn ($query) => $query->whereIn('workspace_id', $workspaceIds))
                ->orderBy('name')->get(['id', 'name']),
            'integrations' => Integration::with('channels')
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->when(! $isOrganizationManager, fn ($query) => $query->whereIn('workspace_id', $workspaceIds))
                ->whereIn('provider', ['slack', 'discord'])
                ->orderBy('provider')
                ->orderBy('name')
                ->get(),
            'members' => $organization->users()
                ->with([
                    'workspaces' => fn ($query) => $query
                        ->wherePivot('is_active', true)
                        ->when(! $isOrganizationManager, fn ($query) => $query->whereIn('workspaces.id', $workspaceIds)),
                    'integrationUsers' => fn ($query) => $query->where('provider', 'slack')->select('id', 'integration_id', 'user_id', 'provider'),
                ])
                ->when(! $isOrganizationManager, fn ($query) => $query->whereHas(
                    'workspaces',
                    fn ($query) => $query->whereIn('workspaces.id', $workspaceIds)->where('workspace_user.is_active', true),
                ))
                ->orderBy('name')
                ->get(['users.id', 'name', 'email']),
            'canManageSchedules' => true,
        ]);
    }

    public function update(Request $request, StandupSchedule $schedule, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeScheduleWorkspace($schedule, $organization, $request, $currentOrganization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => TimezoneRules::required(),
            'time_of_day' => ['required', 'date_format:H:i'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'end_time_of_day' => ['nullable', 'date_format:H:i'],
            'reminder_minutes_before_end' => ['nullable', 'array', 'min:1'],
            'reminder_minutes_before_end.*' => ['integer', 'min:1'],
            'reminder_message_template' => ['nullable', 'string', 'max:1000'],
            'reminder_minutes_after' => ['nullable', 'integer', 'min:0'],
            'summary_minutes_after' => ['nullable', 'integer', 'min:1', 'max:1439'],
            'recipient_mode' => ['required', 'string', 'in:all_workspace_members,selected_members'],
            'delivery_method' => ['required', 'string', 'in:email,slack_dm,slack_channel,discord_channel'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['integer'],
            'target_integration_id' => ['nullable', 'integer'],
            'prompt_channel_id' => ['nullable', 'integer'],
            'target_channel_id' => ['nullable', 'integer'],
            'notify_manager_on_blocker' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $validated = $this->normalizeAndValidateReminderSettings($validated);
        $this->authorizeScheduleSelection([
            ...$validated,
            'workspace_id' => $schedule->workspace_id,
            'standup_template_id' => $schedule->standup_template_id,
        ], $organization, $request, $currentOrganization);

        if ($validated['recipient_mode'] === 'selected_members' && empty($validated['participant_user_ids'])) {
            return back()->withErrors([
                'participant_user_ids' => __('Select at least one recipient.'),
            ])->withInput();
        }

        if ($response = $this->validateDeliveryConfiguration($validated, $organization->id)) {
            return $response;
        }

        if ($response = $this->validateManagerBlockerAlertConfiguration($validated, $organization->id)) {
            return $response;
        }

        DB::transaction(function () use ($schedule, $validated): void {
            $schedule->update([
                ...Arr::except($validated, 'participant_user_ids'),
                'timezone' => TimezoneRules::normalize($validated['timezone']),
            ]);

            if (! $schedule->is_active) {
                $this->cancelScheduledRuns($schedule);
            }

            $this->syncParticipants($schedule, $validated['participant_user_ids'] ?? []);
        });

        return to_route('schedules.index');
    }

    public function destroy(Request $request, StandupSchedule $schedule, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeScheduleWorkspace($schedule, $organization, $request, $currentOrganization);

        $schedule->delete();

        return to_route('schedules.index');
    }

    public function toggleActive(Request $request, StandupSchedule $schedule, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeScheduleWorkspace($schedule, $organization, $request, $currentOrganization);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];

        DB::transaction(function () use ($schedule, $isActive): void {
            $schedule->update(['is_active' => $isActive]);

            if (! $isActive) {
                $this->cancelScheduledRuns($schedule);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $schedule->is_active ? __('Schedule resumed.') : __('Schedule paused.'),
        ]);

        return to_route('schedules.index');
    }

    public function sendNow(
        Request $request,
        StandupSchedule $schedule,
        CurrentOrganization $currentOrganization,
        StandupMessageService $messages,
        StandupTimingService $timing,
    ): RedirectResponse {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $schedule->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeScheduleWorkspace($schedule, $organization, $request, $currentOrganization);

        $baseScheduleDate = now($schedule->timezone)->toDateString();
        $scheduleDate = $this->nextAvailableManualRunDate($schedule, $baseScheduleDate);

        $runAttributes = [
            'organization_id' => $schedule->organization_id,
            'workspace_id' => $schedule->workspace_id,
            'standup_template_id' => $schedule->standup_template_id,
            'status' => 'scheduled',
        ];

        $run = StandupRun::create([
            'standup_schedule_id' => $schedule->id,
            'run_date' => $scheduleDate,
            ...$runAttributes,
        ]);

        Log::info('Manual standup send requested', [
            'standup_schedule_id' => $schedule->id,
            'standup_run_id' => $run->id,
            'base_run_date' => $baseScheduleDate,
            'run_date' => $scheduleDate,
            'run_was_created' => $run->wasRecentlyCreated,
            'delivery_method' => $schedule->delivery_method,
            'recipient_mode' => $schedule->recipient_mode,
        ]);

        $timing->snapshotManual($run, $schedule);
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
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeScheduleWorkspace($schedule, $organization, $request, $currentOrganization);

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
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeScheduleWorkspace($schedule, $organization, $request, $currentOrganization);

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

        $schedule->participants()->sync($activeWorkspaceUserIds);
    }

    private function cancelScheduledRuns(StandupSchedule $schedule): void
    {
        $schedule->runs()
            ->where('status', 'scheduled')
            ->update(['status' => 'cancelled']);
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
                ->whereKey((int) $validated['target_integration_id'])
                ->first()
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

    /** @param array<string, mixed> $validated */
    private function validateManagerBlockerAlertConfiguration(array $validated, int $organizationId): ?RedirectResponse
    {
        if (! ($validated['notify_manager_on_blocker'] ?? false)) {
            return null;
        }

        $hasActiveSlackIntegration = ! empty($validated['target_integration_id'])
            && Integration::query()
                ->whereKey($validated['target_integration_id'])
                ->where('organization_id', $organizationId)
                ->where('provider', 'slack')
                ->where('is_active', true)
                ->exists();

        if ($hasActiveSlackIntegration) {
            return null;
        }

        return back()->withErrors([
            'notify_manager_on_blocker' => __('Select an active Slack integration before enabling private blocker alerts.'),
        ])->withInput();
    }

    /** @param array<string, mixed> $validated */
    private function authorizeScheduleSelection(
        array $validated,
        Organization $organization,
        Request $request,
        CurrentOrganization $currentOrganization,
    ): void {
        $workspaceIds = $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->pluck('id');
        abort_unless($workspaceIds->contains((int) $validated['workspace_id']), 404);

        abort_unless(StandupTemplate::query()
            ->whereKey($validated['standup_template_id'])
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($validated): void {
                $query->whereNull('workspace_id')
                    ->orWhere('workspace_id', $validated['workspace_id']);
            })
            ->exists(), 404);

        if (! empty($validated['target_integration_id'])) {
            abort_unless(Integration::query()
                ->whereKey($validated['target_integration_id'])
                ->where('organization_id', $organization->id)
                ->where('workspace_id', $validated['workspace_id'])
                ->exists(), 404);
        }
    }

    private function authorizeScheduleWorkspace(
        StandupSchedule $schedule,
        Organization $organization,
        Request $request,
        CurrentOrganization $currentOrganization,
    ): void {
        abort_unless(
            $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->contains('id', $schedule->workspace_id),
            404,
        );
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function normalizeAndValidateReminderSettings(array $validated): array
    {
        $start = Carbon::createFromFormat('H:i', $validated['time_of_day']);
        $legacyDuration = (int) ($validated['summary_minutes_after'] ?? 90);
        $end = ! empty($validated['end_time_of_day'])
            ? Carbon::createFromFormat('H:i', $validated['end_time_of_day'])
            : $start->copy()->addMinutes($legacyDuration);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $duration = (int) $start->diffInMinutes($end);
        $defaultOffsets = collect([60, 30, 15, 5])->filter(fn (int $offset): bool => $offset < $duration)->all();
        $rawOffsets = $validated['reminder_minutes_before_end'] ?? $defaultOffsets;
        $offsets = collect(is_array($rawOffsets) ? $rawOffsets : [])
            ->map(fn ($offset): int => (int) $offset)
            ->unique()
            ->sortDesc()
            ->values();
        $invalidOffset = $offsets->first(fn (int $offset): bool => $offset <= 0 || $offset >= $duration);

        if ($invalidOffset !== null) {
            throw ValidationException::withMessages([
                'reminder_minutes_before_end' => __('Every reminder must occur after the start and before the end time.'),
            ]);
        }

        $template = $validated['reminder_message_template'] ?? StandupReminderMessageService::DEFAULT_TEMPLATE;
        preg_match_all('/\{[^}]+\}/', $template, $matches);
        $unsupported = collect($matches[0])->diff(['{title}', '{time_remaining}', '{end_time}', '{url}']);

        if ($unsupported->isNotEmpty()) {
            throw ValidationException::withMessages([
                'reminder_message_template' => __('Unsupported reminder placeholder: :placeholder', [
                    'placeholder' => $unsupported->first(),
                ]),
            ]);
        }

        return [
            ...$validated,
            'end_time_of_day' => $end->format('H:i'),
            'reminder_minutes_before_end' => $offsets->all(),
            'reminder_message_template' => $template,
            // Keep legacy columns populated during the compatibility window.
            'summary_minutes_after' => $duration,
            'reminder_minutes_after' => max(0, $duration - (int) ($offsets->last() ?? 0)),
        ];
    }
}
