<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_views_render_the_persisted_theme_from_cookie(): void
    {
        $this->withCookie('une-theme', 'uneThemeDark')
            ->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme="uneThemeDark"', false);
    }

    public function test_authenticated_views_render_the_persisted_theme_from_cookie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withCookie('une-theme', 'uneThemeDark')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="uneThemeDark"', false);
    }
}
