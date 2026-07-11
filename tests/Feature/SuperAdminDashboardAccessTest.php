<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Tests\TestCase;

class SuperAdminDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_super_admin_can_access_horizon_and_log_viewer(): void
    {
        config(['app.super_admin_email' => 'admin@example.com']);
        $superAdmin = User::factory()->create(['email' => 'Admin@example.com']);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewLogViewer'));
        $this->assertTrue(Horizon::check(request()->setUserResolver(fn () => $superAdmin)));

        $this->actingAs($superAdmin)->get('/horizon')->assertOk();
        $this->actingAs($superAdmin)->get('/log-viewer')->assertOk();
    }

    public function test_other_users_are_denied_in_every_environment(): void
    {
        config([
            'app.super_admin_email' => 'admin@example.com',
            'app.env' => 'local',
        ]);
        $user = User::factory()->create(['email' => 'other@example.com']);

        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($user)->allows('viewLogViewer'));
        $this->assertFalse(Horizon::check(request()->setUserResolver(fn () => $user)));

        $this->actingAs($user)->get('/horizon')->assertForbidden();
        $this->actingAs($user)->get('/log-viewer')->assertForbidden();
    }
}
