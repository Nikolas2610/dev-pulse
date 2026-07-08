<?php

namespace App\Http\Middleware;

use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $currentOrganization = app(CurrentOrganization::class);
        $organization = $currentOrganization->fromRequest($request);
        $user = $request->user();
        $availableWorkspaces = $organization && $user
            ? $currentOrganization->workspacesForUser($organization, $user)
            : collect();
        $currentWorkspace = $organization && $user
            ? $currentOrganization->currentWorkspaceForUser($organization, $user)
            : null;

        $appName = config('app.name') === 'Laravel'
            ? 'DevPulse'
            : config('app.name');

        return [
            ...parent::share($request),
            'name' => $appName,
            'auth' => [
                'user' => $request->user(),
            ],
            'organizations' => $user
                ? $user->organizations()->orderBy('name')->get(['organizations.id', 'name', 'slug', 'timezone'])
                : [],
            'currentOrganization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'timezone' => $organization->timezone,
            ] : null,
            'currentWorkspace' => $currentWorkspace ? [
                'id' => $currentWorkspace->id,
                'name' => $currentWorkspace->name,
                'slug' => $currentWorkspace->slug,
                'timezone' => $currentWorkspace->timezone,
            ] : null,
            'workspaces' => $availableWorkspaces->map(fn ($workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'timezone' => $workspace->timezone,
            ])->values(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
