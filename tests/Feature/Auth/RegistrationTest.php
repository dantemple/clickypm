<?php

namespace Tests\Feature\Auth;

use App\Enums\RegistrationMode;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('inviteOnly', false),
        );
    }

    public function test_auth_screens_know_when_registration_is_invite_only()
    {
        config(['app.registration' => RegistrationMode::InviteOnly->value]);

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('inviteOnly', true),
        );

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('inviteOnly', true),
        );
    }

    public function test_registration_screen_includes_team_invitation_context()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Laravel Team']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->get(route('register', ['invitation' => $invitation->code]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('teamInvitation.code', $invitation->code)
            ->where('teamInvitation.teamName', 'Laravel Team'),
        );
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_an_existing_email_cannot_be_registered_again_in_different_case()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Imposter',
                'email' => 'Taken@Example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_uninvited_users_cannot_register_when_invite_only()
    {
        config(['app.registration' => RegistrationMode::InviteOnly->value]);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Stranger',
                'email' => 'stranger@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_invited_users_can_register_when_invite_only()
    {
        config(['app.registration' => RegistrationMode::InviteOnly->value]);

        TeamInvitation::factory()->create(['email' => 'invited@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Invited User',
            'email' => 'Invited@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
    }

    public function test_expired_or_accepted_invitations_do_not_allow_registration_when_invite_only()
    {
        config(['app.registration' => RegistrationMode::InviteOnly->value]);

        TeamInvitation::factory()->expired()->create(['email' => 'late@example.com']);
        TeamInvitation::factory()->accepted()->create(['email' => 'done@example.com']);

        foreach (['late@example.com', 'done@example.com'] as $email) {
            $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => $email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertSessionHasErrors('email');
        }

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'late@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'done@example.com']);
    }
}
