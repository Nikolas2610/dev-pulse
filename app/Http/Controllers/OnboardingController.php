<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\Organization;
use App\Models\StandupQuestion;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TeamInvitationNotification;
use App\Services\CurrentOrganization;
use App\Services\StandupReminderMessageService;
use App\Support\TimezoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    private const STEPS = ['organization', 'integrations', 'members', 'template', 'schedule', 'complete'];

    public function index(Request $request, CurrentOrganization $currentOrganization): Response|RedirectResponse
    {
        $user = $request->user();
        $organization = $currentOrganization->fromRequest($request);

        if ($organization?->onboarding_completed_at) {
            return to_route('dashboard');
        }

        if ($organization && ! $currentOrganization->canManageOrganization($organization, $user)) {
            return to_route('dashboard');
        }

        $workspace = $organization
            ? $currentOrganization->currentWorkspaceForUser($organization, $user)
            : null;
        $furthestStep = $organization?->onboarding_step ?: 'organization';
        $requestedStep = $request->string('step')->toString();
        $currentStep = $this->canVisit($requestedStep, $furthestStep) ? $requestedStep : $furthestStep;
        $template = $workspace
            ? StandupTemplate::query()->where('workspace_id', $workspace->id)->where('is_default', true)->with('questions')->first()
            : null;
        $integrations = $organization && $workspace
            ? Integration::query()
                ->with('channels:id,integration_id,name,type')
                ->where('organization_id', $organization->id)
                ->where('workspace_id', $workspace->id)
                ->whereIn('provider', ['slack', 'discord'])
                ->get()
            : collect();

        return Inertia::render('onboarding/index', [
            'onboarding' => [
                'currentStep' => $currentStep,
                'furthestStep' => $furthestStep,
            ],
            'organization' => $organization?->only(['id', 'name', 'timezone']),
            'workspace' => $workspace?->only(['id', 'name', 'timezone']),
            'template' => $template ? $this->templatePayload($template) : null,
            'integrations' => $integrations->map(fn (Integration $integration) => [
                'id' => $integration->id,
                'provider' => $integration->provider,
                'name' => $integration->name,
                'client_id' => $integration->provider === 'slack' ? $integration->client_id : null,
                'is_connected' => $integration->provider === 'slack'
                    ? filled($integration->bot_token)
                    : filled($integration->webhook_url),
                'has_credentials' => $integration->provider === 'slack'
                    && filled($integration->client_id)
                    && filled($integration->client_secret),
                'channels' => $integration->channels,
            ])->values(),
            'membersCount' => $organization?->users()->count() ?? 0,
            'slackConfiguredGlobally' => filled(config('services.slack.client_id'))
                && filled(config('services.slack.client_secret')),
        ]);
    }

    public function saveOrganization(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'timezone' => TimezoneRules::required(),
        ]);
        $timezone = TimezoneRules::normalize($validated['timezone']);
        $user = $request->user();
        $organization = $currentOrganization->fromRequest($request);

        if ($organization) {
            abort_unless($currentOrganization->canManageOrganization($organization, $user), 403);
            $workspace = $currentOrganization->currentWorkspaceForUser($organization, $user);
            abort_unless($workspace !== null, 404);

            DB::transaction(function () use ($organization, $workspace, $validated, $timezone, $user): void {
                $organization->update(['name' => $validated['organization_name'], 'timezone' => $timezone]);
                $workspace->update(['name' => $validated['workspace_name'], 'timezone' => $timezone]);
                $user->update(['timezone' => $timezone]);
                $this->advanceTo($organization, 'integrations');
            });
        } else {
            DB::transaction(function () use ($validated, $timezone, $user): void {
                $organization = Organization::create([
                    'name' => $validated['organization_name'],
                    'slug' => $this->uniqueOrganizationSlug($validated['organization_name']),
                    'timezone' => $timezone,
                    'is_active' => true,
                    'onboarding_step' => 'integrations',
                ]);
                $workspace = Workspace::create([
                    'organization_id' => $organization->id,
                    'name' => $validated['workspace_name'],
                    'slug' => Str::slug($validated['workspace_name']) ?: 'workspace',
                    'timezone' => $timezone,
                    'is_active' => true,
                ]);
                $organization->users()->attach($user->id, ['role' => 'admin']);
                $workspace->users()->attach($user->id, ['role' => 'manager', 'is_active' => true]);
                $user->update([
                    'current_organization_id' => $organization->id,
                    'current_workspace_id' => $workspace->id,
                    'timezone' => $timezone,
                    'onboarding_required' => false,
                ]);
                $this->createDefaultTemplate($organization, $workspace, $user);
            });
        }

        return to_route('onboarding.index', ['step' => 'integrations']);
    }

    public function saveSlack(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        [$organization, $workspace] = $this->context($request, $currentOrganization);
        $existing = $this->integration($organization, $workspace, 'slack');
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [$existing?->client_secret ? 'nullable' : 'required', 'string', 'max:500'],
            'signing_secret' => ['nullable', 'string', 'max:500'],
        ]);

        Integration::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'provider' => 'slack'],
            [
                'name' => 'Slack',
                'client_id' => $validated['client_id'],
                'client_secret' => $validated['client_secret'] ?: $existing?->client_secret,
                'signing_secret' => $validated['signing_secret'] ?: $existing?->signing_secret,
                'is_active' => true,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Slack credentials saved.')]);

        return to_route('onboarding.index', ['step' => 'integrations']);
    }

    public function saveDiscord(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        [$organization, $workspace] = $this->context($request, $currentOrganization);
        $existing = $this->integration($organization, $workspace, 'discord');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'webhook_url' => [
                $existing?->webhook_url ? 'nullable' : 'required',
                'url',
                'max:1000',
                'regex:/^https:\/\/(discord\.com|discordapp\.com)\/api\/webhooks\//',
            ],
        ]);

        Integration::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'provider' => 'discord'],
            [
                'name' => $validated['name'],
                'webhook_url' => $validated['webhook_url'] ?: $existing?->webhook_url,
                'connected_by_user_id' => $request->user()->id,
                'connected_at' => now(),
                'is_active' => true,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Discord connected.')]);

        return to_route('onboarding.index', ['step' => 'integrations']);
    }

    public function finishIntegrations(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        [$organization] = $this->context($request, $currentOrganization);
        $this->advanceTo($organization, 'members');

        return to_route('onboarding.index', ['step' => 'members']);
    }

    public function inviteMembers(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        [$organization, $workspace] = $this->context($request, $currentOrganization);
        $validated = $request->validate([
            'invitations' => ['required', 'array', 'min:1', 'max:20'],
            'invitations.*.name' => ['required', 'string', 'max:255'],
            'invitations.*.email' => [
                'required',
                'email',
                'max:255',
                'distinct:ignore_case',
                Rule::notIn([Str::lower($request->user()->email)]),
            ],
            'invitations.*.organization_role' => ['required', 'string', 'in:admin,manager,member'],
            'invitations.*.workspace_role' => ['required', 'string', 'in:manager,member'],
        ]);

        foreach ($validated['invitations'] as $invitation) {
            $user = User::firstOrCreate(
                ['email' => Str::lower($invitation['email'])],
                [
                    'name' => $invitation['name'],
                    'password' => Hash::make(Str::password(32)),
                    'email_verified_at' => now(),
                    'current_organization_id' => $organization->id,
                    'current_workspace_id' => $workspace->id,
                    'timezone' => $workspace->timezone,
                    'is_active' => true,
                ],
            );
            $organization->users()->syncWithoutDetaching([
                $user->id => ['role' => $invitation['organization_role']],
            ]);
            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $invitation['workspace_role'], 'is_active' => true],
            ]);
            $token = $user->wasRecentlyCreated ? Password::createToken($user) : null;
            $user->notify(new TeamInvitationNotification(
                organization: $organization,
                workspace: $workspace,
                invitedBy: $request->user(),
                role: $invitation['organization_role'],
                token: $token,
            ));
        }

        $this->advanceTo($organization, 'template');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitations sent.')]);

        return to_route('onboarding.index', ['step' => 'template']);
    }

    public function skipMembers(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        [$organization] = $this->context($request, $currentOrganization);
        $this->advanceTo($organization, 'template');

        return to_route('onboarding.index', ['step' => 'template']);
    }

    public function saveTemplate(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        [$organization, $workspace] = $this->context($request, $currentOrganization);
        $template = StandupTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.question_key' => ['nullable', 'string', 'max:255'],
            'questions.*.work_item_relation' => ['nullable', 'string', 'in:worked_on,planned,blocker,review'],
            'questions.*.answer_type' => ['required', 'string', 'in:text,textarea,boolean,select,multi_select,number'],
            'questions.*.options_json' => ['nullable', 'array'],
            'questions.*.depends_on_question_id' => ['nullable', 'integer'],
            'questions.*.depends_on_question_index' => ['nullable', 'integer', 'min:0'],
            'questions.*.depends_on_operator' => ['nullable', 'string', 'in:equals'],
            'questions.*.depends_on_value' => ['nullable', 'string', 'max:255'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);
        $questions = $this->normalizeQuestionOptions($validated['questions']);

        DB::transaction(function () use ($template, $validated, $questions): void {
            $template->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => true,
                'is_active' => true,
            ]);
            $saved = [];

            foreach ($questions as $question) {
                $saved[] = StandupQuestion::updateOrCreate(
                    ['id' => $question['id'] ?? null, 'standup_template_id' => $template->id],
                    [
                        'question_text' => $question['question_text'],
                        'question_key' => $question['question_key'] ?? null,
                        'work_item_relation' => $question['work_item_relation'] ?? null,
                        'answer_type' => $question['answer_type'],
                        'options_json' => $question['options_json'] ?? null,
                        'is_required' => (bool) ($question['is_required'] ?? true),
                        'sort_order' => $question['sort_order'],
                    ],
                );
            }

            foreach ($questions as $index => $question) {
                $dependencyIndex = $question['depends_on_question_index'] ?? null;
                $dependency = is_numeric($dependencyIndex) ? ($saved[(int) $dependencyIndex] ?? null) : null;
                $dependencyId = $dependency?->id;
                $saved[$index]->update([
                    'depends_on_question_id' => $dependencyId,
                    'depends_on_operator' => $dependencyId ? 'equals' : null,
                    'depends_on_value' => $dependencyId ? ($question['depends_on_value'] ?? null) : null,
                ]);
            }

            $template->questions()->whereNotIn('id', array_map(fn (StandupQuestion $question) => $question->id, $saved))->delete();
        });

        $this->advanceTo($organization, 'schedule');

        return to_route('onboarding.index', ['step' => 'schedule']);
    }

    public function saveSchedule(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        [$organization, $workspace] = $this->context($request, $currentOrganization);
        $template = StandupTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => TimezoneRules::required(),
            'time_of_day' => ['required', 'date_format:H:i'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'delivery_method' => ['required', 'string', 'in:email,slack_dm,slack_channel,discord_channel'],
            'target_integration_id' => ['nullable', 'integer'],
            'prompt_channel_id' => ['nullable', 'integer'],
            'target_channel_id' => ['nullable', 'integer'],
            'end_time_of_day' => ['nullable', 'date_format:H:i'],
            'reminder_minutes_before_end' => ['nullable', 'array', 'min:1'],
            'reminder_minutes_before_end.*' => ['integer', 'min:1'],
            'reminder_message_template' => ['nullable', 'string', 'max:1000'],
            'reminder_minutes_after' => ['nullable', 'integer', 'min:0'],
            'summary_minutes_after' => ['nullable', 'integer', 'min:1', 'max:1439'],
        ]);

        $start = Carbon::createFromFormat('H:i', $validated['time_of_day']);
        $end = ! empty($validated['end_time_of_day'])
            ? Carbon::createFromFormat('H:i', $validated['end_time_of_day'])
            : $start->copy()->addMinutes((int) ($validated['summary_minutes_after'] ?? 90));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $duration = (int) $start->diffInMinutes($end);
        $defaultOffsets = collect([60, 30, 15, 5])->filter(fn (int $offset): bool => $offset < $duration)->all();
        $rawOffsets = $validated['reminder_minutes_before_end'] ?? $defaultOffsets;
        $offsets = collect(is_array($rawOffsets) ? $rawOffsets : [])
            ->map(fn ($offset): int => (int) $offset)->unique()->sortDesc()->values();

        if ($offsets->contains(fn (int $offset): bool => $offset <= 0 || $offset >= $duration)) {
            throw ValidationException::withMessages([
                'reminder_minutes_before_end' => __('Every reminder must occur after the start and before the end time.'),
            ]);
        }

        $templateText = $validated['reminder_message_template'] ?? StandupReminderMessageService::DEFAULT_TEMPLATE;
        preg_match_all('/\{[^}]+\}/', $templateText, $matches);
        $unsupported = collect($matches[0])->diff(['{title}', '{time_remaining}', '{end_time}', '{url}']);

        if ($unsupported->isNotEmpty()) {
            throw ValidationException::withMessages([
                'reminder_message_template' => __('Unsupported reminder placeholder: :placeholder', ['placeholder' => $unsupported->first()]),
            ]);
        }

        if ($validated['delivery_method'] !== 'email') {
            $provider = $validated['delivery_method'] === 'discord_channel' ? 'discord' : 'slack';
            $integration = Integration::query()
                ->whereKey($validated['target_integration_id'] ?? null)
                ->where('organization_id', $organization->id)
                ->where('workspace_id', $workspace->id)
                ->where('provider', $provider)
                ->first();

            if (! $integration) {
                throw ValidationException::withMessages([
                    'target_integration_id' => __('Choose a connected integration for this delivery method.'),
                ]);
            }

            if ($validated['delivery_method'] === 'slack_channel' && ! $integration->channels()->whereKey($validated['prompt_channel_id'] ?? null)->exists()) {
                throw ValidationException::withMessages([
                    'prompt_channel_id' => __('Choose a synced Slack channel.'),
                ]);
            }
        }

        DB::transaction(function () use ($organization, $workspace, $template, $validated, $request, $end, $offsets, $templateText, $duration): void {
            StandupSchedule::create([
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
                'standup_template_id' => $template->id,
                'name' => $validated['name'],
                'timezone' => TimezoneRules::normalize($validated['timezone']),
                'time_of_day' => $validated['time_of_day'],
                'end_time_of_day' => $end->format('H:i'),
                'days_of_week' => array_values(array_unique($validated['days_of_week'])),
                'recipient_mode' => 'all_workspace_members',
                'delivery_method' => $validated['delivery_method'],
                'reminder_minutes_before_end' => $offsets->all(),
                'reminder_message_template' => $templateText,
                'reminder_minutes_after' => max(0, $duration - (int) ($offsets->last() ?? 0)),
                'summary_minutes_after' => $duration,
                'target_integration_id' => $validated['target_integration_id'] ?? null,
                'prompt_channel_id' => $validated['prompt_channel_id'] ?? null,
                'target_channel_id' => $validated['target_channel_id'] ?? null,
                'is_active' => true,
                'created_by_user_id' => $request->user()->id,
            ]);
            $organization->update([
                'onboarding_step' => 'complete',
                'onboarding_completed_at' => now(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your first standup is scheduled.')]);

        return to_route('dashboard');
    }

    /** @return array{Organization, Workspace} */
    private function context(Request $request, CurrentOrganization $currentOrganization): array
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $currentOrganization->canManageOrganization($organization, $request->user()), 403);
        $workspace = $currentOrganization->currentWorkspaceForUser($organization, $request->user());
        abort_unless($workspace !== null, 404);

        return [$organization, $workspace];
    }

    private function integration(Organization $organization, Workspace $workspace, string $provider): ?Integration
    {
        return Integration::query()
            ->where('organization_id', $organization->id)
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->first();
    }

    private function advanceTo(Organization $organization, string $step): void
    {
        $current = array_search($organization->onboarding_step, self::STEPS, true);
        $target = array_search($step, self::STEPS, true);

        if ($current === false || ($target !== false && $target > $current)) {
            $organization->update(['onboarding_step' => $step]);
        }
    }

    private function canVisit(string $requested, string $furthest): bool
    {
        $requestedIndex = array_search($requested, self::STEPS, true);
        $furthestIndex = array_search($furthest, self::STEPS, true);

        return $requestedIndex !== false && $furthestIndex !== false && $requestedIndex <= $furthestIndex;
    }

    private function uniqueOrganizationSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function createDefaultTemplate(Organization $organization, Workspace $workspace, User $user): void
    {
        $template = StandupTemplate::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Daily Standup',
            'description' => 'A focused daily check-in for progress, priorities, and blockers.',
            'is_default' => true,
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);
        StandupQuestion::create([
            'standup_template_id' => $template->id,
            'question_text' => 'What did you complete yesterday?',
            'question_key' => 'yesterday',
            'work_item_relation' => 'worked_on',
            'answer_type' => 'textarea',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        StandupQuestion::create([
            'standup_template_id' => $template->id,
            'question_text' => 'What are you focusing on today?',
            'question_key' => 'today',
            'work_item_relation' => 'planned',
            'answer_type' => 'textarea',
            'is_required' => true,
            'sort_order' => 2,
        ]);
        $blocker = StandupQuestion::create([
            'standup_template_id' => $template->id,
            'question_text' => 'Are you blocked by anything?',
            'question_key' => 'blocker',
            'answer_type' => 'boolean',
            'is_required' => true,
            'sort_order' => 3,
        ]);
        StandupQuestion::create([
            'standup_template_id' => $template->id,
            'question_text' => 'What are you blocked by?',
            'question_key' => 'blocker_detail',
            'work_item_relation' => 'blocker',
            'answer_type' => 'textarea',
            'depends_on_question_id' => $blocker->id,
            'depends_on_operator' => 'equals',
            'depends_on_value' => 'Yes',
            'is_required' => true,
            'sort_order' => 4,
        ]);
    }

    /** @return array<string, mixed> */
    private function templatePayload(StandupTemplate $template): array
    {
        $questions = StandupQuestion::query()
            ->where('standup_template_id', $template->id)
            ->orderBy('sort_order')
            ->get();
        $questionIds = $questions->pluck('id')->values();

        return [
            ...$template->only(['id', 'name', 'description']),
            'questions' => $questions->map(fn (StandupQuestion $question) => [
                ...Arr::only($question->toArray(), [
                    'id', 'question_text', 'question_key', 'work_item_relation', 'answer_type', 'options_json',
                    'depends_on_question_id', 'depends_on_operator', 'depends_on_value', 'is_required', 'sort_order',
                ]),
                'depends_on_question_index' => $question->depends_on_question_id
                    ? $questionIds->search($question->depends_on_question_id)
                    : null,
            ]),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestionOptions(array $questions): array
    {
        $errors = [];
        $usedRelations = [];

        foreach ($questions as $index => $question) {
            $rawOptions = is_array($question['options_json'] ?? null) ? $question['options_json'] : [];
            $options = array_values(array_unique(array_filter(
                array_map(fn (mixed $option) => trim((string) $option), $rawOptions),
            )));

            if (in_array($question['answer_type'] ?? null, ['select', 'multi_select'], true) && $options === []) {
                $errors["questions.{$index}.options_json"] = __('Add at least one answer option.');
            }

            $relation = $question['work_item_relation'] ?? null;
            if ($relation && in_array($relation, $usedRelations, true)) {
                $errors["questions.{$index}.work_item_relation"] = __('Each Jira ticket relation can be used only once per template.');
            } elseif ($relation) {
                $usedRelations[] = $relation;
            }

            $questions[$index]['options_json'] = $options === [] ? null : $options;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $questions;
    }
}
