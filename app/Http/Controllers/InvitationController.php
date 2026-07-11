<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $email = Str::lower((string) $request->query('email'));
        $user = User::query()->where('email', $email)->first();

        abort_unless($user && Password::broker()->tokenExists($user, $token), 404);

        $organization = $user->current_organization_id
            ? Organization::query()->find($user->current_organization_id)
            : null;
        $workspace = $user->current_workspace_id
            ? Workspace::query()->find($user->current_workspace_id)
            : null;

        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'email' => $user->email,
            'name' => $user->name,
            'organizationName' => $organization?->name,
            'workspaceName' => $workspace?->name,
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                Auth::login($user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['token' => __($status)]);
        }

        return redirect()->route('dashboard');
    }
}
