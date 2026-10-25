<?php

namespace App\Listeners;

use App\Actions\Teams\AcceptPendingInvitations;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class AcceptInvitationsOnVerification
{
    public function __construct(private AcceptPendingInvitations $acceptInvitations)
    {
        //
    }

    /**
     * Once someone proves they own the address, let them into the
     * workspaces that invited it.
     */
    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->acceptInvitations->handle($event->user);
        }
    }
}
