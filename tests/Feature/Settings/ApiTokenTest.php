<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_lists_the_users_tokens_without_their_secrets()
    {
        $user = User::factory()->create();
        $user->createToken('Laptop', ['read', 'write']);
        $user->createToken('Old script', ['read'], now()->subDay());
        User::factory()->create()->createToken('Someone else', ['read', 'write']);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('api-tokens.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/api-tokens')
                ->has('tokens', 2)
                ->where('tokens.0.name', 'Old script')
                ->where('tokens.0.read_only', true)
                ->where('tokens.0.expired', true)
                ->where('tokens.1.name', 'Laptop')
                ->where('tokens.1.read_only', false)
                ->where('tokens.1.expires_at', null)
                ->missing('tokens.0.token')
                ->where('newToken', null)
                ->where('apiUrl', url('/api/v1')));
    }

    public function test_the_page_requires_a_fresh_password()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('api-tokens.index'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_a_token_is_made_and_shown_once()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('api-tokens.index'))
            ->post(route('api-tokens.store'), ['name' => 'Laptop'])
            ->assertRedirect(route('api-tokens.index'))
            ->assertSessionHas('newToken');

        $plain = $response->getSession()->get('newToken');
        $token = $user->tokens()->sole();

        $this->assertSame('Laptop', $token->name);
        $this->assertSame(['read', 'write'], $token->abilities);
        $this->assertNull($token->expires_at);
        $this->assertSame(hash('sha256', explode('|', $plain, 2)[1]), $token->token);

        // The secret works against the API, and is gone from the next page load.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withToken($plain)->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.email', $user->email);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('api-tokens.index'))
            ->assertInertia(fn (Assert $page) => $page->where('newToken', null)->has('tokens', 1));
    }

    public function test_a_token_can_be_read_only_and_expire()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), ['name' => 'Reporting', 'read_only' => true, 'expires_in_days' => 30])
            ->assertSessionHasNoErrors();

        $token = $user->tokens()->sole();

        $this->assertSame(['read'], $token->abilities);
        $this->assertSame(now()->addDays(30)->toDateString(), $token->expires_at->toDateString());
    }

    public function test_a_token_needs_a_name_and_one_of_the_offered_lifetimes()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), ['name' => '', 'expires_in_days' => 7])
            ->assertSessionHasErrors(['name', 'expires_in_days']);

        $this->assertCount(0, $user->tokens);
    }

    public function test_a_token_can_be_revoked_but_only_by_its_owner()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $user->createToken('Laptop');
        $theirs = $other->createToken('Theirs');

        $this->actingAs($user)
            ->delete(route('api-tokens.destroy', $theirs->accessToken))
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('api-tokens.destroy', $mine->accessToken))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $mine->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $theirs->accessToken->id]);

        $this->app['auth']->forgetGuards();
        $this->withToken($mine->plainTextToken)->getJson(route('api.v1.me'))->assertUnauthorized();
    }
}
