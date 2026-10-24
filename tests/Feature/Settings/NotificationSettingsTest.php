<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_settings_page_is_displayed()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('notifications.edit'))
            ->assertOk();
    }

    public function test_task_and_reminder_emails_are_on_by_default()
    {
        $user = User::factory()->create();

        $this->assertTrue($user->task_emails);
        $this->assertTrue($user->reminder_emails);
    }

    public function test_emails_can_be_turned_off_and_back_on()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('notifications.update'), ['task_emails' => false, 'reminder_emails' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('notifications.edit'));

        $this->assertFalse($user->refresh()->task_emails);
        $this->assertTrue($user->reminder_emails);

        $this->actingAs($user)
            ->put(route('notifications.update'), ['task_emails' => true, 'reminder_emails' => false])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->refresh()->task_emails);
        $this->assertFalse($user->reminder_emails);
    }

    public function test_both_settings_are_required_and_must_be_booleans()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('notifications.edit'))
            ->put(route('notifications.update'), ['task_emails' => 'maybe'])
            ->assertSessionHasErrors(['task_emails', 'reminder_emails']);

        $this->assertTrue($user->refresh()->task_emails);
    }
}
