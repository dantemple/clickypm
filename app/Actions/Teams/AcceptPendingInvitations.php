<?php

namespace App\Actions\Teams;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AcceptPendingInvitations
{
    /**
     * Accept every open invitation sent to the user's email address and
     * switch them to the first workspace that invited them.
     *
     * Only ever called once the address is verified, so nobody can claim an
     * invitation by signing up with someone else's email.
     */
    public function handle(User $user): bool
    {
        $invitations = $this->pendingFor($user->email)->with('team')->oldest()->get();

        foreach ($invitations as $invitation) {
            $invitation->team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);
        }

        if ($invitations->isEmpty()) {
            return false;
        }

        $user->switchTeam($invitations->first()->team);

        return true;
    }

    /**
     * Open, unexpired invitations sent to the address.
     *
     * @return Builder<TeamInvitation>
     */
    public function pendingFor(string $email): Builder
    {
        return TeamInvitation::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->whereNull('accepted_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }
}
