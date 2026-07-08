<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscordIntegrationController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        $integration = $organization && $workspace
            ? $this->workspaceIntegration($organization->id, $workspace->id)
            : null;

        return Inertia::render('integrations/discord', [
            'integration' => $integration,
            'isConfigured' => filled($integration?->webhook_url),
            'canManageIntegration' => $organization
                ? $currentOrganization->canManageOrganization($organization, $request->user())
                : false,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $currentOrganization->canManageOrganization($organization, $request->user()), 403);

        $workspace = $currentOrganization->currentWorkspaceForUser($organization, $request->user());
        abort_unless($workspace, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'webhook_url' => [
                'required',
                'url',
                'max:1000',
                'regex:/^https:\/\/(discord\.com|discordapp\.com)\/api\/webhooks\//',
            ],
        ]);

        Integration::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'workspace_id' => $workspace->id,
                'provider' => 'discord',
            ],
            [
                'name' => $validated['name'],
                'webhook_url' => $validated['webhook_url'],
                'is_active' => true,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Discord webhook saved.')]);

        return to_route('integrations.discord.index');
    }

    private function workspaceIntegration(int $organizationId, int $workspaceId): ?Integration
    {
        return Integration::query()
            ->where('organization_id', $organizationId)
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'discord')
            ->latest()
            ->first();
    }
}
