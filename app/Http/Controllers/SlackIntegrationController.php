<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSlackChannelsJob;
use App\Jobs\SyncSlackUsersJob;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\User;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SlackIntegrationController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        $integration = $this->workspaceIntegration($organization?->id, $workspace?->id);
        $credentials = $this->oauthCredentials($integration);

        return Inertia::render('integrations/index', [
            'provider' => 'slack',
            'integration' => $integration,
            'slackUsers' => $this->slackUsers($integration),
            'isConfigured' => filled($credentials['client_id']) && filled($credentials['client_secret']),
            'hasWorkspaceCredentials' => $integration !== null && filled($integration->client_id) && filled($integration->client_secret),
            'hasSigningSecret' => filled($integration?->signing_secret) || filled(config('services.slack.signing_secret')),
            'redirectUri' => $this->redirectUri(),
            'interactionsUrl' => route('slack.interactions'),
            'eventsUrl' => route('slack.events'),
        ]);
    }

    public function storeCredentials(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);

        $workspace = $currentOrganization->currentWorkspaceForUser($organization, $request->user());
        abort_unless($workspace !== null, 403);

        $existingIntegration = $this->workspaceIntegration($organization->id, $workspace->id);

        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [$existingIntegration?->client_secret ? 'nullable' : 'required', 'string', 'max:500'],
            'signing_secret' => ['nullable', 'string', 'max:500'],
        ]);

        Integration::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
                'provider' => 'slack',
            ],
            [
                'name' => 'Slack',
                'client_id' => $validated['client_id'],
                'client_secret' => $validated['client_secret'] ?: $existingIntegration?->client_secret,
                'signing_secret' => $validated['signing_secret'] ?: $existingIntegration?->signing_secret,
                'is_active' => true,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Slack app credentials saved.')]);

        return to_route('integrations.slack.index');
    }

    public function connect(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        abort_unless($organization && $workspace, 403);

        $integration = $this->workspaceIntegration($organization->id, $workspace->id);
        $credentials = $this->oauthCredentials($integration);
        $returnToOnboarding = $request->boolean('onboarding');
        $request->session()->put('slack_return_to_onboarding', $returnToOnboarding);

        if (! filled($credentials['client_id']) || ! filled($credentials['client_secret'])) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Add Slack client ID and client secret for this workspace before connecting Slack.'),
            ]);

            return $returnToOnboarding
                ? to_route('onboarding.index', ['step' => 'integrations'])
                : to_route('integrations.slack.index');
        }

        $request->session()->put('slack_oauth_state', $state = Str::random(40));
        $request->session()->put('slack_integration_id', $integration?->id);

        $query = http_build_query([
            'client_id' => $credentials['client_id'],
            'scope' => 'chat:write,users:read,users:read.email,channels:read,groups:read,im:write,im:history,channels:join',
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);

        return redirect()->away('https://slack.com/oauth/v2/authorize?'.$query);
    }

    public function callback(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization !== null, 403);
        $workspace = $currentOrganization->currentWorkspaceForUser($organization, $request->user());
        abort_unless($workspace !== null, 403);

        if ($request->filled('error')) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Slack did not authorize the connection.'),
            ]);

            return $this->oauthReturn($request);
        }

        abort_unless(
            filled($request->string('state')->toString())
            && hash_equals((string) $request->session()->pull('slack_oauth_state'), $request->string('state')->toString()),
            403,
        );

        $integrationId = $request->session()->pull('slack_integration_id');
        $integration = $integrationId
            ? Integration::query()
                ->whereKey($integrationId)
                ->where('organization_id', $organization->id)
                ->where('workspace_id', $workspace->id)
                ->first()
            : null;
        $integration ??= $this->workspaceIntegration($organization->id, $workspace->id);
        $credentials = $this->oauthCredentials($integration);

        $payload = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'redirect_uri' => $this->redirectUri(),
            'code' => $request->string('code')->toString(),
        ])->throw()->json();

        $integration ??= new Integration([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'provider' => 'slack',
        ]);
        $integration->forceFill([
            'name' => $payload['team']['name'] ?? 'Slack',
            'external_id' => $payload['team']['id'] ?? null,
            'access_token' => $payload['access_token'] ?? null,
            'bot_token' => $payload['access_token'] ?? null,
            'scopes_json' => explode(',', $payload['scope'] ?? ''),
            'connected_by_user_id' => $request->user()->id,
            'connected_at' => now(),
            'is_active' => true,
        ])->save();

        SyncSlackUsersJob::dispatch($integration->id);
        SyncSlackChannelsJob::dispatch($integration->id);

        return $this->oauthReturn($request);
    }

    private function oauthReturn(Request $request): RedirectResponse
    {
        return $request->session()->pull('slack_return_to_onboarding', false)
            ? to_route('onboarding.index', ['step' => 'integrations'])
            : to_route('integrations.slack.index');
    }

    private function redirectUri(): string
    {
        return config('services.slack.redirect_uri') ?: route('integrations.slack.callback');
    }

    private function workspaceIntegration(?int $organizationId, ?int $workspaceId): ?Integration
    {
        if (! $organizationId || ! $workspaceId) {
            return null;
        }

        return Integration::query()
            ->withCount(['users', 'channels'])
            ->where('organization_id', $organizationId)
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'slack')
            ->latest()
            ->first();
    }

    /**
     * @return array{client_id: ?string, client_secret: ?string}
     */
    private function oauthCredentials(?Integration $integration): array
    {
        return [
            'client_id' => $integration?->client_id ?: config('services.slack.client_id'),
            'client_secret' => $integration?->client_secret ?: config('services.slack.client_secret'),
        ];
    }

    public function syncUsers(Request $request, Integration $integration, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $this->authorizeIntegration($request, $integration, $currentOrganization);
        SyncSlackUsersJob::dispatch($integration->id);

        return to_route('integrations.slack.index');
    }

    public function syncChannels(Request $request, Integration $integration, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $this->authorizeIntegration($request, $integration, $currentOrganization);
        SyncSlackChannelsJob::dispatch($integration->id);

        return to_route('integrations.slack.index');
    }

    public function importUsers(Request $request, Integration $integration, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $integration->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $workspace = $integration->workspace
            ?: $currentOrganization->currentWorkspaceForUser($organization, $request->user());
        abort_unless($workspace && $workspace->organization_id === $organization->id, 404);
        abort_unless(
            $currentOrganization->workspacesForUser($organization, $request->user())->contains('id', $workspace->id),
            404,
        );

        $validated = $request->validate([
            'integration_user_ids' => ['required', 'array', 'min:1'],
            'integration_user_ids.*' => ['integer'],
            'organization_role' => ['required', 'string', 'in:admin,manager,member'],
            'workspace_role' => ['required', 'string', 'in:manager,member'],
        ]);

        $slackUsers = IntegrationUser::query()
            ->where('integration_id', $integration->id)
            ->whereIn('id', $validated['integration_user_ids'])
            ->get();

        if ($slackUsers->count() !== count(array_unique($validated['integration_user_ids']))) {
            return back()->withErrors([
                'integration_user_ids' => __('Select synced Slack users for this workspace.'),
            ])->withInput();
        }

        if ($slackUsers->contains(fn (IntegrationUser $slackUser): bool => blank($slackUser->external_email))) {
            return back()->withErrors([
                'integration_user_ids' => __('Only Slack users with an email address can be imported.'),
            ])->withInput();
        }

        foreach ($slackUsers as $slackUser) {
            $user = User::firstOrCreate(
                ['email' => Str::lower((string) $slackUser->external_email)],
                [
                    'name' => $slackUser->display_name ?: $slackUser->external_username ?: $slackUser->external_email,
                    'password' => Hash::make(Str::password(32)),
                    'email_verified_at' => now(),
                    'current_organization_id' => $organization->id,
                    'current_workspace_id' => $workspace->id,
                    'timezone' => $workspace->timezone,
                    'avatar_url' => $slackUser->avatar_url,
                    'is_active' => true,
                ],
            );

            $user->forceFill([
                'current_organization_id' => $user->current_organization_id ?: $organization->id,
                'current_workspace_id' => $user->current_workspace_id ?: $workspace->id,
                'avatar_url' => $user->avatar_url ?: $slackUser->avatar_url,
                'is_active' => true,
            ])->save();

            $organization->users()->syncWithoutDetaching([
                $user->id => ['role' => $validated['organization_role']],
            ]);
            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $validated['workspace_role'], 'is_active' => true],
            ]);

            if ($validated['workspace_role'] !== 'manager') {
                DB::table('workspace_user')
                    ->where('workspace_id', $workspace->id)
                    ->where('manager_user_id', $user->id)
                    ->update(['manager_user_id' => null, 'updated_at' => now()]);
            }

            $slackUser->forceFill(['user_id' => $user->id])->save();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{1} Slack user imported.|[2,*] Slack users imported.', $slackUsers->count()),
        ]);

        return to_route('integrations.slack.index');
    }

    private function authorizeIntegration(Request $request, Integration $integration, CurrentOrganization $currentOrganization): void
    {
        $organization = $currentOrganization->fromRequest($request);

        abort_unless($organization && $integration->organization_id === $organization->id, 404);
        $workspace = $currentOrganization->currentWorkspaceForUser($organization, $request->user());
        abort_unless($workspace && $integration->workspace_id === $workspace->id, 404);
    }

    /**
     * @return array<int, array{id: int, external_user_id: string, external_username: ?string, external_email: ?string, display_name: ?string, avatar_url: ?string, is_linked: bool, linked_user: array{id: int, name: string, email: string}|null}>
     */
    private function slackUsers(?Integration $integration): array
    {
        if (! $integration) {
            return [];
        }

        return $integration->users()
            ->with('user:id,name,email')
            ->orderByRaw('user_id is null desc')
            ->orderBy('display_name')
            ->get()
            ->map(fn (IntegrationUser $slackUser) => [
                'id' => $slackUser->id,
                'external_user_id' => $slackUser->external_user_id,
                'external_username' => $slackUser->external_username,
                'external_email' => $slackUser->external_email,
                'display_name' => $slackUser->display_name,
                'avatar_url' => $slackUser->avatar_url,
                'is_linked' => filled($slackUser->user_id),
                'linked_user' => $slackUser->user
                    ? [
                        'id' => $slackUser->user->id,
                        'name' => $slackUser->user->name,
                        'email' => $slackUser->user->email,
                    ]
                    : null,
            ])
            ->all();
    }
}
