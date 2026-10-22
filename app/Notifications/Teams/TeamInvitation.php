<?php

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TeamInvitationModel $invitation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__("You've been invited to join :teamName", ['teamName' => $team->name]))
            ->line(__(':inviterName has invited you to join the :teamName workspace.', [
                'inviterName' => $inviter->name,
                'teamName' => $team->name,
            ]))
            ->line(__('New here? Sign up with this email address and you will be added automatically. Already have an account? Log in and accept the invitation from your dashboard.'))
            ->action(
                __('Log in'),
                route('login', ['invitation' => $this->invitation->code]),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
