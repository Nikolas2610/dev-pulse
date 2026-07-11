<?php

namespace App\Http\Controllers;

use App\Services\CurrentOrganization;
use App\Support\TimezoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless(
            $organization && $currentOrganization->canManageOrganization($organization, $request->user()),
            403,
        );

        return Inertia::render('settings/organization', [
            'organization' => $organization,
        ]);
    }

    public function update(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless(
            $organization && $currentOrganization->canManageOrganization($organization, $request->user()),
            403,
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => TimezoneRules::required(),
        ]);

        $organization->update([
            ...$validated,
            'timezone' => TimezoneRules::normalize($validated['timezone']),
        ]);

        return to_route('settings.organization.edit');
    }
}
