<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chosen_theme_color_is_set_on_the_page_before_it_loads()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withUnencryptedCookie('theme_color', 'violet')
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertSee('data-theme-color="violet"', false);
    }

    public function test_the_theme_color_falls_back_to_the_default_when_unset_or_tampered_with()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertSee('data-theme-color="slate"', false);

        $this->actingAs($user)
            ->withUnencryptedCookie('theme_color', '"><script>alert(1)</script>')
            ->get(route('appearance.edit'))
            ->assertSee('data-theme-color="slate"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
