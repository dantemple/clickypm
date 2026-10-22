<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_to_their_current_team_dashboard()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard', $user->currentTeam));
    }
}
