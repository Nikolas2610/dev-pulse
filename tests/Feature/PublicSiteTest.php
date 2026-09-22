<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_every_public_product_page(): void
    {
        foreach ([
            '/' => 'welcome',
            '/product' => 'public/product',
            '/integrations' => 'public/integrations',
            '/about' => 'public/about',
        ] as $uri => $component) {
            $this->get($uri)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_authenticated_users_can_view_every_public_product_page(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/', '/product', '/about'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->get('/integrations')->assertRedirect('/integrations/slack');
    }
}
