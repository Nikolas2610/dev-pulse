<?php

namespace App\Http\Middleware;

use App\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs([
            'onboarding.*',
            'logout',
            'verification.*',
            'integrations.slack.connect',
            'integrations.slack.callback',
        ])) {
            return $next($request);
        }

        $currentOrganization = app(CurrentOrganization::class);
        $organization = $currentOrganization->fromRequest($request);

        if (! $organization) {
            return $user->onboarding_required
                ? to_route('onboarding.index')
                : $next($request);
        }

        if (
            ! $organization->onboarding_completed_at
            && $currentOrganization->canManageOrganization($organization, $user)
        ) {
            return to_route('onboarding.index');
        }

        return $next($request);
    }
}
