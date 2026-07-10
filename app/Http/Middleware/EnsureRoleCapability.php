<?php

namespace App\Http\Middleware;

use App\Services\CurrentOrganization;
use App\Services\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleCapability
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private RoleAccess $access,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $capability,
    ): Response {
        $organization = $this->currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $this->currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;

        abort_unless(
            $this->access->allows($capability, $organization, $workspace, $request->user()),
            403,
        );

        return $next($request);
    }
}
