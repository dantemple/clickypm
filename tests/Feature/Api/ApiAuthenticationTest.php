<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calls_without_a_valid_token_get_a_json_401()
    {
        $this->getJson(route('api.v1.me'))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->withToken('1|not-a-real-token')->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_expired_tokens_are_refused()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Old', ['read', 'write'], now()->subMinute());

        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_read_only_tokens_can_read_but_not_write()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $token = $user->createToken('Reporting', ['read']);

        $this->withToken($token->plainTextToken)
            ->getJson(route('api.v1.projects.tasks.index', $project))
            ->assertOk();

        $this->withToken($token->plainTextToken)
            ->postJson(route('api.v1.projects.tasks.store', $project), ['title' => 'Nope'])
            ->assertForbidden();

        $this->assertCount(0, $project->tasks);
    }

    public function test_using_a_token_records_when_it_was_last_used()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Laptop');

        $this->assertNull($token->accessToken->last_used_at);

        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me'))->assertOk();

        $this->assertNotNull($token->accessToken->fresh()->last_used_at);
    }

    public function test_validation_errors_come_back_as_json()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $token = $user->createToken('Laptop');

        $this->withToken($token->plainTextToken)
            ->postJson(route('api.v1.projects.tasks.store', $project), ['priority' => 'sometime'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority']);
    }
}
