<?php

namespace Tests\Feature\Settings;

use App\Enums\ThemeColor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_appearance_page_offers_the_theme_colours()
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/appearance')
                ->has('themeColors', count(ThemeColor::cases()))
                ->where('themeColors.1.value', 'violet')
                ->where('auth.user.theme_color', null),
            );
    }

    public function test_the_theme_colour_is_saved_on_the_account_and_set_on_the_page_before_it_loads()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('appearance.edit'))
            ->patch(route('appearance.update'), ['theme_color' => 'violet'])
            ->assertRedirect(route('appearance.edit'));

        $this->assertSame(ThemeColor::Violet, $user->fresh()->theme_color);

        $this->actingAs($user)
            ->get(route('dashboard', $user->currentTeam))
            ->assertSee('data-theme-color="violet"', false);
    }

    public function test_the_page_falls_back_to_the_default_colour_for_guests_and_unset_accounts()
    {
        $this->get(route('login'))->assertSee('data-theme-color="slate"', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertSee('data-theme-color="slate"', false);
    }

    public function test_unknown_theme_colours_are_rejected()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('appearance.edit'))
            ->patch(route('appearance.update'), ['theme_color' => 'neon'])
            ->assertSessionHasErrors('theme_color');

        $this->assertNull($user->fresh()->theme_color);
    }
}
