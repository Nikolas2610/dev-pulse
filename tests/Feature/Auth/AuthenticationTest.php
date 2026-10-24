<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_local_login_screen_shows_recent_users()
    {
        $this->app->detectEnvironment(fn () => 'local');
        $oldest = User::factory()->create([
            'name' => 'Oldest User',
            'email' => 'oldest@example.com',
            'created_at' => now()->subMinutes(6),
        ]);

        config(['devpulse.demo_user_password' => 'configured-demo-password']);

        $users = collect(range(1, 5))
            ->map(fn (int $index) => User::factory()->create([
                'name' => "Recent User {$index}",
                'email' => "recent{$index}@example.com",
                'created_at' => now()->subMinutes(6 - $index),
            ]));

        $response = $this->get(route('login'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->has('recentLoginUsers', 6)
            ->where('recentLoginUsers.0.email', $users->last()->email)
            ->where('recentLoginUsers.4.email', $users->first()->email)
            ->where('recentLoginUsers.5.email', $oldest->email)
        );
    }

    public function test_non_local_login_screen_does_not_expose_users_or_demo_password()
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['devpulse.demo_user_password' => 'configured-demo-password']);

        collect(range(1, 5))
            ->map(fn (int $index) => User::factory()->create([
                'email' => "production-user{$index}@example.com",
                'created_at' => now()->subMinutes(5 - $index),
            ]));

        $response = $this->get(route('login'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('developmentLogin', false)
            ->where('recentLoginUsers', [])
            ->where('developmentPassword', null)
        );
    }

    public function test_local_login_screen_includes_roles_for_development_users()
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['devpulse.demo_user_password' => 'configured-demo-password']);
        $organization = Organization::factory()->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $manager = User::factory()->create([
            'current_organization_id' => $organization->id,
            'current_workspace_id' => $workspace->id,
        ]);
        $organization->users()->attach($manager, ['role' => 'member']);
        $workspace->users()->attach($manager, ['role' => 'manager', 'is_active' => true]);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('developmentLogin', true)
            ->where('developmentPassword', 'configured-demo-password')
            ->where('recentLoginUsers.0.email', $manager->email)
            ->where('recentLoginUsers.0.role', 'manager'));
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
