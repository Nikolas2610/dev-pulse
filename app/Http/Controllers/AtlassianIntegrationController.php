<?php

namespace App\Http\Controllers;

use App\Jobs\SyncAtlassianIntegrationJob;
use App\Models\BitbucketRepository;
use App\Models\Integration;
use App\Models\IntegrationUser;
use App\Models\JiraProject;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AtlassianIntegrationController extends Controller
{
    public function index(Request $request, string $provider, CurrentOrganization $current): Response
    {
        $this->provider($provider);
        $organization = $current->fromRequest($request);
        abort_unless($organization !== null, 404);
        $integration = Integration::query()->where('organization_id', $organization->id)->where('provider', $provider)->first();
        $workspaces = $organization->workspaces()->with('users:id,name,email')->get();

        $settings = $integration?->settings() ?? [];

        return Inertia::render('integrations/index', [
            'provider' => $provider,
            'integration' => $integration ? [
                'id' => $integration->id,
                'name' => $integration->name,
                'is_active' => $integration->is_active,
                'connected_at' => $integration->connected_at,
                'settings' => array_intersect_key($settings, array_flip(['site_url', 'cloud_id', 'workspace_slugs', 'last_successful_sync_at', 'last_failed_sync_at', 'last_error', 'webhook_error'])),
                'scopes' => $integration->scopes_json ?? [],
            ] : null,
            'resources' => $provider === 'jira'
                ? JiraProject::query()->where('integration_id', $integration?->id)->with('workspaces:id')->orderBy('name')->get()
                : BitbucketRepository::query()->where('integration_id', $integration?->id)->with(['workspaces:id', 'users:id'])->orderBy('full_name')->get(),
            'externalUsers' => IntegrationUser::query()->where('integration_id', $integration?->id)->with('user:id,name,email')->orderBy('display_name')->get(),
            'workspaces' => $workspaces,
            'availableConnections' => $provider === 'jira' ? data_get($settings, 'available_sites', []) : data_get($settings, 'available_workspaces', []),
        ]);
    }

    public function connect(Request $request, string $provider, CurrentOrganization $current): RedirectResponse
    {
        $this->provider($provider);
        $organization = $current->fromRequest($request);
        abort_unless($organization !== null, 404);
        $state = Str::random(64);
        $request->session()->put("oauth.{$provider}.{$state}", ['organization_id' => $organization->id, 'user_id' => $request->user()->id]);
        if ($provider === 'jira') {
            return redirect()->away('https://auth.atlassian.com/authorize?'.http_build_query([
                'audience' => 'api.atlassian.com', 'client_id' => config('services.jira.client_id'),
                'scope' => 'offline_access read:jira-user read:jira-work manage:jira-webhook', 'redirect_uri' => config('services.jira.redirect_uri'),
                'state' => $state, 'response_type' => 'code', 'prompt' => 'consent',
            ]));
        }

        return redirect()->away('https://bitbucket.org/site/oauth2/authorize?'.http_build_query(['client_id' => config('services.bitbucket.client_id'), 'response_type' => 'code', 'state' => $state]));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->provider($provider);
        $state = (string) $request->query('state');
        $context = $request->session()->pull("oauth.{$provider}.{$state}");
        abort_unless(is_array($context) && (int) $context['user_id'] === $request->user()->id, 403);
        $request->validate(['code' => ['required', 'string']]);

        if ($provider === 'jira') {
            $tokens = Http::post('https://auth.atlassian.com/oauth/token', [
                'grant_type' => 'authorization_code', 'client_id' => config('services.jira.client_id'),
                'client_secret' => config('services.jira.client_secret'), 'code' => $request->query('code'),
                'redirect_uri' => config('services.jira.redirect_uri'),
            ])->throw()->json();
            $sites = Http::withToken($tokens['access_token'])->get('https://api.atlassian.com/oauth/token/accessible-resources')->throw()->json();
            abort_if(count($sites) === 0, 422, 'No accessible Jira site was returned.');
            $site = $sites[0];
            $settings = ['cloud_id' => $site['id'], 'site_url' => $site['url'], 'available_sites' => $sites, 'webhook_secret' => Str::random(64), 'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->toIso8601String()];
        } else {
            $tokens = Http::asForm()->withBasicAuth((string) config('services.bitbucket.client_id'), (string) config('services.bitbucket.client_secret'))->post('https://bitbucket.org/site/oauth2/access_token', ['grant_type' => 'authorization_code', 'code' => $request->query('code')])->throw()->json();
            $workspaces = Http::withToken($tokens['access_token'])->get('https://api.bitbucket.org/2.0/workspaces', ['pagelen' => 100])->throw()->json('values', []);
            $workspaceRows = is_array($workspaces) ? $workspaces : [];
            $settings = ['workspace_slugs' => array_values(array_filter(array_map(fn ($workspace) => is_array($workspace) ? ($workspace['slug'] ?? null) : null, $workspaceRows))), 'available_workspaces' => $workspaceRows, 'webhook_secret' => Str::random(64), 'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 7200))->toIso8601String()];
        }

        $integration = Integration::updateOrCreate(
            ['organization_id' => $context['organization_id'], 'provider' => $provider],
            ['workspace_id' => null, 'name' => ucfirst($provider).' Cloud', 'external_id' => $provider === 'jira' ? (string) data_get($settings, 'cloud_id') : null, 'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'] ?? null, 'settings_json' => $settings, 'scopes_json' => preg_split('/\s+/', (string) ($tokens['scope'] ?? '')), 'connected_by_user_id' => $request->user()->id, 'is_active' => true, 'connected_at' => now()],
        );
        SyncAtlassianIntegrationJob::dispatch($integration->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($provider).' connected. Initial sync queued.']);

        return to_route('integrations.atlassian.index', ['provider' => $provider]);
    }

    public function sync(Request $request, string $provider, Integration $integration, CurrentOrganization $current): RedirectResponse
    {
        $this->authorizeIntegration($request, $provider, $integration, $current);
        SyncAtlassianIntegrationJob::dispatch($integration->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Synchronization queued.']);

        return back();
    }

    public function disconnect(Request $request, string $provider, Integration $integration, CurrentOrganization $current): RedirectResponse
    {
        $this->authorizeIntegration($request, $provider, $integration, $current);
        $integration->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($provider).' disconnected.']);

        return back();
    }

    public function assignResources(Request $request, string $provider, Integration $integration, CurrentOrganization $current): RedirectResponse
    {
        $this->authorizeIntegration($request, $provider, $integration, $current);
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'resource_ids' => ['array'],
            'resource_ids.*' => ['integer'],
            'status_filters' => ['sometimes', 'array'],
            'status_filters.*' => ['nullable', 'array', 'min:1'],
            'status_filters.*.*' => ['string'],
        ]);
        $workspace = Workspace::query()->where('organization_id', $integration->organization_id)->whereKey($validated['workspace_id'])->firstOrFail();
        $ids = $provider === 'jira'
            ? JiraProject::query()->where('integration_id', $integration->id)->whereIn('id', $validated['resource_ids'] ?? [])->pluck('id')
            : BitbucketRepository::query()->where('integration_id', $integration->id)->whereIn('id', $validated['resource_ids'] ?? [])->pluck('id');
        if ($provider === 'jira') {
            $projects = JiraProject::query()
                ->where('integration_id', $integration->id)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');
            $syncData = [];

            foreach ($ids as $id) {
                $project = $projects->get($id);
                if (! $project instanceof JiraProject) {
                    continue;
                }
                $selected = data_get($validated, 'status_filters.'.$id);
                $selected = is_array($selected) ? array_values(array_unique(array_map('strval', $selected))) : null;
                if ($selected !== null) {
                    if (array_diff($selected, $project->statusIds()) !== []) {
                        throw ValidationException::withMessages([
                            "status_filters.{$id}" => __('One or more selected Jira statuses are invalid.'),
                        ]);
                    }
                }
                $syncData[$id] = [
                    'included_status_ids' => $selected === null ? null : json_encode($selected, JSON_THROW_ON_ERROR),
                ];
            }

            $workspace->jiraProjects()->sync($syncData);
        } else {
            $workspace->bitbucketRepositories()->sync($ids);
        }
        SyncAtlassianIntegrationJob::dispatch($integration->id);

        return back();
    }

    public function configureConnection(Request $request, string $provider, Integration $integration, CurrentOrganization $current): RedirectResponse
    {
        $this->authorizeIntegration($request, $provider, $integration, $current);
        $settings = $integration->settings();

        if ($provider === 'jira') {
            $validated = $request->validate(['cloud_id' => ['required', 'string']]);
            $availableSites = data_get($settings, 'available_sites', []);
            $availableSites = is_array($availableSites) ? $availableSites : [];
            $site = null;
            foreach ($availableSites as $candidate) {
                if (is_array($candidate) && ($candidate['id'] ?? null) === $validated['cloud_id']) {
                    $site = $candidate;
                    break;
                }
            }
            abort_unless(is_array($site), 422);
            $settings['cloud_id'] = $site['id'];
            $settings['site_url'] = $site['url'];
            $integration->external_id = (string) $site['id'];
        } else {
            $validated = $request->validate(['workspace_slugs' => ['required', 'array', 'min:1'], 'workspace_slugs.*' => ['string']]);
            $availableWorkspaces = data_get($settings, 'available_workspaces', []);
            $availableWorkspaces = is_array($availableWorkspaces) ? $availableWorkspaces : [];
            $allowed = array_values(array_filter(array_map(fn ($workspace) => is_array($workspace) ? ($workspace['slug'] ?? null) : null, $availableWorkspaces)));
            foreach ($validated['workspace_slugs'] as $slug) {
                abort_unless(in_array($slug, $allowed, true), 422);
            }
            $settings['workspace_slugs'] = array_values($validated['workspace_slugs']);
        }

        $integration->forceFill(['settings_json' => $settings])->save();
        SyncAtlassianIntegrationJob::dispatch($integration->id);

        return back();
    }

    public function assignRepositoryUsers(Request $request, Integration $integration, BitbucketRepository $repository, CurrentOrganization $current): RedirectResponse
    {
        $this->authorizeIntegration($request, 'bitbucket', $integration, $current);
        abort_unless($repository->integration_id === $integration->id, 404);
        $validated = $request->validate(['user_ids' => ['array'], 'user_ids.*' => ['integer']]);
        $valid = User::query()->whereHas('organizations', fn ($q) => $q->whereKey($integration->organization_id))->whereIn('id', $validated['user_ids'] ?? [])->pluck('id');
        $repository->users()->sync($valid);

        return back();
    }

    public function mapUser(Request $request, string $provider, Integration $integration, IntegrationUser $externalUser, CurrentOrganization $current): RedirectResponse
    {
        $this->authorizeIntegration($request, $provider, $integration, $current);
        abort_unless($externalUser->integration_id === $integration->id, 404);
        $validated = $request->validate(['user_id' => ['nullable', 'integer', Rule::exists('organization_user', 'user_id')->where('organization_id', $integration->organization_id)]]);
        if ($validated['user_id'] ?? null) {
            IntegrationUser::query()->where('integration_id', $integration->id)->where('user_id', $validated['user_id'])->whereKeyNot($externalUser->id)->update(['user_id' => null]);
        }
        $externalUser->update(['user_id' => $validated['user_id'] ?? null]);

        return back();
    }

    private function provider(string $provider): void
    {
        abort_unless(config('features.atlassian_integrations') && in_array($provider, ['jira', 'bitbucket'], true), 404);
    }

    private function authorizeIntegration(Request $request, string $provider, Integration $integration, CurrentOrganization $current): void
    {
        $this->provider($provider);

        $organization = $current->fromRequest($request);
        abort_unless($organization && $integration->organization_id === $organization->id && $integration->provider === $provider, 404);
    }
}
